<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpPortController extends Controller
{

    /**
     * Update IP and Port for given meters.
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

        if (!isset($formParams['primary_ip_address'])) {
            $response['message'] = 'primary_ip_address Field is missing';
            return response()->json($response, $response['http_status']);
        }

        if (!isset($formParams['secondary_ip_address'])) {
            $response['message'] = 'secondary_ip_address Field is missing';
            return response()->json($response, $response['http_status']);
        }

        if (!isset($formParams['primary_port'])) {
            $response['message'] = 'primary_port Field is missing';
            return response()->json($response, $response['http_status']);
        }

        if (!isset($formParams['secondary_port'])) {
            $response['message'] = 'secondary_port Field is missing';
            return response()->json($response, $response['http_status']);
        }

        if (
            !filter_integer($formParams['primary_port'], 1, 65535) ||
            !filter_integer($formParams['secondary_port'], 1, 65535) ||
            !filter_var($formParams['primary_ip_address'], FILTER_VALIDATE_IP) ||
            !filter_var($formParams['secondary_ip_address'], FILTER_VALIDATE_IP)
        ) {
            $response['message'] = 'Invalid IP OR Port Fields are provided. Please, correct them';
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

        $slug = $formParams['slug'] ?? 'update_ip_port';
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

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WIPPO');

            $ipProfileData = [
                'ip_profile_id' => null,
                'ip_1' => $formParams['primary_ip_address'],
                'ip_2' => $formParams['secondary_ip_address'],
                'w_tcp_port_1' => $formParams['primary_port'],
                'w_tcp_port_2' => $formParams['secondary_port'],
                'w_udp_port' => 28525,
                'h_tcp_port' => 26978,
                'h_udp_port' => 26988,
            ];

            $ipProfileId = DB::connection('mysql2')
                ->table('ip_profile')
                ->insertGetId($ipProfileData);

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update(['set_ip_profiles' => $ipProfileId]);

            $udilData = [
                'update_ip_port' => json_encode([
                    'msn' => $meterMsn,
                    'global_device_id' => $globalDeviceId,
                    'ippo_datetime' => $requestDatetime,
                    'ippo_primary_ip_address' => $formParams['primary_ip_address'],
                    'ippo_secondary_ip_address' => $formParams['secondary_ip_address'],
                    'ippo_primary_port' => $formParams['primary_port'],
                    'ippo_secondary_port' => $formParams['secondary_port'],
                ]),
            ];

            DB::connection('mysql2')
                ->table('udil_log')
                ->where('global_device_id', $globalDeviceId)
                ->update($udilData);

            insert_event($meterMsn, $globalDeviceId, 305, 'IP & Port Programmed', $requestDatetime);
            update_on_transaction_success($slug, $globalDeviceId);

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'New IP & Port will be programmed in meter upon connection',
                ];

                $visualsData = [
                    'ippo_datetime' => $requestDatetime,
                    'ippo_primary_ip_address' => $formParams['primary_ip_address'],
                    'ippo_secondary_ip_address' => $formParams['secondary_ip_address'],
                    'ippo_primary_port' => $formParams['primary_port'],
                    'ippo_secondary_port' => $formParams['secondary_port'],
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
                'remarks' => 'New IP & Port will be programmed in meter upon connection',
            ];
            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'New IP & Port will be programmed against meters having indv_status equal to 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
