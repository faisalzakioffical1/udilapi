<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SanctionedLoadController extends Controller
{

    public function program(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        // Validation checks
        $required_fields = [
            'load_limit' => 'load_limit Field is missing',
            'maximum_retries' => 'maximum_retries Field is missing',
            'retry_interval' => 'retry_interval Field is missing',
            'threshold_duration' => 'threshold_duration Field is missing',
            'retry_clear_interval' => 'retry_clear_interval Field is missing'
        ];

        foreach ($required_fields as $field => $message) {
            if (!isset($formParams[$field])) {
                $response['message'] = $message;
                return response()->json($response, $response['http_status']);
            }
        }

        // Numeric validation
        $numeric_fields = ['maximum_retries', 'retry_interval', 'retry_clear_interval', 'load_limit', 'threshold_duration'];
        foreach ($numeric_fields as $field) {
            if (!is_numeric($formParams[$field])) {
                $response['message'] = 'Only Numeric Fields are allowed';
                return response()->json($response, $response['http_status']);
            }
        }

        if (!isset($formParams['request_datetime'])) {
            $response['message'] = 'request_datetime field is required';
            return response()->json($response, $response['http_status']);
        }

        if (function_exists('is_date_valid') && !is_date_valid($formParams['request_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'request_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        $requestDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            trim($formParams['request_datetime'])
        )->format('Y-m-d H:i:s');

        // Get devices from request
        $allDevices = $formParams['global_device_id'] ?? [];

        if (empty($allDevices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slug = $formParams['slug'] ?? 'sanctioned_load_control';
        $successfulMeters = 0;

        foreach ($allDevices as $device) {
            $globalDeviceId = $device['global_device_id'] ?? '';
            $msn = $device['msn'] ?? 0;

            if ($msn == 0 || empty($globalDeviceId)) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meterMsn = $meter->msn;
            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WSANC');

            $contactorData = [
                'contactor_param_id' => null,
                'contactor_param_name' => 'udil parameterization',
                'retry_count' => $formParams['maximum_retries'],
                'retry_auto_interval_in_sec' => $formParams['retry_interval'],
                'on_retry_expire_auto_interval_min' => $formParams['retry_clear_interval'] / 60,
                'write_monitoring_time' => 1,
                'write_monitoring_time_t2' => 1,
                'write_monitoring_time_t3' => 1,
                'write_monitoring_time_t4' => 1,
                'monitering_time_over_load' => $formParams['threshold_duration'],
                'monitering_time_over_load_t2' => $formParams['threshold_duration'],
                'monitering_time_over_load_t3' => $formParams['threshold_duration'],
                'monitering_time_over_load_t4' => $formParams['threshold_duration'],
                'write_limit_over_load_total_kW_t1' => 1,
                'write_limit_over_load_total_kW_t2' => 1,
                'write_limit_over_load_total_kW_t3' => 1,
                'write_limit_over_load_total_kW_t4' => 1,
                'limit_over_load_total_kW_t1' => $formParams['load_limit'],
                'limit_over_load_total_kW_t2' => $formParams['load_limit'],
                'limit_over_load_total_kW_t3' => $formParams['load_limit'],
                'limit_over_load_total_kW_t4' => $formParams['load_limit'],
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
                ->where('global_device_id', $globalDeviceId)
                ->update([
                    'write_contactor_param' => 1,
                    'contactor_param_id' => $contactorId,
                ]);

            insert_event($meterMsn, $globalDeviceId, 303, 'Sanction Load Control Programmed', $requestDatetime);

            if (config('udil.update_udil_log_for_write_services')) {
                $udilData = [
                    'sanc_load_control' => json_encode([
                        'msn' => $meterMsn,
                        'global_device_id' => $globalDeviceId,
                        'sanc_datetime' => $requestDatetime,
                        'sanc_load_limit' => $formParams['load_limit'],
                        'sanc_maximum_retries' => $formParams['maximum_retries'],
                        'sanc_retry_interval' => $formParams['retry_interval'],
                        'sanc_threshold_duration' => $formParams['threshold_duration'],
                        'sanc_retry_clear_interval' => $formParams['retry_clear_interval'],
                    ]),
                ];

                DB::connection('mysql2')
                    ->table('udil_log')
                    ->where('global_device_id', $globalDeviceId)
                    ->update($udilData);
            }

            update_on_transaction_success($slug, $globalDeviceId);

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Sanctioned Load Control function will be programmed in meter upon connection',
                ];

                $visualsData = [
                    'sanc_datetime' => $requestDatetime,
                    'sanc_load_limit' => $formParams['load_limit'],
                    'sanc_maximum_retries' => $formParams['maximum_retries'],
                    'sanc_retry_interval' => $formParams['retry_interval'],
                    'sanc_threshold_duration' => $formParams['threshold_duration'],
                    'sanc_retry_clear_interval' => $formParams['retry_clear_interval'],
                    'last_command' => $slug,
                    'last_command_datetime' => $requestDatetime,
                    'last_command_resp' => json_encode($deviceResponse),
                    'last_command_resp_datetime' => $requestDatetime,
                ];

                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update($visualsData);
            }

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $meterMsn,
                'indv_status' => '1',
                'remarks' => 'Sanctioned Load Control function will be programmed in meter upon connection',
            ];
            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Sanctioned Load Control function will be programmed against meters having indv_status equal to 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
