<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParameterizationCancellationController extends Controller
{
    private const SUPPORTED_TYPES = ['SANC', 'LSCH', 'TIOU', 'OVFC', 'UVFC', 'OCFC', 'OLFC', 'VUFC', 'PFFC', 'CUFC', 'HAPF'];
    private const IMPLEMENTED_TYPES = ['SANC', 'LSCH', 'TIOU'];

    /**
     * Parameterization Cancellation for given meters.
     */
    public function cancel(Request $request)
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

        if (!isset($formParams['type']) || trim((string) $formParams['type']) === '') {
            $response['message'] = 'Type field is required';
            return response()->json($response, 400);
        }

        $type = strtoupper($formParams['type']);
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', self::SUPPORTED_TYPES);
            return response()->json($response, 400);
        }

        if (!in_array($type, self::IMPLEMENTED_TYPES, true)) {
            $response['message'] = 'Type field ' . $type . ' is Valid but is NOT Implemented';
            return response()->json($response, 400);
        }

        if (!array_key_exists('global_device_id', $formParams)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, 400);
        }

        $devices = $this->normalizeDeviceList($formParams['global_device_id']);
        if (!is_array($devices)) {
            $response['message'] = 'global_device_id must be a JSON array of devices';
            return response()->json($response, 400);
        }

        if (empty($devices)) {
            $response['message'] = 'No devices provided';
            return response()->json($response, 400);
        }

        $now = now()->format('Y-m-d H:i:s');
        $slug = $formParams['slug'] ?? 'parameterization_cancellation';
        $successfulMeters = 0;

        foreach ($devices as $deviceEntry) {
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
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $device['msn'] ?? 0,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $result = $this->processCancellation($meter, $type, $transaction, $now, $slug);

            if ($result['indv_status'] === '1') {
                $successfulMeters++;
            }

            $datar[] = $result;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = $successfulMeters > 0
            ? 'Parameters will be set to default in meter with indv_status = 1'
            : 'All requested meters failed to process parameterization cancellation';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
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

    private function processCancellation($meter, string $type, string $transaction, string $now, string $slug): array
    {
        return match ($type) {
            'SANC' => $this->resetSanctionedLoad($meter, $transaction, $now, $slug),
            'LSCH' => $this->resetLoadShedding($meter, $transaction, $now, $slug),
            'TIOU' => $this->resetTimeOfUse($meter, $transaction, $now, $slug),
            default => [
                'global_device_id' => $meter->global_device_id,
                'msn' => $meter->msn,
                'indv_status' => '0',
                'remarks' => 'Type not implemented',
            ],
        };
    }

    private function resetSanctionedLoad($meter, string $transaction, string $now, string $slug): array
    {
        insert_transaction_status($meter->global_device_id, $meter->msn, $transaction, 'WSANC');

        $contactorData = [
            'contactor_param_id' => null,
            'contactor_param_name' => 'udil parameterization',
            'retry_count' => 3,
            'retry_auto_interval_in_sec' => 300,
            'on_retry_expire_auto_interval_min' => 1,
            'write_monitoring_time' => 1,
            'write_monitoring_time_t2' => 1,
            'write_monitoring_time_t3' => 1,
            'write_monitoring_time_t4' => 1,
            'monitering_time_over_load' => 180,
            'monitering_time_over_load_t2' => 180,
            'monitering_time_over_load_t3' => 180,
            'monitering_time_over_load_t4' => 180,
            'write_limit_over_load_total_kW_t1' => 1,
            'write_limit_over_load_total_kW_t2' => 1,
            'write_limit_over_load_total_kW_t3' => 1,
            'write_limit_over_load_total_kW_t4' => 1,
            'limit_over_load_total_kW_t1' => 69,
            'limit_over_load_total_kW_t2' => 69,
            'limit_over_load_total_kW_t3' => 69,
            'limit_over_load_total_kW_t4' => 69,
            'contactor_on_pulse_time_ms' => 100,
            'contactor_off_pulse_time_ms' => 100,
            'interval_btw_contactor_state_change_sec' => 7,
            'power_up_delay_to_change_state_sec' => 15,
            'interval_to_contactor_failure_status_sec' => 300,
            'optically_connect' => 0,
            'optically_disconnect' => 0,
            'tariff_change' => 0,
            'is_retry_automatic_or_switch' => 1,
            'reconnect_by_switch_on_expire' => 0,
            'reconnect_automatic_on_expire' => 0,
            'turn_contactor_off_overload_t1' => 1,
            'tunr_contactor_off_overload_t2' => 1,
            'turn_contactor_off_overload_t3' => 0,
            'turn_contactor_off_overload_t4' => 0,
            'write_contactor_param' => 1,
        ];

        $contactorId = DB::connection('mysql2')
            ->table('contactor_params')
            ->insertGetId($contactorData);

        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $meter->global_device_id)
            ->update([
                'write_contactor_param' => 1,
                'contactor_param_id' => $contactorId,
            ]);

        insert_event(
            $meter->msn,
            $meter->global_device_id,
            324,
            'Sanction Load Control Cancelled',
            $now
        );

        update_on_transaction_success($slug, $meter->global_device_id);

        return [
            'global_device_id' => $meter->global_device_id,
            'msn' => $meter->msn,
            'indv_status' => '1',
            'remarks' => 'Sanctioned Load Control function will be reset to default in Meter upon Connection',
        ];
    }

    private function resetLoadShedding($meter, string $transaction, string $now, string $slug): array
    {
        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $meter->global_device_id)
            ->update([
                'load_shedding_schedule_id' => 105,
                'write_load_shedding_schedule' => 1,
            ]);

        insert_transaction_status($meter->global_device_id, $meter->msn, $transaction, 'WLSCH');

        insert_event(
            $meter->msn,
            $meter->global_device_id,
            325,
            'Load Shedding Schedule Cancelled',
            $now
        );

        update_on_transaction_success($slug, $meter->global_device_id);

        return [
            'global_device_id' => $meter->global_device_id,
            'msn' => $meter->msn,
            'indv_status' => '1',
            'remarks' => 'Loadshedding Scheduling function will be reset to default in Meter upon Connection',
        ];
    }

    private function resetTimeOfUse($meter, string $transaction, string $now, string $slug): array
    {
        $calendarId = $this->ensureDefaultActivityCalendar();

        if (!$calendarId) {
            return [
                'global_device_id' => $meter->global_device_id,
                'msn' => $meter->msn,
                'indv_status' => '0',
                'remarks' => 'Unable to locate default activity calendar for cancellation',
            ];
        }

        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $meter->global_device_id)
            ->update(['activity_calendar_id' => $calendarId]);

        insert_transaction_status($meter->global_device_id, $meter->msn, $transaction, 'WTIOU');

        insert_event(
            $meter->msn,
            $meter->global_device_id,
            326,
            'Time of use Programmed Cancelled',
            $now
        );

        update_on_transaction_success($slug, $meter->global_device_id);

        return [
            'global_device_id' => $meter->global_device_id,
            'msn' => $meter->msn,
            'indv_status' => '1',
            'remarks' => 'Time of Use function will be reset to default in Meter upon Connection',
        ];
    }

    private function ensureDefaultActivityCalendar(): ?int
    {
        $existingId = DB::connection('mysql2')
            ->table('param_activity_calendar')
            ->where('description', 'udil_parameter_cancel_default')
            ->value('pk_id');

        if ($existingId) {
            return (int) $existingId;
        }

        $calendarId = insert_data('param_activity_calendar', [
            'pk_id' => null,
            'description' => 'udil_parameter_cancel_default',
            'activation_date' => '2019-01-01 00:00:00',
        ]);

        if (!$calendarId) {
            return null;
        }

        $tariffs = [
            ['17:00', '21:00'],
            ['18:00', '22:00'],
            ['19:00', '23:00'],
            ['18:00', '22:00'],
        ];

        $seasons = ['2024-12-01', '2024-03-01', '2024-06-01', '2024-12-09'];

        for ($d = 0; $d < 4; $d++) {
            insert_data('param_day_profile', [
                'pk_id' => null,
                'calendar_id' => $calendarId,
                'day_profile_id' => $d + 1,
                'day_profile_name' => 'd' . ($d + 1),
            ]);

            foreach ($tariffs[$d] as $index => $switch) {
                insert_data('param_day_profile_slots', [
                    'pk_id' => null,
                    'calendar_id' => $calendarId,
                    'day_profile_id' => $d + 1,
                    'switch_time' => $switch,
                    'tariff' => $index + 1,
                ]);
            }
        }

        for ($w = 1; $w <= 4; $w++) {
            insert_data('param_week_profile', [
                'pk_id' => null,
                'calendar_id' => $calendarId,
                'week_profile_id' => $w,
                'day1_profile_id' => $w,
                'day2_profile_id' => $w,
                'day3_profile_id' => $w,
                'day4_profile_id' => $w,
                'day5_profile_id' => $w,
                'day6_profile_id' => $w,
                'day7_profile_id' => $w,
            ]);

            insert_data('param_season_profile', [
                'pk_id' => null,
                'calendar_id' => $calendarId,
                'week_profile_id' => $w,
                'start_date' => $seasons[$w - 1],
            ]);
        }

        return (int) $calendarId;
    }
}
