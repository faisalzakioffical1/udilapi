<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WakeupSimController extends Controller
{

    public function update(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $form_params = $request->all();
        $response = [
            'status' => 1,
            'http_status' => 200,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (!isset($form_params['request_datetime']) || !is_date_valid($form_params['request_datetime'], 'Y-m-d H:i:s')) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Request Date is invalid';
            return response()->json($response, 400);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', $form_params['request_datetime'])->format('Y-m-d H:i:s');

        // Validation checks (per UDIL spec all three numbers are mandatory, 11 digits)
        if (!isset($form_params['wakeup_number_1']) || !isset($form_params['wakeup_number_2']) || !isset($form_params['wakeup_number_3'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'wakeup_number_1, wakeup_number_2 & wakeup_number_3 fields are required';
            return response()->json($response, $response['http_status']);
        }

        $numbers = [
            'wakeup_number_1' => $this->normalizeSim($form_params['wakeup_number_1']),
            'wakeup_number_2' => $this->normalizeSim($form_params['wakeup_number_2']),
            'wakeup_number_3' => $this->normalizeSim($form_params['wakeup_number_3']),
        ];

        if (!is_valid_sim_number($numbers['wakeup_number_1']) || !is_valid_sim_number($numbers['wakeup_number_2']) || !is_valid_sim_number($numbers['wakeup_number_3'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Only 11 digit wakeup numbers are allowed';
            return response()->json($response, $response['http_status']);
        }

        // Get devices from request (middleware ensures enriched structure)
        $all_devices = $form_params['global_device_id'] ?? [];
        if (empty($all_devices)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slug = $form_params['slug'] ?? 'update_wake_up_sim_number';

        foreach ($all_devices as $dvc) {
            $globalDeviceId = $dvc['global_device_id'] ?? '';
            $msn = $dvc['msn'] ?? 0;

            if ($msn == 0 || empty($globalDeviceId)) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists')
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
                    'remarks' => config('udil.meter_not_exists')
                ];
                continue;
            }

            $msn = $meter->msn;

            insert_transaction_status($globalDeviceId, $msn, $transaction, 'WSIM');

            $meterUpdateData = [
                'wakeup_no1' => $numbers['wakeup_number_1'],
                'wakeup_no2' => $numbers['wakeup_number_2'],
                'wakeup_no3' => $numbers['wakeup_number_3'],
                'set_wakeup_profile_id' => 1,
                'number_profile_group_id' => 1,
            ];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update($meterUpdateData);

            if (config('udil.update_meter_visuals_for_write_services')) {
                $visualsData = [
                    'wsim_wakeup_number_1' => $numbers['wakeup_number_1'],
                    'wsim_wakeup_number_2' => $numbers['wakeup_number_2'],
                    'wsim_wakeup_number_3' => $numbers['wakeup_number_3'],
                    'wsim_datetime' => $requestDatetime
                ];

                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update($visualsData);
            }

            insert_event(
                $msn,
                $globalDeviceId,
                307,
                'Wakeup SIM Programmed',
                $requestDatetime
            );

            update_on_transaction_success($slug, $globalDeviceId);

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $msn,
                'indv_status' => '1',
                'remarks' => 'Provided Wakeup Numbers will be programmed in meter upon communication',
            ];
        }

        $response['data'] = $datar;
        $response['message'] = 'Wakeup Numbers will be programmed in meters with indv_status = 1. Try Wakeup by Call for fast connectivity';

        return response()->json($response, $response['http_status']);
    }

    private function normalizeSim($value)
    {
        return trim(str_replace([' ', '-', '"', "'"], '', $value));
    }
}
