<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoadSheddingController extends Controller
{

    /**
     * Program Load Shedding Schedule for given meters.
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

        if (!isset($formParams['start_datetime']) || !isset($formParams['end_datetime']) || !isset($formParams['load_shedding_slabs'])) {
            $response['message'] = 'start_datetime, end_datetime & load_shedding_slabs fields are mandatory and required';
            return response()->json($response, $response['http_status']);
        }

        if (!is_date_valid($formParams['start_datetime'], 'Y-m-d H:i:s') || !is_date_valid($formParams['end_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'Invalid dates are provided in start_datetime & end_datetime fields';
            return response()->json($response, $response['http_status']);
        }

        if (strtotime($formParams['start_datetime']) >= strtotime($formParams['end_datetime'])) {
            $response['message'] = 'end_datetime must be greater than start_datetime';
            return response()->json($response, $response['http_status']);
        }

        if (!validate_load_shedding_slabs($formParams['load_shedding_slabs'])) {
            $response['message'] = 'action_time OR relay_operate field is missing from load_shedding_slabs';
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

        $requestDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            trim($formParams['request_datetime'])
        )->format('Y-m-d H:i:s');

        $allDevices = $formParams['global_device_id'] ?? [];

        if (empty($allDevices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slabs = json_decode($formParams['load_shedding_slabs'], true);
        $slug = $formParams['slug'] ?? 'load_shedding_scheduling';
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

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WLSCH');

            $scheduleData = [
                'schedule_id' => null,
                'name' => 'udil parameterization of loadshedding',
                'activation_date' => $formParams['start_datetime'],
                'expiry_date' => $formParams['end_datetime'],
            ];

            $scheduleId = DB::connection('mysql2')
                ->table('load_shedding_schedule')
                ->insertGetId($scheduleData);

            foreach ($slabs as $slab) {
                DB::connection('mysql2')
                    ->table('load_shedding_detail')
                    ->insert([
                        'schedule_id' => $scheduleId,
                        'action_time' => $slab['action_time'],
                        'relay_operate' => $slab['relay_operate'],
                    ]);
            }

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update([
                    'load_shedding_schedule_id' => $scheduleId,
                    'write_load_shedding_schedule' => 1,
                ]);

            insert_event($meterMsn, $globalDeviceId, 304, 'Load Shedding Schedule Programmed', $requestDatetime);
            update_on_transaction_success($slug, $globalDeviceId);

            if (config('udil.update_udil_log_for_write_services')) {
                $udilData = [
                    'lsch' => json_encode([
                        'msn' => $meterMsn,
                        'global_device_id' => $globalDeviceId,
                        'lsch_datetime' => $requestDatetime,
                        'lsch_start_datetime' => $formParams['start_datetime'],
                        'lsch_end_datetime' => $formParams['end_datetime'],
                        'lsch_load_shedding_slabs' => $formParams['load_shedding_slabs'],
                    ]),
                ];

                DB::connection('mysql2')
                    ->table('udil_log')
                    ->where('global_device_id', $globalDeviceId)
                    ->update($udilData);
            }

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Load Shedding Schedule will be updated',
                ];

                $visualsData = [
                    'lsch_datetime' => $requestDatetime,
                    'lsch_start_datetime' => $formParams['start_datetime'],
                    'lsch_end_datetime' => $formParams['end_datetime'],
                    'lsch_load_shedding_slabs' => $formParams['load_shedding_slabs'],
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
                'remarks' => 'Load Shedding Schedule will be updated',
            ];
            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Load Shedding Schedule will be updated for meters having individual status as 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }


}
