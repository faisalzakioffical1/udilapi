<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnDemandDataController extends Controller
{
    private const SUPPORTED_TYPES = ['INST', 'BILL', 'MBIL', 'EVNT', 'LPRO'];

    /**
     * On Demand Data Read for given meters.
     */
    public function read(Request $request)
    {
        $transaction = $request->header('transactionid', '');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if ($transaction === '') {
            $response['message'] = 'Transaction ID is required';
            return response()->json($response, 400);
        }

        if (!isset($formParams['start_datetime'], $formParams['end_datetime'], $formParams['type'])) {
            $response['message'] = 'start_datetime, end_datetime & type fields are mandatory and required';
            return response()->json($response, 400);
        }

        $startDatetime = trim((string) $formParams['start_datetime']);
        $endDatetime = trim((string) $formParams['end_datetime']);

        if (!is_date_valid($startDatetime, "Y-m-d H:i:s") || !is_date_valid($endDatetime, "Y-m-d H:i:s")) {
            $response['message'] = 'Invalid dates are provided in start_datetime & end_datetime fields';
            return response()->json($response, 400);
        }

        if (strtotime($startDatetime) >= strtotime($endDatetime)) {
            $response['message'] = 'start_datetime must be earlier than end_datetime';
            return response()->json($response, 400);
        }

        $type = strtoupper((string) $formParams['type']);
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            $response['message'] = 'type field has invalid value. Only these fields are applicable INST, BILL, MBIL, EVNT, LPRO';
            return response()->json($response, 400);
        }

        if (!array_key_exists('global_device_id', $formParams)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, 400);
        }

        $devicesPayload = $this->normalizeDeviceList($formParams['global_device_id']);
        if (!is_array($devicesPayload)) {
            $response['message'] = 'global_device_id must be a JSON array of devices';
            return response()->json($response, 400);
        }

        if (empty($devicesPayload)) {
            $response['message'] = 'No devices provided';
            return response()->json($response, 400);
        }

        $normalizedDevices = [];
        foreach ($devicesPayload as $deviceEntry) {
            $device = is_object($deviceEntry) ? (array) $deviceEntry : $deviceEntry;

            if (!isset($device['global_device_id']) || trim((string) $device['global_device_id']) === '') {
                $response['message'] = 'Each device entry must contain global_device_id';
                return response()->json($response, 400);
            }

            $globalDeviceId = trim((string) $device['global_device_id']);
            if (!is_valid_global_device_id($globalDeviceId)) {
                $response['message'] = "Invalid value for field 'global_device_id'. Only letters, numbers, underscore, and hyphen are allowed";
                return response()->json($response, 400);
            }

            if (array_key_exists('msn', $device) && !is_valid_msn($device['msn'])) {
                $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
                return response()->json($response, 400);
            }

            $normalizedDevices[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $device['msn'] ?? null,
            ];
        }

        [$table, $limit] = $this->resolveDataSource($type);
        $now = now()->format('Y-m-d H:i:s');
        $slug = $formParams['slug'] ?? 'on_demand_data_read';
        $successfulMeters = 0;

        foreach ($normalizedDevices as $device) {
            $globalDeviceId = $device['global_device_id'];

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = $this->buildFailurePayload($globalDeviceId, $device['msn'], config('udil.meter_not_exists'));
                continue;
            }

            $meterMsn = $meter->msn;

            setOnDemandReadTransactionStatus(true, $meterMsn, $now, $slug, $transaction, $globalDeviceId, $type, $startDatetime, $endDatetime);

            $wakeupResp = readOndemandStatusLevel($transaction, $type, $globalDeviceId);

            if ($wakeupResp) {
                $dataRows = $this->fetchDataRows($table, $globalDeviceId, $limit, $startDatetime, $endDatetime);

                update_on_transaction_success($slug, $globalDeviceId);
                $successfulMeters++;

                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $meterMsn,
                    'indv_status' => '1',
                    'remarks' => 'On demand data fetched successfully',
                    'data' => $dataRows,
                ];
            } else {
                $datar[] = $this->buildFailurePayload($globalDeviceId, $meterMsn, "Network Error, Meter didn't communicated in Maximum allowed threshold of 5.5 minutes");
            }
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = $successfulMeters > 0
            ? 'On demand data results'
            : 'All requested meters failed to respond within the allowed threshold';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function resolveDataSource(string $type): array
    {
        switch ($type) {
            case 'LPRO':
                return ['load_profile_data', 5];
            case 'BILL':
                return ['billing_data', 1];
            case 'MBIL':
                return ['monthly_billing_data', 2];
            case 'EVNT':
                return ['events', 10];
            default:
                return ['instantaneous_data', 1];
        }
    }

    private function fetchDataRows(string $table, string $globalDeviceId, int $limit, string $startDatetime, string $endDatetime): array
    {
        return DB::connection('mysql2')
            ->table($table)
            ->where('global_device_id', $globalDeviceId)
            ->whereBetween('db_datetime', [$startDatetime, $endDatetime])
            ->orderBy('db_datetime', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function normalizeDeviceList($payload): ?array
    {
        if (is_null($payload)) {
            return null;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_string($decoded) || is_numeric($decoded)) {
                    $value = trim((string) $decoded);
                    return $value === '' ? [] : [['global_device_id' => $value]];
                }

                return is_array($decoded) ? $decoded : [];
            }

            $trimmed = trim($payload, "\"' ");
            return $trimmed === '' ? [] : [['global_device_id' => $trimmed]];
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function buildFailurePayload(string $globalDeviceId, $msn, string $message): array
    {
        return [
            'global_device_id' => $globalDeviceId,
            'msn' => $msn ?? 0,
            'indv_status' => '0',
            'remarks' => $message,
        ];
    }
}
