<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuxRelayController extends Controller
{
    public function operateRelay(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $form_params = $request->all();
        $slug = 'aux_relay_operations';
        $datar = [];
        $response = [
            'status' => 1,
            'http_status' => 200,
            'transactionid' => $transaction,
        ];

        // Validate transaction & devices
        // $resp = applyCommonValidation($slug, $headers, $form_params);
        // if ($resp['status'] == 0) {
        //     return response()->json($resp, 400);
        // }

        // Parse devices
        $all_devices = $form_params['global_device_id'] ?? [];

        if (empty($all_devices)) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'Global Device ID is required'
            ], 400);
        }

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

        // Validate relay_operate
        if (!isset($form_params['relay_operate'])) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'relay_operate field is required'
            ], 400);
        } elseif (!filterInteger($form_params['relay_operate'], 0, 1)) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'Invalid value provided in relay_operate Field'
            ], 400);
        }

        $requestDatetime = Carbon::createFromFormat('Y-m-d H:i:s', trim($form_params['request_datetime']))->format('Y-m-d H:i:s');
        $relayOperate = (int) trim((string) $form_params['relay_operate'], "\"' ");

        $successfulMeters = 0;

        if (!empty($all_devices)) {
            foreach ($all_devices as $dvc) {
                $globalDeviceId = $dvc['global_device_id'] ?? '';
                $msn = $dvc['msn'] ?? 0;

                if (empty($globalDeviceId)) {
                    $datar[] = [
                        "global_device_id" => $globalDeviceId,
                        "msn" => $msn,
                        "indv_status" => "0",
                        "remarks" => config('udil.meter_not_exists', 'Meter not found'),
                    ];
                    continue;
                } else {
                    $meter = DB::connection('mysql2')
                        ->table('meter')
                        ->where('global_device_id', $globalDeviceId)
                        ->first();

                    if (!$meter) {
                        $datar[] = [
                            "global_device_id" => $globalDeviceId,
                            "msn" => $msn,
                            "indv_status" => "0",
                            "remarks" => config('udil.meter_not_exists', 'Meter not found'),
                        ];
                        continue;
                    }

                    $msn = $meter->msn;

                    $curr_time = date('H:i:s');
                    //$curr_time = "23:58:00";
                    $special_treatment_type = 10; // 10 for Normal, 20 for Parameter Cancel and reprogram LSCH
                    $load_shedding_schedule_exists = true;
                    $load_shedding_schedule_list = [];
                    $load_shedding_schedule_id = $meter->load_shedding_schedule_id ?? null;

                    if ($load_shedding_schedule_id === null || (int) $load_shedding_schedule_id === 105) {
                        $load_shedding_schedule_exists = false;
                    } else {
                        $load_shedding_schedule_list = DB::connection('mysql2')->table('load_shedding_detail')
                            ->where('schedule_id', $load_shedding_schedule_id)
                            ->orderBy('action_time', 'ASC')
                            ->get(['action_time', 'relay_operate'])->toArray();

                        if (empty($load_shedding_schedule_list)) {
                            $load_shedding_schedule_exists = false;
                        }
                    }

                    $current_time_slab = null;
                    $next_activation_time = null;

                    if ($load_shedding_schedule_exists) {
                        for ($i = 0; $i < count($load_shedding_schedule_list); $i++) {
                            if ($curr_time > $load_shedding_schedule_list[$i]->action_time) {
                                $current_time_slab = $i;
                            }
                        }

                        if (is_null($current_time_slab)) {
                            $current_time_slab = count($load_shedding_schedule_list) - 1;
                        }

                        if ($current_time_slab == count($load_shedding_schedule_list) - 1) {
                            if ($curr_time > $load_shedding_schedule_list[$current_time_slab]->action_time) {
                                $next_activation_time = date('Y-m-d', strtotime('+1 day')) . " " . $load_shedding_schedule_list[0]->action_time;
                            } else {
                                $next_activation_time = date('Y-m-d') . " " . $load_shedding_schedule_list[0]->action_time;
                            }
                        } else {
                            $next_activation_time = date('Y-m-d') . " " . $load_shedding_schedule_list[$current_time_slab + 1]->action_time;
                        }

                        $load_shedding_relay_status = $load_shedding_schedule_list[$current_time_slab]->relay_operate;

                        if (($relayOperate === 0 && $load_shedding_relay_status == 1) ||
                            ($relayOperate === 1 && $load_shedding_relay_status == 0)) {
                            $special_treatment_type = 20;
                        }
                    }

                    if ($special_treatment_type == 10) {
                        insert_transaction_status($globalDeviceId, $msn, $transaction, "WAUXR");

                        $datau = [
                            'apply_new_contactor_state' => 1,
                            'new_contactor_state' => $relayOperate
                        ];

                        DB::connection('mysql2')->table('meter')
                            ->where('global_device_id', $globalDeviceId)
                            ->update($datau);

                        // Keepalive logic
                        if ($relayOperate === 1) {
                            $keepAlive = 0; $meter_class = 'non-keepalive';
                            $sch_pq = 2; $sch_cb = 1; $sch_mb = 2; $sch_ev = 2;
                            $sch_lp = 2; $sch_lp2 = 2; $sch_lp3 = 2; $save_sch_pq = 2;
                            $sch_ss = 2; $sch_cs = 2; $kas = '00:01:00'; $set_keepalive = 2;
                            $interval_pq = '00:15:00'; $interval_cb = '00:01:00';
                            $interval_mb = '00:01:00'; $interval_ev = '00:01:00';
                            $interval_lp = '00:01:00'; $interval_cs = '00:01:00';
                        } else {
                            $keepAlive = 1; $meter_class = 'keepalive';
                            $sch_pq = 3; $sch_cb = 1; $sch_mb = 3; $sch_ev = 3;
                            $sch_lp = 3; $sch_lp2 = 3; $sch_lp3 = 3; $save_sch_pq = 2;
                            $sch_ss = 2; $sch_cs = 3; $kas = '00:00:10'; $set_keepalive = 1;
                            $interval_pq = '00:15:00'; $interval_cb = '00:15:00';
                            $interval_mb = '00:15:00'; $interval_ev = '00:05:00';
                            $interval_lp = '00:15:00'; $interval_cs = '00:15:00';
                        }

                        $datae = [
                            'class' => $meter_class,
                            'type' => $keepAlive,
                            'sch_pq' => $sch_pq, 'interval_pq' => $interval_pq,
                            'sch_cb' => $sch_cb, 'interval_cb' => $interval_cb,
                            'sch_mb' => $sch_mb, 'interval_mb' => $interval_mb,
                            'sch_ev' => $sch_ev, 'interval_ev' => $interval_ev,
                            'sch_lp' => $sch_lp, 'sch_lp2' => $sch_lp2, 'sch_lp3' => $sch_lp3,
                            'interval_lp' => $interval_lp, 'kas_interval' => $kas,
                            'save_sch_pq' => $save_sch_pq, 'save_interval_pq' => $interval_pq,
                            'sch_ss' => $sch_ss, 'sch_cs' => $sch_cs, 'interval_cs' => $interval_cs,
                            'set_keepalive' => $set_keepalive,
                            'super_immediate_pq' => '0',
                        ];

                        DB::connection('mysql2')->table('meter')
                            ->where('global_device_id', $globalDeviceId)
                            ->update($datae);

                    } elseif ($special_treatment_type == 20) {
                        $datau = [
                            'load_shedding_schedule_id' => 105,
                            'write_load_shedding_schedule' => 1,
                        ];
                        DB::connection('mysql2')->table('meter')
                            ->where('global_device_id', $globalDeviceId)
                            ->update($datau);

                        sleep(41);

                        insert_transaction_status($globalDeviceId, $msn, $transaction, "WAUXR");

                        $datau = [
                            'apply_new_contactor_state' => 1,
                            'new_contactor_state' => $relayOperate
                        ];
                        DB::connection('mysql2')->table('meter')
                            ->where('global_device_id', $globalDeviceId)
                            ->update($datau);

                        $cc = date('YmdHis');
                        $time_replace = str_replace(['-', ':', ' '], '', $next_activation_time);
                        $event_query = "
                            CREATE
                            EVENT `LSCH_" . $globalDeviceId . "_" . $time_replace . "_" . $cc . "`
                            ON SCHEDULE AT '" . $next_activation_time . "'
                            DO
                            BEGIN
                                UPDATE meter SET load_shedding_schedule_id = " . $load_shedding_schedule_id . ", write_load_shedding_schedule = 1
                                WHERE global_device_id = '" . $globalDeviceId . "';
                            END
                        ";
                        DB::connection('mysql2')->unprepared($event_query);
                    }

                    if (config('udil.update_meter_visuals_for_write_services')) {
                        DB::connection('mysql2')->table('meter_visuals')
                            ->where('global_device_id', $globalDeviceId)
                            ->update([
                                'auxr_status' => $relayOperate,
                                'auxr_datetime' => $requestDatetime
                            ]);
                    }

                    $eventCode = $relayOperate === 1 ? 202 : 203;
                    $eventMessage = $relayOperate === 1 ? 'Contactor ON' : 'Contactor OFF';

                    insert_event(
                        $msn,
                        $globalDeviceId,
                        $eventCode,
                        $eventMessage,
                        $requestDatetime
                    );

                    update_on_transaction_success($slug, $globalDeviceId);

                    $rr = ($relayOperate === 0) ? 'OFF' : 'ON';
                    $datar[] = [
                        "global_device_id" => $globalDeviceId,
                        "msn" => $msn,
                        "indv_status" => "1",
                        "remarks" => "Relay will be Turned $rr Soon",
                    ];
                    $successfulMeters++;
                }
            }

            $response['status'] = $successfulMeters > 0 ? 1 : 0;
            $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
            $response['message'] = $successfulMeters > 0
                ? 'Relay will be turned ON or OFF against meters having indv_status equal to 1'
                : 'No meters accepted the aux relay request';
            $response['data'] = $datar;
        }

        return response()->json($response, $response['http_status']);
    }
}
