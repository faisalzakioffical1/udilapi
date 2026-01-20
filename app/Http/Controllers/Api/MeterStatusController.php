<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterStatusController extends Controller
{
    /**
     * Update Meter Status for given meters.
     */
    public function update(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (!isset($formParams['meter_activation_status'])) {
            $response['message'] = 'Meter Activation Status variable is missing';
            return response()->json($response, $response['http_status']);
        }

        if (!filter_integer($formParams['meter_activation_status'], 0, 1)) {
            $response['message'] = 'Meter Activation Status is not valid Only 0 for Inactive or 1 for Active is acceptable';
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

        $slug = $formParams['slug'] ?? 'update_meter_status';
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

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WMTST');

            $newStatus = (int) $formParams['meter_activation_status'];
            $statusText = $newStatus === 1 ? 'Activated' : 'De-activated';

            update_meter($globalDeviceId, ['status' => $newStatus]);

            $visualsData = [
                'mtst_meter_activation_status' => $newStatus,
                'mtst_datetime' => $requestDatetime,
            ];

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Meter is ' . $statusText,
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
                'remarks' => 'Meter is ' . $statusText,
            ];
            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Meter Status have been updated for meters having individual status as 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
