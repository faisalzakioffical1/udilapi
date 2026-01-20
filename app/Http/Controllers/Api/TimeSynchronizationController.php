<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeSynchronizationController extends Controller
{

    public function synchronize(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $form_params = $request->all();
        $response = [
            'status' => 1,
            'http_status' => 200,
            'transactionid' => $transaction,
        ];
        $datar = [];

        // Validation checks
        if (!isset($form_params['request_datetime'])) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'request_datetime field is required'
            ], 400);
        }

        if (function_exists('is_date_valid') && !is_date_valid($form_params['request_datetime'], 'Y-m-d H:i:s')) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'request_datetime must be in Y-m-d H:i:s format'
            ], 400);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($form_params['request_datetime']))->format('Y-m-d H:i:s');
        $baseTime = Carbon::createFromFormat('Y-m-d H:i:s', $requestDatetime)
            ->subMinutes(15)
            ->format('Y-m-d H:i:s');

        // Get devices from request
        $all_devices = $form_params['global_device_id'] ?? [];

        if (empty($all_devices)) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'Global Device ID is required'
            ], 400);
        }

        $slug = $form_params['slug'] ?? 'time_synchronization';

        $successfulMeters = 0;

        foreach ($all_devices as $dvc) {
            $globalDeviceId = $dvc['global_device_id'] ?? '';
            $msn = $dvc['msn'] ?? 0;

            if ($msn == 0 || empty($globalDeviceId)) {
                $datar[] = [
                    "global_device_id" => $globalDeviceId,
                    "msn" => $msn,
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
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => config('udil.meter_not_exists')
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, "WDVTM");

            $meterUpdateData = [
                'max_cs_difference' => 999999999,
                'super_immediate_cs' => 1,
                'base_time_cs' => $baseTime,
            ];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdateData);

            insert_event(
                $meterMsn,
                $globalDeviceId,
                201,
                'Time Synchronization',
                $requestDatetime
            );

            update_on_transaction_success($slug, $globalDeviceId);

            $datar[] = [
                "global_device_id" => $globalDeviceId,
                "msn" => $meterMsn,
                "indv_status" => "1",
                "remarks" => "Time will be synced Soon",
            ];

            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = $successfulMeters > 0
            ? 'Time will be synchronized against meters having indv_status equal to 1. Make sure MDC has correct time & timezone settings'
            : 'No meters accepted the time synchronization request';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
