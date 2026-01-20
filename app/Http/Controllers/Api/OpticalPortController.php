<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpticalPortController extends Controller
{

    /**
     * Activate Meter Optical Port for given meters.
     */
    public function activate(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (!isset($formParams['optical_port_on_datetime'])) {
            $response['message'] = 'Optical Port ON Datetime is missing';
            return response()->json($response, $response['http_status']);
        }

        if (!isset($formParams['optical_port_off_datetime'])) {
            $response['message'] = 'Optical Port OFF Datetime is missing';
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

        if (function_exists('is_date_valid') && !is_date_valid($formParams['optical_port_on_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'optical_port_on_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        if (function_exists('is_date_valid') && !is_date_valid($formParams['optical_port_off_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'optical_port_off_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['request_datetime']))
            ->format('Y-m-d H:i:s');
        $opticalOnDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['optical_port_on_datetime']))
            ->format('Y-m-d H:i:s');
        $opticalOffDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['optical_port_off_datetime']))
            ->format('Y-m-d H:i:s');

        if (Carbon::createFromFormat('Y-m-d H:i:s', $opticalOffDatetime)->lessThanOrEqualTo(Carbon::createFromFormat('Y-m-d H:i:s', $opticalOnDatetime))) {
            $response['message'] = 'optical_port_off_datetime must be greater than optical_port_on_datetime';
            return response()->json($response, $response['http_status']);
        }

        $allDevices = $formParams['global_device_id'] ?? [];
        if (empty($allDevices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slug = $formParams['slug'] ?? 'activate_meter_optical_port';
        $successfulMeters = 0;

        foreach ($allDevices as $device) {
            $globalDeviceId = $device['global_device_id'] ?? '';

            if (empty($globalDeviceId)) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $device['msn'] ?? 0,
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
                    'msn' => $device['msn'] ?? 0,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WOPPO');

            $meterUpdate = [
                'update_optical_port_access' => 1,
                'optical_port_start_time' => $opticalOnDatetime,
                'optical_port_end_time' => $opticalOffDatetime,
            ];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdate);

            $udilLogData = [
                'msn' => $meterMsn,
                'global_device_id' => $globalDeviceId,
                'oppo_datetime' => $requestDatetime,
                'oppo_optical_port_on_datetime' => $opticalOnDatetime,
                'oppo_optical_port_off_datetime' => $opticalOffDatetime,
            ];

            if (config('udil.update_udil_log_for_write_services')) {
                DB::connection('mysql2')
                    ->table('udil_log')
                    ->where('global_device_id', $globalDeviceId)
                    ->update(['optical_port' => json_encode($udilLogData)]);
            }

            $visualsData = [
                'oppo_datetime' => $requestDatetime,
                'oppo_optical_port_on_datetime' => $opticalOnDatetime,
                'oppo_optical_port_off_datetime' => $opticalOffDatetime,
            ];

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Optical Port has been activated',
                ];

                $visualsData = array_merge($visualsData, [
                    'last_command' => $slug,
                    'last_command_datetime' => $requestDatetime,
                    'last_command_resp' => json_encode($deviceResponse),
                    'last_command_resp_datetime' => $requestDatetime,
                ]);
            }

            DB::connection('mysql2')
                ->table('meter_visuals')
                ->where('global_device_id', $globalDeviceId)
                ->update($visualsData);

            update_on_transaction_success($slug, $globalDeviceId);

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $meterMsn,
                'indv_status' => '1',
                'remarks' => 'Optical Port has been activated',
            ];

            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Optical Port has been activated for meters having individual status as 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
