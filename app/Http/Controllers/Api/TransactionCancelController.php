<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionCancelController extends Controller
{
    public function cancel(Request $request)
    {
        $transactionId = trim((string) $request->header('transactionid', ''));
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transactionId,
        ];
        $datar = [];

        if ($transactionId === '') {
            $response['message'] = 'Transaction ID is required';
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

        $devices = [];
        foreach ($devicesPayload as $deviceEntry) {
            $device = is_object($deviceEntry) ? (array) $deviceEntry : $deviceEntry;
            if (!is_array($device)) {
                $device = ['global_device_id' => $device];
            }

            if (!isset($device['global_device_id']) || trim((string) $device['global_device_id']) === '') {
                $response['message'] = 'Each device entry must contain global_device_id';
                return response()->json($response, 400);
            }

            $globalDeviceId = trim((string) $device['global_device_id']);
            if (!is_valid_global_device_id($globalDeviceId)) {
                $response['message'] = "Invalid value for field 'global_device_id'. Only letters, numbers, underscore, and hyphen are allowed";
                return response()->json($response, 400);
            }

            $msn = null;
            if (array_key_exists('msn', $device) && $device['msn'] !== null && $device['msn'] !== '') {
                if (!is_valid_msn($device['msn'])) {
                    $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
                    return response()->json($response, 400);
                }

                $msn = (string) $device['msn'];
            }

            $devices[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $msn,
            ];
        }

        $timestamp = now();
        $successful = 0;

        foreach ($devices as $device) {
            $globalDeviceId = $device['global_device_id'];
            $meter = $this->findMeter($globalDeviceId, $device['msn']);

            if (!$meter) {
                $datar[] = $this->buildFailurePayload($globalDeviceId, $device['msn'], config('udil.meter_not_exists'));
                continue;
            }

            $transactions = DB::connection('mysql2')
                ->table('transaction_status')
                ->where('transaction_id', $transactionId)
                ->where('global_device_id', $globalDeviceId)
                ->get();

            if ($transactions->isEmpty()) {
                $datar[] = $this->buildFailurePayload($globalDeviceId, $meter->msn, 'No Operations are queued for this Global Device ID for this transaction');
                continue;
            }

            foreach ($transactions as $row) {
                $slugType = strtoupper((string) $row->type);

                if ($this->isCancellable($row)) {
                    $entry = $this->handleCancellationForType($slugType, $meter);

                    if ($entry['indv_status'] === '1') {
                        $this->markTransactionCancelled($transactionId, $globalDeviceId, $timestamp);
                        $successful++;
                    }

                    $datar[] = $entry;
                } else {
                    $datar[] = $this->buildFailurePayload($globalDeviceId, $meter->msn, 'Command sent to Meter so Transaction cannot be cancelled');
                }
            }
        }

        $response['status'] = $successful > 0 ? 1 : 0;
        $response['http_status'] = $successful > 0 ? 200 : 400;
        $response['message'] = $successful > 0
            ? 'Transaction will be cancelled in meter with indv_status = 1'
            : 'Unable to cancel requested transactions';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function isCancellable($transactionRow): bool
    {
        return is_null($transactionRow->status_level) || (int) $transactionRow->status_level < 3;
    }

    private function markTransactionCancelled(string $transactionId, string $globalDeviceId, $timestamp): void
    {
        DB::connection('mysql2')
            ->table('transaction_status')
            ->where('transaction_id', $transactionId)
            ->where('global_device_id', $globalDeviceId)
            ->update([
                'request_cancelled' => 1,
                'request_cancel_reason' => 'Cancelled by Service',
                'request_cancel_datetime' => $timestamp,
            ]);
    }

    private function handleCancellationForType(string $slugType, $meter): array
    {
        $globalDeviceId = $meter->global_device_id;
        $msn = $meter->msn;

        switch ($slugType) {
            case 'WSIM':
                $this->updateMeter($globalDeviceId, [
                    'set_wakeup_profile_id' => 0,
                    'number_profile_group_id' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WMDI':
                $this->updateMeter($globalDeviceId, [
                    'write_mdi_reset_date' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WAUXR':
                $this->updateMeter($globalDeviceId, [
                    'apply_new_contactor_state' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WSANC':
                $this->updateMeter($globalDeviceId, [
                    'write_contactor_param' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WMDSM':
                $this->updateMeter($globalDeviceId, [
                    'lp_write_interval_request' => 0,
                    'lp2_write_interval_request' => 0,
                    'lp3_write_interval_request' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WOPPO':
                $this->updateMeter($globalDeviceId, [
                    'update_optical_port_access' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WLSCH':
                $this->updateMeter($globalDeviceId, [
                    'write_load_shedding_schedule' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WIPPO':
                $this->updateMeter($globalDeviceId, [
                    'set_ip_profiles' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WPMAL':
                $this->updateMeter($globalDeviceId, [
                    'major_alarm_group_id' => 0,
                    'save_events_on_alarm' => 0,
                ]);

                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update([
                        'pmal_event_codes' => null,
                        'pmal_datetime' => null,
                    ]);

                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WTIOU':
                $this->updateMeter($globalDeviceId, [
                    'activity_calendar_id' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WDVTM':
                $this->updateMeter($globalDeviceId, [
                    'super_immediate_cs' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WDMDT':
                $this->updateMeter($globalDeviceId, [
                    'set_keepalive' => 0,
                    'tbe1_write_request_id' => 0,
                    'energy_param_id' => 0,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WMTST':
                $status = (int) ($meter->status ?? 0);
                $this->updateMeter($globalDeviceId, [
                    'status' => $status === 1 ? 0 : 1,
                ]);
                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            case 'WDVCR':
                DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $globalDeviceId)
                    ->where('msn', $msn)
                    ->delete();

                return get_transaction_cancel_response($globalDeviceId, $msn, $slugType);

            default:
                return [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => "Unknown Type '" . $slugType . "' for this Transaction. Only Write Jobs can be cancelled",
                ];
        }
    }

    private function updateMeter(string $globalDeviceId, array $data): void
    {
        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId)
            ->update($data);
    }

    private function findMeter(string $globalDeviceId, ?string $msn)
    {
        $query = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId);

        if ($msn !== null) {
            $query->where('msn', $msn);
        }

        return $query->first();
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
}
