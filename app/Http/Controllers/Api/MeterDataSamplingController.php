<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterDataSamplingController extends Controller
{

    /**
     * Meter Data Sampling for given meters.
     */
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

        $typesValid = ['INST', 'BILL', 'LPRO'];

        if (!isset($formParams['activation_datetime']) || !isset($formParams['data_type']) || !isset($formParams['sampling_interval']) || !isset($formParams['sampling_initial_time'])) {
            $response['message'] = 'Mandatory Input Fields are missing';
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

        if (function_exists('is_date_valid') && !is_date_valid($formParams['activation_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'activation_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        if (!is_time_valid($formParams['sampling_initial_time'])) {
            $response['message'] = 'sampling_initial_time must be in H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        $dataType = strtoupper($formParams['data_type']);
        if (!in_array($dataType, $typesValid)) {
            $response['message'] = 'Field data_type has invalid value. Only these fields are applicable: ' . implode(' ', $typesValid);
            return response()->json($response, $response['http_status']);
        }

        if (!filterInteger($formParams['sampling_interval'], 1, 1440)) {
            $response['message'] = 'Provided Sampling Interval value is not in valid range i.e. 1 to 1440 minutes';
            return response()->json($response, $response['http_status']);
        }

        $allDevices = $formParams['global_device_id'] ?? [];
        if (empty($allDevices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['request_datetime']))
            ->format('Y-m-d H:i:s');
        $activationDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($formParams['activation_datetime']))
            ->format('Y-m-d H:i:s');
        $samplingInitialTime = Carbon::createFromFormat('H:i:s', trim($formParams['sampling_initial_time']))
            ->format('H:i:s');
        $samplingInterval = (int) $formParams['sampling_interval'];
        $slug = $formParams['slug'] ?? 'meter_data_sampling';
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

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WMDSM');

            if ($dataType === 'INST') {
                $meterUpdate = [
                    'lp2_write_interval_request' => 1,
                    'lp2_write_interval' => $samplingInterval,
                    'lp2_interval_activation_datetime' => $activationDatetime,
                    'lp2_interval_initial_time' => $samplingInitialTime,
                ];
            } elseif ($dataType === 'BILL') {
                $meterUpdate = [
                    'lp3_write_interval_request' => 1,
                    'lp3_write_interval' => $samplingInterval,
                    'lp3_interval_activation_datetime' => $activationDatetime,
                    'lp3_interval_initial_time' => $samplingInitialTime,
                ];
            } else {
                $meterUpdate = [
                    'lp_write_interval_request' => 1,
                    'lp_write_interval' => $samplingInterval,
                    'lp_interval_activation_datetime' => $activationDatetime,
                    'lp_interval_initial_time' => $samplingInitialTime,
                ];
            }

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdate);

            $visualsData = [
                'mdsm_datetime' => $requestDatetime,
            ];

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Sampling interval of meter will be changed',
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
                'remarks' => 'Sampling interval of meter will be changed',
            ];

            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Sampling interval of meters with individual status as 1 will be changed accordingly';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

}
