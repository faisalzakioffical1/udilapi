<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MajorAlarmController extends Controller
{
    private const MAX_CODES = 10;
    private const MIN_EVENT_CODE = 1;
    private const MAX_EVENT_CODE = 9999;

    /**
     * Configure major alarms for the specified meters.
     */
    public function update(Request $request)
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

        if (!isset($formParams['major_alarms_list'])) {
            $response['message'] = 'major_alarms_list field is required';
            return response()->json($response, $response['http_status']);
        }

        $majorAlarmCodes = $this->normalizeAlarmList($formParams['major_alarms_list']);
        if ($majorAlarmCodes === null) {
            $response['message'] = 'major_alarms_list must be an array of numeric event codes';
            return response()->json($response, $response['http_status']);
        }

        if (empty($majorAlarmCodes)) {
            $response['message'] = 'Provide at least one event code in major_alarms_list';
            return response()->json($response, $response['http_status']);
        }

        if (count($majorAlarmCodes) > self::MAX_CODES) {
            $response['message'] = 'major_alarms_list can contain at most ' . self::MAX_CODES . ' event codes';
            return response()->json($response, $response['http_status']);
        }

        $invalidCodes = array_filter($majorAlarmCodes, function ($code) {
            return $code < self::MIN_EVENT_CODE || $code > self::MAX_EVENT_CODE;
        });

        if (!empty($invalidCodes)) {
            $response['message'] = 'Event codes must be numeric values between ' . self::MIN_EVENT_CODE . ' and ' . self::MAX_EVENT_CODE;
            $response['debug'] = $invalidCodes;
            return response()->json($response, $response['http_status']);
        }

        if (!array_key_exists('global_device_id', $formParams)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $allDevices = $this->normalizeDeviceList($formParams['global_device_id']);
        if (!is_array($allDevices)) {
            $response['message'] = 'global_device_id must be a JSON array of devices';
            return response()->json($response, $response['http_status']);
        }

        if (empty($allDevices)) {
            $response['message'] = 'No devices provided';
            return response()->json($response, $response['http_status']);
        }

        $slug = $formParams['slug'] ?? 'update_major_alarms';
        $now = now()->format('Y-m-d H:i:s');
        $successCount = 0;

        $alarmGroupId = null;

        foreach ($allDevices as $deviceEntry) {
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

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = $this->buildFailurePayload($globalDeviceId, $device['msn'] ?? 0, config('udil.meter_not_exists'));
                continue;
            }

            if ($alarmGroupId === null) {
                $alarmGroupId = $this->getOrCreateAlarmGroup($majorAlarmCodes);
            }

            $msn = $meter->msn;

            insert_transaction_status($globalDeviceId, $msn, $transaction, 'WPMAL');

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update([
                    'major_alarm_group_id' => $alarmGroupId,
                    'read_events_on_major_alarms' => 1,
                    'save_events_on_alarm' => 1,
                    'unset_ma' => 0,
                ]);

            $deviceResponse = [
                'status' => 1,
                'http_status' => 200,
                'transactionid' => $transaction,
                'message' => 'Major alarm set will be programmed in meter upon communication',
            ];

            if (config('udil.update_meter_visuals_for_write_services')) {
                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update([
                        'pmal_event_codes' => json_encode($majorAlarmCodes),
                        'pmal_datetime' => $now,
                        'last_command' => $slug,
                        'last_command_datetime' => $now,
                        'last_command_resp' => json_encode($deviceResponse),
                        'last_command_resp_datetime' => $now,
                    ]);
            } else {
                update_on_transaction_success($slug, $globalDeviceId);
            }

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $msn,
                'indv_status' => '1',
                'remarks' => 'Major alarm set will be programmed in meter upon communication',
            ];
            $successCount++;
        }

        if ($successCount === 0) {
            $response['message'] = 'All requested meters failed to program major alarm set';
            $response['data'] = $datar;
            return response()->json($response, $response['http_status']);
        }

        $response['status'] = 1;
        $response['http_status'] = 200;
        $response['message'] = 'Major alarm set will be programmed against meters having indv_status = 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function normalizeAlarmList($payload): ?array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                $parts = array_filter(array_map('trim', explode(',', $payload)), fn ($value) => $value !== '');
                $payload = array_values($parts);
            }
        }

        if (!is_array($payload)) {
            return null;
        }

        $normalized = [];
        foreach ($payload as $code) {
            if (is_array($code)) {
                return null;
            }

            if ($code === '' || $code === null) {
                continue;
            }

            if (!is_numeric($code)) {
                return null;
            }

            $normalized[] = (int) $code;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
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

    private function getOrCreateAlarmGroup(array $codes): int
    {
        $hash = sha1(implode('-', $codes));

        $existing = DB::connection('mysql2')
            ->table('major_alarm_groups')
            ->where('codes_hash', $hash)
            ->first();

        if ($existing) {
            return (int) $existing->major_alarm_group_id;
        }

        return (int) DB::connection('mysql2')
            ->table('major_alarm_groups')
            ->insertGetId([
                'event_codes' => json_encode($codes),
                'codes_hash' => $hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function buildFailurePayload($globalDeviceId, $msn, string $message): array
    {
        return [
            'global_device_id' => $globalDeviceId,
            'msn' => $msn,
            'indv_status' => '0',
            'remarks' => $message,
        ];
    }
}
