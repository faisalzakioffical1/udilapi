<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MdiResetController extends Controller
{


    /**
     * Update MDI Reset Date for given meters.
     */
    public function updateMdiResetDate(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (!isset($formParams['mdi_reset_date']) || !isset($formParams['mdi_reset_time'])) {
            $response['message'] = 'mdi_reset_date OR mdi_reset_time fields are missing';
            return response()->json($response, $response['http_status']);
        }

        if (!filterInteger($formParams['mdi_reset_date'], 1, 28) || !is_time_valid($formParams['mdi_reset_time'])) {
            $response['message'] = 'Invalid values are provided in mdi_reset_date OR mdi_reset_time fields';
            return response()->json($response, $response['http_status']);
        }

        if (!isset($formParams['request_datetime'])) {
            $response['message'] = 'request_datetime field is required';
            return response()->json($response, $response['http_status']);
        }

        if (function_exists('is_date_valid') && !is_date_valid($formParams['request_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'request_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        $devices = $this->normalizeDeviceList($formParams['global_device_id'] ?? null);
        if (!is_array($devices)) {
            $response['message'] = 'global_device_id must be a JSON array of devices';
            return response()->json($response, $response['http_status']);
        }

        if (empty($devices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        foreach ($devices as $deviceEntry) {
            $device = is_object($deviceEntry) ? (array)$deviceEntry : $deviceEntry;

            if (!isset($device['global_device_id']) || !isset($device['msn']) || trim((string)$device['global_device_id']) === '' || trim((string)$device['msn']) === '') {
                $response['message'] = 'Each device entry must contain both global_device_id and msn';
                return response()->json($response, $response['http_status']);
            }

            if (!is_valid_global_device_id($device['global_device_id'])) {
                $response['message'] = "Invalid value for field 'global_device_id'. Only letters, numbers, underscore, and hyphen are allowed";
                return response()->json($response, $response['http_status']);
            }

            if (!is_valid_msn($device['msn'])) {
                $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
                return response()->json($response, $response['http_status']);
            }
        }

        $slug = $formParams['slug'] ?? 'update_mdi_reset_date';
        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['request_datetime']))
            ->format('Y-m-d H:i:s');
        $mdiResetDate = (int)trim((string)$formParams['mdi_reset_date'], "\"' ");
        $mdiResetTime = $this->normalizeTime($formParams['mdi_reset_time']);
        $successfulMeters = 0;

        foreach ($devices as $deviceEntry) {
            $device = is_object($deviceEntry) ? (array)$deviceEntry : $deviceEntry;
            $globalDeviceId = trim((string)$device['global_device_id']);

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $device['msn'],
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WMDI');

            $meterUpdateData = [
                'mdi_reset_date' => $mdiResetDate,
                'mdi_reset_time' => $mdiResetTime,
                'write_mdi_reset_date' => 1,
            ];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdateData);

            $visualsUpdateData = [
                'mdi_reset_date' => $mdiResetDate,
                'mdi_reset_time' => $mdiResetTime,
            ];

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'New MDI RESET date will be programmed in meter upon communication',
                ];

                $visualsUpdateData = array_merge($visualsUpdateData, [
                    'last_command' => $slug,
                    'last_command_datetime' => $requestDatetime,
                    'last_command_resp' => json_encode($deviceResponse),
                    'last_command_resp_datetime' => $requestDatetime,
                ]);
            }

            DB::connection('mysql2')
                ->table('meter_visuals')
                ->where('global_device_id', $globalDeviceId)
                ->update($visualsUpdateData);

            update_on_transaction_success($slug, $globalDeviceId, $requestDatetime);

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $meterMsn,
                'indv_status' => '1',
                'remarks' => 'New MDI RESET date will be programmed in meter upon communication',
            ];

            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'New MDI RESET date will be programmed in meter with indv_status = 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function normalizeDeviceList($payload): ?array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : null;
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function normalizeTime($time): string
    {
        $sanitized = trim((string)$time, "\"' ");
        $parts = explode(':', $sanitized);

        $hour = isset($parts[0]) ? (int)$parts[0] : 0;
        $minute = isset($parts[1]) ? (int)$parts[1] : 0;
        $second = isset($parts[2]) ? (int)$parts[2] : 0;

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }






}
