<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceCreationController extends Controller
{
    private $validatedDevices = [];

    /**
     * Device Creation for given meters.
     */
    public function create(Request $request)
    {
        $transaction = $request->header('transactionid');
        $form_params = $request->all();
        $response = [
            'status' => 1,
            'http_status' => 200,
            'transactionid' => $transaction,
        ];
        $datar = [];

        // 1. Validate Request
        $validationResponse = $this->validateRequest($form_params, $transaction);
        if ($validationResponse !== null) {
            return response()->json($validationResponse, $validationResponse['http_status']);
        }

        $devices = !empty($this->validatedDevices)
            ? $this->validatedDevices
            : $this->decodeDeviceIdentity($form_params['device_identity'] ?? null);
        $now = now()->format('Y-m-d H:i:s');
        $slug = $form_params['slug'] ?? 'device_creation';

        // Sanitize meter_type
        if (isset($form_params['meter_type'])) {
            $replace_str = array('"', "'", ",");
            $form_params['meter_type'] = str_replace($replace_str, '', $form_params['meter_type']);
        }

        // 2. Validate Devices Array Structure
        foreach ($devices as $device) {
            if (!isset($device['dsn']) || !isset($device['global_device_id']) || $device['global_device_id'] == '' || $device['dsn'] == '') {
                return response()->json([
                    'status' => 0,
                    'http_status' => 400,
                    'transactionid' => $transaction,
                    'message' => 'Either DSN or Global Device ID index is missing'
                ], 400);
            }
        }

        // 3. Process Each Device
        foreach ($devices as $device) {
            $gdid = $device['global_device_id'];
            $msn = $device['dsn'];

            insert_transaction_status($gdid, $msn, $transaction, "WDVCR");

            // Device Specific Validation
            $deviceError = $this->validateDeviceSpecifics($msn, $form_params, $gdid);
            if ($deviceError !== null) {
                $datar[] = $deviceError;
                continue;
            }

            // Check for existing MSN with different GDID
            $existingCheck = $this->checkExistingMsn($msn, $gdid);
            if ($existingCheck) {
                $datar[] = $existingCheck;
                continue;
            }

            // Process Device Creation/Update
            $datar[] = $this->processDevice($device, $form_params, $transaction, $now, $slug);
        }

        $successCount = count(array_filter($datar, function ($item) {
            return isset($item['indv_status']) && $item['indv_status'] === '1';
        }));
        $failureCount = count($datar) - $successCount;

        $response['data'] = $datar;

        if ($successCount === 0) {
            $response['status'] = 0;
            $response['message'] = 'No devices were created. Check data array for validation errors.';
        } elseif ($failureCount > 0) {
            $response['message'] = 'Some devices failed validation. See data array for details.';
        } else {
            $response['message'] = 'Devices having indv_status equal to 1 are Created Successfully.';
        }

        return response()->json($response, $response['http_status']);
    }

    private function validateRequest($form_params, $transaction)
    {
        $transactionId = $transaction ?? '0';
        $device_creation_error_status = validate_device_creation_params($form_params);

        if ($device_creation_error_status != "") {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => $device_creation_error_status,
                'debug' => $form_params
            ];
        }

        $deviceIdentityValidation = validate_device_identity_list($form_params['device_identity'] ?? null);
        if ($deviceIdentityValidation['error']) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => $deviceIdentityValidation['error'],
            ];
        }

        $this->validatedDevices = $deviceIdentityValidation['devices'];

        if (!filter_integer($form_params['communication_mode'], 1, 5)) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => "Invalid value for field 'communication_mode'. Acceptable Values are 1 - 5"
            ];
        }

        if (!filter_integer($form_params['communication_type'], 1, 2)) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => "Invalid value for field 'communication_type'. Acceptable Values are 1 - 2"
            ];
        }

        if (!filter_integer($form_params['bidirectional_device'], 0, 1)) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => "Invalid value for field 'bidirectional_device'. Acceptable Values are 0 - 1"
            ];
        }

        if (!is_time_valid($form_params['mdi_reset_time'])) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => "Invalid value for field 'mdi_reset_time'. Expected format HH:mm:ss"
            ];
        }

        if (!is_time_valid($form_params['initial_communication_time'])) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => "Invalid value for field 'initial_communication_time'. Expected format HH:mm:ss"
            ];
        }

        $interval = (int)trim($form_params['communication_interval'], "\"' ");
        $comType = (int)trim($form_params['communication_type'], "\"' ");

        if ($comType === 2 && $interval !== 0) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => 'Communication interval must be 0 for keep-alive devices'
            ];
        }

        if ($comType === 1 && $interval <= 0) {
            return [
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transactionId,
                'message' => 'Communication interval must be greater than 0 for non keep-alive devices'
            ];
        }

        return null;
    }

    private function decodeDeviceIdentity($payload)
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : null;
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function validateDeviceSpecifics($msn, $form_params, $gdid)
    {
        $replace_str = array('"', "'", ",");
        $msn_initial = str_replace($replace_str, '', substr($msn, 0, 4));
        $meter_type = str_replace($replace_str, '', $form_params['meter_type']);
        $phase = $form_params['phase'];

        $errorRemark = null;

        if ($msn_initial == '3697' && $phase != 1) {
            $errorRemark = "Meter with Prefix of 97 can only be allowed for Single Phase Device";
        } elseif (($msn_initial == '3698' || $msn_initial == '3699') && $phase != '3') {
            $errorRemark = "Meter with Prefix of 98, 99 can only be allowed for Three Phase Device.";
        } elseif ($msn_initial == '3698' && $meter_type != '2') {
            $errorRemark = "Meter with Prefix of 98 can only be allowed for Three Phase Whole Current Device.";
        } elseif ($msn_initial == '3699' && ($meter_type != 3 && $meter_type != 4)) {
            $errorRemark = "Meter with Prefix of 99 can only be allowed for CTO OR CTPT Device.";
        }

        if ($errorRemark) {
            return [
                "global_device_id" => $gdid,
                "msn" => $msn,
                "indv_status" => "0",
                "remarks" => $errorRemark,
            ];
        }

        return null;
    }

    private function checkExistingMsn($msn, $gdid)
    {
        $data_on = DB::connection('mysql2')
            ->table('meter')
            ->where('msn', $msn)
            ->get(['global_device_id'])
            ->first();

        if (!is_null($data_on) && $data_on->global_device_id != $gdid) {
            return [
                "global_device_id" => $gdid,
                "msn" => $msn,
                "indv_status" => "0",
                "remarks" => "Same MSN already exist with different Global Device ID (" . ($data_on->global_device_id) . ") Global Device ID can't be changed",
            ];
        }
        return null;
    }

    private function processDevice($device, $form_params, $transaction, $now, $slug)
    {
        $gdid = $device['global_device_id'];
        $msn = $device['dsn'];

        $comSettings = $this->getCommunicationSettings($form_params['communication_type']);
        $meterConfig = $this->getMeterConfiguration($msn);
        $bidirectionalSettings = $this->getBidirectionalSettings($form_params);

        DB::connection('mysql2')->beginTransaction();
        try {
            $found_meter = DB::connection('mysql2')->table('meter')->where('global_device_id', $gdid)->count();

            if ($found_meter == 0) {
                $this->insertNewMeter($device, $form_params, $comSettings, $meterConfig, $bidirectionalSettings);
            } else {
                $this->updateExistingMeter($gdid, $comSettings, $bidirectionalSettings, $form_params);
            }

            $log = set_wakeup_and_transaction($msn, $now, $slug, $transaction, $gdid);

            DB::connection('mysql2')->commit();

            $this->updateMeterVisuals($gdid, $msn, $form_params);

            if ($log) {
                DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $gdid)
                    ->update(['wakeup_request_id' => $log]);

                return [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "1",
                    "remarks" => "Meter Created Successfully",
                ];
            } else {
                return [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Meter Creation Failed",
                ];
            }
        } catch (\Exception $e) {
            DB::connection('mysql2')->rollback();
            dump($e->getMessage());
            return [
                "global_device_id" => $gdid,
                "msn" => $msn,
                "indv_status" => "0",
                "remarks" => "Unknown DB error occurred. $e",
            ];
        }
    }

    private function getCommunicationSettings($com_type)
    {
        if ($com_type == 2) { // Keepalive
            return [
                'keepAlive' => 1,
                'sch_pq' => 3, 'sch_mb' => 3, 'sch_ev' => 3, 'sch_lp' => 3, 'sch_lp2' => 3, 'sch_lp3' => 3,
                'save_sch_pq' => 2, 'sch_ss' => 2, 'sch_cs' => 3, 'sch_cb' => 1,
                'kas' => '00:00:10',
                'meter_class' => 'keepalive',
                'set_keepalive' => 1,
                'interval_pq' => '00:15:00', 'interval_cb' => '00:15:00', 'interval_mb' => '00:15:00',
                'interval_ev' => '00:05:00', 'interval_lp' => '00:15:00', 'interval_cs' => '00:15:00'
            ];
        } else { // NON-Keepalive
            return [
                'keepAlive' => 0,
                'sch_pq' => 2, 'sch_mb' => 2, 'sch_ev' => 2, 'sch_lp' => 2, 'sch_lp2' => 2, 'sch_lp3' => 2,
                'save_sch_pq' => 2, 'sch_ss' => 2, 'sch_cs' => 2, 'sch_cb' => 1,
                'kas' => '00:01:00',
                'meter_class' => 'non-keepalive',
                'set_keepalive' => 2,
                'interval_pq' => '00:15:00', 'interval_cb' => '00:01:00', 'interval_mb' => '00:01:00',
                'interval_ev' => '00:01:00', 'interval_lp' => '00:01:00', 'interval_cs' => '00:01:00'
            ];
        }
    }

    private function getMeterConfiguration($msn)
    {
        if (substr($msn, 0, 4) == 3698) {
            return ['model_id' => 3, 'load_profile_group_id' => 201, 'contactor_opt' => 1];
        } else {
            return ['model_id' => 5, 'load_profile_group_id' => 282, 'contactor_opt' => 0];
        }
    }

    private function getBidirectionalSettings($form_params)
    {
        $meter_type = $form_params['meter_type'];
        $is_bidirectional = $form_params['bidirectional_device'] == 1;

        // Common Defaults
        $energy_param_id = $is_bidirectional ? 1 : 2;
        $bidirectional_flag = $is_bidirectional ? 1 : 0;

        // Initialize settings array
        $settings = [
            'energy_param_id' => $energy_param_id,
            'bidirectional_flag' => $bidirectional_flag,
            'isLtHt' => false, // Default to false, override for 3 & 4
        ];

        switch ($meter_type) {
            case '1': // Normal (Single Phase)
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 8;
                    $settings['dw_alternate'] = 9;
                } else {
                    $settings['dw_normal'] = 10;
                    $settings['dw_alternate'] = 11;
                }

                $settings['load_profile_group_id'] = 205;
                $settings['encryption_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['authentication_key'] = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                $settings['master_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['dds_compatible'] = 1;
                $settings['max_events_entries'] = 100;
                $settings['association_id'] = 10;
                $settings['max_billing_months'] = 12;
                $settings['read_logbook'] = 1;
                $settings['lp_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            case '2': // Whole Current (Three Phase)
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 8;
                    $settings['dw_alternate'] = 9;
                } else {
                    $settings['dw_normal'] = 10;
                    $settings['dw_alternate'] = 11;
                }

                $settings['load_profile_group_id'] = 205;
                $settings['encryption_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['authentication_key'] = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                $settings['master_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['dds_compatible'] = 1;
                $settings['max_events_entries'] = 100;
                $settings['association_id'] = 10;
                $settings['max_billing_months'] = 12;
                $settings['read_logbook'] = 1;
                $settings['lp_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            case '3': // CTO
                $settings['isLtHt'] = true;
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 12;
                    $settings['dw_alternate'] = 13;
                } else {
                    $settings['dw_normal'] = 14;
                    $settings['dw_alternate'] = 15;
                }

                $settings['load_profile_group_id'] = 206;
                $settings['encryption_key'] = "F2CE6D0BC7E53DA0B23FCCEE9736D617";
                $settings['authentication_key'] = "C1CA4472EFE30A2668CC10A64DCCCED7";
                $settings['master_key'] = "00000000000000000000000000000000";
                $settings['dds_compatible'] = 0;
                $settings['max_events_entries'] = 300;
                $settings['association_id'] = 2;
                $settings['max_billing_months'] = 24;
                $settings['read_logbook'] = 2;
                $settings['lp_invalid_update'] = 2;
                $settings['ev_invalid_update'] = 2;
                $settings['schedule_plan'] = "7,3,4,5,6,8,10,11";
                $settings['interval_lp'] = '00:30:00';
                break;

            case '4': // CTPT
                $settings['isLtHt'] = true;
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 12;
                    $settings['dw_alternate'] = 13;
                } else {
                    $settings['dw_normal'] = 14;
                    $settings['dw_alternate'] = 15;
                }

                $settings['load_profile_group_id'] = 206;
                $settings['encryption_key'] = "F2CE6D0BC7E53DA0B23FCCEE9736D617";
                $settings['authentication_key'] = "C1CA4472EFE30A2668CC10A64DCCCED7";
                $settings['master_key'] = "00000000000000000000000000000000";
                $settings['dds_compatible'] = 0;
                $settings['max_events_entries'] = 300;
                $settings['association_id'] = 2;
                $settings['max_billing_months'] = 24;
                $settings['read_logbook'] = 2;
                $settings['lp_invalid_update'] = 2;
                $settings['ev_invalid_update'] = 2;
                $settings['schedule_plan'] = "7,3,4,5,6,8,10,11";
                $settings['interval_lp'] = '00:30:00';
                break;

            case '5': // AMPS (Other)
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 8;
                    $settings['dw_alternate'] = 9;
                } else {
                    $settings['dw_normal'] = 10;
                    $settings['dw_alternate'] = 11;
                }

                $settings['load_profile_group_id'] = 205;
                $settings['encryption_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['authentication_key'] = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                $settings['master_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['dds_compatible'] = 1;
                $settings['max_events_entries'] = 100;
                $settings['association_id'] = 10;
                $settings['max_billing_months'] = 12;
                $settings['read_logbook'] = 1;
                $settings['lp_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            default: // Fallback
                if ($is_bidirectional) {
                    $settings['dw_normal'] = 8;
                    $settings['dw_alternate'] = 9;
                } else {
                    $settings['dw_normal'] = 10;
                    $settings['dw_alternate'] = 11;
                }

                $settings['load_profile_group_id'] = 205;
                $settings['encryption_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['authentication_key'] = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                $settings['master_key'] = "000102030405060708090A0B0C0D0E0F";
                $settings['dds_compatible'] = 1;
                $settings['max_events_entries'] = 100;
                $settings['association_id'] = 10;
                $settings['max_billing_months'] = 12;
                $settings['read_logbook'] = 1;
                $settings['lp_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;
        }
        return $settings;
    }

    private function insertNewMeter($device, $form_params, $comSettings, $meterConfig, $bidirectionalSettings)
    {
        $deviceType = (int) $form_params['device_type'];
        $meterType = (int) $form_params['meter_type'];
        $mdiResetDate = (string) $form_params['mdi_reset_date'];
        $mdiResetTime = $form_params['mdi_reset_time'];

        $ins = [
            'control'                => '1',
            'datetime_year'          => '65535',
            'datetime_month'         => '255',
            'datetime_day_of_month'  => '255',
            'datetime_day_of_week'   => '255',
            'datetime_hours'         => '0',
            'datetime_minutes'       => '0',
            'datetime_seconds'       => '0',
            'interval_timespan'      => $form_params['initial_communication_time'],
            'interval_sink_minutes'  => $form_params['communication_interval'],
            'interval_sink_seconds'  => 0,
            'interval_fixed_minutes' => 0,
            'interval_fixed_seconds' => 0,
        ];

        $id_com_interval = DB::connection('mysql2')
            ->table('time_base_events_detail')
            ->insertGetId($ins);

        $wakeupNumbers = $this->getWakeupNumbers();

        $data = array(
            'tbe1_write_request_id' => $id_com_interval,
            'name' => $device['dsn'],
            'description' => 'Meter Created using UDIL Service',
            'global_device_id' => $device['global_device_id'],
            'msn' => $device['dsn'],
            'model_id' => $meterConfig['model_id'],
            'cat_id' => $deviceType,
            'class' => $comSettings['meter_class'],
            'code' => 0,
            'schedule_plan' => $bidirectionalSettings['schedule_plan'],
            'prioritize_wakeup' => '1',
            'encryption_key' => $bidirectionalSettings['encryption_key'],
            'authentication_key' => $bidirectionalSettings['authentication_key'],
            'master_key' => $bidirectionalSettings['master_key'],
            'dds_compatible' => $bidirectionalSettings['dds_compatible'],
            'rated_mva' => '0',
            'rated_amps' => '0',
            'ct_ratio_num' => '1',
            'ct_ratio_denum' => '1',
            'type' => $comSettings['keepAlive'],
            'sub_type' => '0',
            'use_for_demand' => '0',
            'export_company' => 0,
            'mf' => 1,
            'sim' => $form_params['sim_number'],
            'sim_id' => $form_params['sim_id'],
            'meter_type_id' => $meterType,
            'pt_ratio_num' => '1',
            'pt_ratio_denum' => '1',
            'breaker_capacity' => 0,
            'pool_interval' => '1',
            'is_installed' => '0',
            'status' => '1',
            'password' => 'microtek',
            'enable_live_updation' => '1',
            'read_events_on_major_alarms' => '1',
            'log_echo_enable' => '1',
            'log_save_enable' => '1',
            'save_life_time' => '1',
            'read_mb_on_mdi_reset' => '0',
            'read_lp' => '2',
            'read_lp2' => '2',
            'read_lp3' => '2',
            'read_pq' => '0',
            'read_ev' => '1',
            'read_cb' => '0',
            'read_mb' => '1',
            'read_ar' => '1',
            'read_ss' => '1',
            'save_pq' => '1',
            'save_mb' => '1',
            'save_lp' => '1',
            'save_ar' => '1',
            'save_st' => '1',
            'save_ev' => '1',
            'save_cb' => '1',
            'last_lp_time' => '2021-01-01 00:00:00',
            'last_pq_time' => '2021-01-01 00:00:00',
            'last_ev_time' => '2021-01-01 00:00:00',
            'last_cb_time' => '2021-01-01 00:00:00',
            'last_mb_time' => '2021-01-01 00:00:00',
            'default_reset_duration' => '00:15:00',
            'unset_ma' => '0',
            'detailed_billing_id' => '0',
            'sch_pq' => $comSettings['sch_pq'],
            'base_time_pq' => '2022-01-01 00:00:00',
            'interval_pq' => $comSettings['interval_pq'],
            'sch_cb' => $comSettings['sch_cb'],
            'base_time_cb' => '2022-01-01 00:00:00',
            'interval_cb' => $comSettings['interval_cb'],
            'sch_mb' => $comSettings['sch_mb'],
            'base_time_mb' => '2022-01-01 00:00:00',
            'interval_mb' => $comSettings['interval_mb'],
            'sch_ev' => $comSettings['sch_ev'],
            'base_time_ev' => '2022-01-01 00:00:00',
            'interval_ev' => $comSettings['interval_ev'],
            'sch_lp' => $comSettings['sch_lp'],
            'sch_lp2' => $comSettings['sch_lp2'],
            'sch_lp3' => $comSettings['sch_lp3'],
            'base_time_lp' => '2022-01-01 00:00:00',
            'interval_lp' => $bidirectionalSettings['interval_lp'],
            'interval_lp2' => $bidirectionalSettings['interval_lp'],
            'interval_lp3' => $bidirectionalSettings['interval_lp'],
            'max_load_profile_entries' => '4096',
            'max_load_profile2_entries' => '4096',
            'max_load_profile3_entries' => '4096',
            'monthly_billing_counter' => '0',
            'load_profile_group_id' => $bidirectionalSettings['load_profile_group_id'],
            'load_profile2_group_id' => 202,
            'load_profile3_group_id' => 201,
            'set_ip_profiles' => '0',
            'set_modem_initialize_basic' => '0',
            'set_modem_initialize_extended' => '0',
            'Apply_Disable_Tbe_Flag_On_Powerfail' => '0000',
            'set_keepalive' => $comSettings['set_keepalive'],
            'major_alarm_group_id' => '0',
            'max_lp_count_diff' => '500',
            'min_lp_count_diff' => '1',
            'kas_interval' => $comSettings['kas'],
            'kas_due_time' => '2022-01-01 00:00:00',
            'save_sch_pq' => $comSettings['save_sch_pq'],
            'save_base_time_pq' => '2021-01-01 00:00:00',
            'save_interval_pq' => $comSettings['interval_pq'],
            'save_last_pq_time' => '2021-01-01 00:00:00',
            'last_ss_time' => '2021-01-01 00:00:00',
            'last_cs_time' => '2021-01-01 00:00:00',
            'super_immediate_pq' => '0',
            'super_immediate_cb' => '0',
            'super_immediate_mb' => '0',
            'super_immediate_ev' => '0',
            'super_immediate_lp' => '0',
            'super_immediate_ss' => '0',
            'super_immediate_cs' => '0',
            'sch_ss' => $comSettings['sch_ss'],
            'base_time_ss' => '2022-01-01 00:00:00',
            'interval_ss' => '12:00:00',
            'sch_cs' => $comSettings['sch_cs'],
            'base_time_cs' => '2022-01-01 00:00:00',
            'interval_cs' => $comSettings['interval_cs'],
            'events_to_save_pq' => '0',
            'log_level' => '4',
            'lp_write_channel_request' => '0',
            'lp_write_channel_1' => '0',
            'lp_write_channel_2' => '0',
            'lp_write_channel_3' => '0',
            'lp_write_channel_4' => '0',
            'lp_write_interval_request' => '0',
            'lp_write_interval' => '30',
            'lp2_write_interval' => '30',
            'lp3_write_interval' => '1440',
            'max_events_entries' => $bidirectionalSettings['max_events_entries'],
            'max_ev_count_diff' => '1500',
            'min_ev_count_diff' => '1',
            'is_prepaid' => $meterConfig['contactor_opt'],
            'apply_new_contactor_state' => '0',

            'is_rf' => '0',
            'is_line' => 0,
            'dw_alternate_format' => '1',
            'dw_normal_format' => '1',
            'current_contactor_status' => '1',
            'reference_no' => NULL,
            'wakeup_password' => '1234567890',
            'default_password' => 'microtek',
            'display_power_down_id' => '0',
            'contactor_param_id' => '0',
            'events_liveUpdate' => '000000000000',
            'events_liveUpdate_individual' => '000000000000',
            'events_liveUpdate_logbook' => '000000000000',
            'individual_events_string_alarm' => '000000000000',
            'individual_events_string_sch' => '000000000000',
            'last_password_update_time' => '2021-01-01 00:00:00',
            'mb_invalid_update' => '0',
            'ev_invalid_update' => $bidirectionalSettings['ev_invalid_update'],
            'new_meter_password' => 'microtek',
            'new_password_activation_time' => '2021-01-01 00:00:00',
            'lp_chunk_size' => '100',
            'lp2_chunk_size' => '100',
            'lp3_chunk_size' => '100',
            'is_individual' => '0',
            'lp_invalid_update' => $bidirectionalSettings['lp_invalid_update'],
            'lp2_invalid_update' => $bidirectionalSettings['lp_invalid_update'],
            'lp3_invalid_update' => $bidirectionalSettings['lp_invalid_update'],
            'dw_normal_mode_id' => $bidirectionalSettings['dw_normal'],
            'dw_alternate_mode_id' => $bidirectionalSettings['dw_alternate'],
            'energy_param_id' => $bidirectionalSettings['energy_param_id'],
            'bidirectional_device' => $bidirectionalSettings['bidirectional_flag'],
            'max_cs_difference' => '4000',
            'min_cs_difference' => '40',
            'no_show_kwh' => '0',
            'modem_limits_time_id' => '0',
            'new_contactor_state' => '0',
            'no_show_ls' => '0',
            'scroll_time' => '15',
            'mdi_reset_date' => $mdiResetDate,
            'mdi_reset_time' => $mdiResetTime,
            'number_profile_group_id' => '0',
            'read_individual_events_sch' => '0',
            'set_wakeup_profile_id' => '0',
            'write_contactor_param' => '0',
            'write_mdi_reset_date' => '0',
            'read_logbook' => $bidirectionalSettings['read_logbook'],
            'write_modem_limits_time' => '0',
            'write_password_flag' => '0',
            'write_reference_no' => '0',
            'read_cs' => '1',
            'wakeup_no1' => $wakeupNumbers['wakeup_no1'],
            'wakeup_no2' => $wakeupNumbers['wakeup_no2'],
            'wakeup_no3' => $wakeupNumbers['wakeup_no3'],
            'wakeup_no4' => $wakeupNumbers['wakeup_no4'],
            'is_overload' => '0',
            'is_time_sync' => '0',
            'association_id' => $bidirectionalSettings['association_id'],
            'save_events_on_alarm' => 0,
            'max_billing_months' => $bidirectionalSettings['max_billing_months']
        );

        DB::connection('mysql2')
            ->table('meter')
            ->insertGetId($data);
    }

    private function updateExistingMeter($gdid, $comSettings, $bidirectionalSettings, $form_params)
    {
        $datav = array(
            'class' => $comSettings['meter_class'],
            'type' => $comSettings['keepAlive'],
            'sch_pq' => $comSettings['sch_pq'],
            'interval_pq' => $comSettings['interval_pq'],
            'sch_cb' => $comSettings['sch_cb'],
            'interval_cb' => $comSettings['interval_cb'],
            'sch_mb' => $comSettings['sch_mb'],
            'interval_mb' => $comSettings['interval_mb'],
            'sch_ev' => $comSettings['sch_ev'],
            'interval_ev' => $comSettings['interval_ev'],
            'sch_lp' => $comSettings['sch_lp'],
            'sch_lp2' => $comSettings['sch_lp2'],
            'sch_lp3' => $comSettings['sch_lp3'],
            'interval_lp' => $bidirectionalSettings['interval_lp'],
            'kas_interval' => $comSettings['kas'],
            'save_sch_pq' => $comSettings['save_sch_pq'],
            'save_interval_pq' => $comSettings['interval_pq'],
            'sch_ss' => $comSettings['sch_ss'],
            'sch_cs' => $comSettings['sch_cs'],
            'interval_cs' => $comSettings['interval_cs'],
            'set_keepalive' => $comSettings['set_keepalive'],
            'super_immediate_pq' => '1',
            'bidirectional_device' => $bidirectionalSettings['bidirectional_flag'],
            'dw_normal_mode_id' => $bidirectionalSettings['dw_normal'],
            'dw_alternate_mode_id' => $bidirectionalSettings['dw_alternate'],
            'energy_param_id' => $bidirectionalSettings['energy_param_id'],
            'max_billing_months' => $bidirectionalSettings['max_billing_months'],
            'cat_id' => (int) $form_params['device_type'],
            'meter_type_id' => (int) $form_params['meter_type'],
            'sim' => $form_params['sim_number'],
            'sim_id' => $form_params['sim_id'],
            'mdi_reset_date' => (string) $form_params['mdi_reset_date'],
            'mdi_reset_time' => $form_params['mdi_reset_time'],
        );


        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $gdid)
            ->update($datav);
    }

    private function updateMeterVisuals($gdid, $msn, $form_params)
    {
        $data_m = [
            'msn' => $msn,
            'msim_id' => $form_params['sim_id'],
            'mdi_reset_time' => $form_params['mdi_reset_time'],
            'mdi_reset_date' => $form_params['mdi_reset_date'],
            'dmdt_communication_interval' => $form_params['communication_interval'],
            'dmdt_communication_mode' => $form_params['communication_mode'],
            'dmdt_bidirectional_device' => (int) $form_params['bidirectional_device'],
            'dmdt_communication_type' => $form_params['communication_type'],
            'dmdt_initial_communication_time' => $form_params['initial_communication_time'],
            'dmdt_phase' => $form_params['phase'],
            'dmdt_meter_type' => $form_params['meter_type'],
            'dmdt_datetime' => $form_params['request_datetime'] ?? now(),
        ];

        DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', $gdid)
            ->update($data_m);
    }

    private function getWakeupNumbers()
    {
        return [
            'wakeup_no1' => $this->resolveWakeupNumber('wakeup.no1', '03018446741'),
            'wakeup_no2' => $this->resolveWakeupNumber('wakeup.no2', '03212345686'),
            'wakeup_no3' => $this->resolveWakeupNumber('wakeup.no3', '03004009347'),
            'wakeup_no4' => $this->resolveWakeupNumber('wakeup.no4', '03236762704'),
        ];
    }

    private function resolveWakeupNumber($configKey, $defaultValue)
    {
        $value = config($configKey);

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return $defaultValue;
        }

        return (string) $value;
    }
}
