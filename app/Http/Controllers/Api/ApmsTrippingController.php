<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApmsTrippingController extends Controller
{

    public function program(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $form_params = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        // Convert type to lowercase if set
        if (isset($form_params['type'])) {
            $form_params['type'] = strtolower($form_params['type']);
        }

        $supported_apms = array('ovfc', 'uvfc', 'ocfc', 'olfc', 'vufc', 'pffc', 'cufc', 'hapf');

        // Validation checks
        $required_fields = [
            'type',
            'critical_event_threshold_limit',
            'critical_event_log_time',
            'tripping_event_threshold_limit',
            'tripping_event_log_time',
            'enable_tripping',
        ];

        foreach ($required_fields as $field) {
            if (!isset($form_params[$field])) {
                $response['status'] = 0;
                $response['http_status'] = 400;
                $response['message'] = 'One or more mandatory fields are missing';
                return response()->json($response, $response['http_status']);
            }
        }

        if (!isset($form_params['request_datetime'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'request_datetime field is required';
            return response()->json($response, $response['http_status']);
        }

        if (function_exists('is_date_valid') && !is_date_valid($form_params['request_datetime'], 'Y-m-d H:i:s')) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'request_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        if (!in_array(($form_params['type']), $supported_apms)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', $supported_apms);
            return response()->json($response, $response['http_status']);
        } else if (!filterInteger($form_params['enable_tripping'], 0, 1)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Field enable_tripping can only have values 0-1';
            return response()->json($response, $response['http_status']);
        } else if (!filterInteger($form_params['critical_event_log_time'], 0, 86399)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'critical_event_log_time can only have values between 0 - 86399';
            return response()->json($response, $response['http_status']);
        } else if (!filterInteger($form_params['tripping_event_log_time'], 0, 86399)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'tripping_event_log_time can only have values between 0 - 86399';
            return response()->json($response, $response['http_status']);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($form_params['request_datetime']))
            ->format('Y-m-d H:i:s');

        // Get devices from request
        $all_devices = $form_params['global_device_id'] ?? [];

        if (empty($all_devices)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slug = $form_params['slug'] ?? 'apms_tripping_events';
        $successfulMeters = 0;

        foreach ($all_devices as $dvc) {
            $globalDeviceId = $dvc['global_device_id'] ?? '';

            if (empty($globalDeviceId)) {
                $datar[] = [
                    "global_device_id" => $globalDeviceId,
                    "msn" => $dvc['msn'] ?? 0,
                    "indv_status" => "0",
                    "remarks" => config('udil.meter_not_exists')
                ];
                continue;
            }

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = [
                    "global_device_id" => $globalDeviceId,
                    "msn" => $dvc['msn'] ?? 0,
                    "indv_status" => "0",
                    "remarks" => config('udil.meter_not_exists')
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            $temp_type = "W" . strtoupper($form_params['type']);
            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, $temp_type);

            // Prepare APMS tripping event data
            $apmsData = [
                'type' => $form_params['type'],
                'critical_event_threshold_limit' => $form_params['critical_event_threshold_limit'],
                'critical_event_log_time' => gmdate("H:i:s", (int) $form_params['critical_event_log_time']),
                'tripping_event_threshold_limit' => $form_params['tripping_event_threshold_limit'],
                'tripping_event_log_time' => gmdate("H:i:s", (int) $form_params['tripping_event_log_time']),
                'enable_tripping' => (int) $form_params['enable_tripping'],
                'created_at' => $requestDatetime,
                'updated_at' => $requestDatetime,
            ];

            $apmsEventId = DB::connection('mysql2')
                ->table('param_apms_tripping_events')
                ->insertGetId($apmsData);

            // Update meter with APMS event reference
            $meterUpdateData = ['write_' . $form_params['type'] => $apmsEventId];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdateData);

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'APMS Threshold Limits will be programmed in meter upon communication',
                ];

                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update([
                        'last_command' => $slug,
                        'last_command_datetime' => $requestDatetime,
                        'last_command_resp' => json_encode($deviceResponse),
                        'last_command_resp_datetime' => $requestDatetime,
                    ]);
            }

            update_on_transaction_success($slug, $globalDeviceId, $requestDatetime);

            $datar[] = [
                "global_device_id" => $globalDeviceId,
                "msn" => $meterMsn,
                "indv_status" => "1",
                "remarks" => "APMS Threshold Limits will be programmed in meter upon communication",
            ];

            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'APMS Threshold Limits will be programmed in meters with indv_status = 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

}
