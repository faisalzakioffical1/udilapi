<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceMetadataController extends Controller
{
    private $validatedDevices = [];


    /**
     * Update Device Metadata for given meters.
     */
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

        // Validate request
        $validationResponse = $this->validateRequest($form_params, $transaction);
        if ($validationResponse) {
            return response()->json($validationResponse, $validationResponse['http_status']);
        }

        // Get devices from request
        $all_devices = $this->validatedDevices;

        if (empty($all_devices)) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'transactionid' => $transaction,
                'message' => 'Global Device ID is required'
            ], 400);
        }

        $slug = $form_params['slug'] ?? 'update_device_metadata';

        $successfulMeters = 0;
        $requestDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            trim($form_params['request_datetime'])
        )->format('Y-m-d H:i:s');

        foreach ($all_devices as $dvc) {
            $result = $this->processDevice($dvc, $form_params, $transaction, $slug, $requestDatetime);
            if ($result) {
                $datar[] = $result;
                if ($result['indv_status'] === '1') {
                    $successfulMeters++;
                }
            }
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Device Metadata is applied to meters with indv_status = 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function validateRequest($form_params, $transaction)
    {
        $transactionId = $transaction ?? '0';
        $requiredFields = [
            'global_device_id',
            'communication_mode',
            'bidirectional_device',
            'communication_type',
            'phase',
            'meter_type',
            'initial_communication_time',
            'communication_interval',
            'request_datetime'
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $form_params)) {
                return $this->validationError($transactionId, "$field field is mandatory", $form_params);
            }

            $value = $form_params[$field];
            if (is_string($value) && trim($value) === '') {
                return $this->validationError($transactionId, "$field field contains blank value", $form_params);
            }
        }

        if (!is_date_valid($form_params['request_datetime'], 'Y-m-d H:i:s')) {
            return $this->validationError($transactionId, 'request_datetime must be in Y-m-d H:i:s format', $form_params);
        }

        $devices = $this->normalizeDeviceList($form_params['global_device_id']);
        if (!is_array($devices)) {
            return $this->validationError($transactionId, 'global_device_id must be a JSON array of devices', $form_params);
        }

        if (empty($devices)) {
            return $this->validationError($transactionId, 'Global Device ID is required', $form_params);
        }

        foreach ($devices as $device) {
            if (!isset($device['global_device_id']) || !isset($device['msn']) || $device['global_device_id'] === '' || $device['msn'] === '') {
                return $this->validationError($transactionId, 'Each device entry must contain both global_device_id and msn', $form_params);
            }

            if (!is_valid_msn($device['msn'])) {
                return $this->validationError($transactionId, "Invalid value for field 'msn'. Only digits are allowed", $form_params);
            }
        }

        $this->validatedDevices = $devices;

        $checks = [
            'communication_mode' => [1, 5, "Invalid value for field 'communication_mode'. Acceptable Values are 1 - 5"],
            'bidirectional_device' => [0, 1, "Invalid value for field 'bidirectional_device'. Acceptable Values are 0 - 1"],
            'communication_type' => [1, 2, "Invalid value for field 'communication_type'. Acceptable Values are 1 - 2"],
            'phase' => [1, 3, "Invalid value for field 'phase'. Acceptable Values are 1 - 3"],
            'meter_type' => [1, 5, "Invalid value for field 'meter_type'. Acceptable Values are 1 - 5"],
            'communication_interval' => [0, 1440, "Invalid value for field 'communication_interval'. Acceptable Values are 0 - 1440"],
        ];

        foreach ($checks as $key => [$min, $max, $message]) {
            if (!filter_integer($form_params[$key], $min, $max)) {
                return $this->validationError($transactionId, $message, $form_params);
            }
        }

        if (!is_time_valid($form_params['initial_communication_time'])) {
            return $this->validationError($transactionId, "Invalid value for field 'initial_communication_time'. Expected format HH:mm:ss", $form_params);
        }

        $interval = (int)trim($form_params['communication_interval'], "\"' ");
        $comType = (int)trim($form_params['communication_type'], "\"' ");

        if ($comType === 2 && $interval !== 0) {
            return $this->validationError($transactionId, 'Communication interval must be 0 for keep-alive devices', $form_params);
        }

        if ($comType === 1 && $interval <= 0) {
            return $this->validationError($transactionId, 'Communication interval must be greater than 0 for non keep-alive devices', $form_params);
        }

        return null;
    }

    private function normalizeDeviceList($payload): ?array
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

    private function validationError(string $transactionId, string $message, array $form_params): array
    {
        return [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transactionId,
            'message' => $message,
            'debug' => $form_params,
        ];
    }

    private function processDevice($dvc, $form_params, $transaction, $slug, string $requestDatetime)
    {
        $globalDeviceId = $dvc['global_device_id'] ?? '';
        $msn = $dvc['msn'] ?? 0;

        if ($msn == 0 || empty($globalDeviceId)) {
            return [
                "global_device_id" => $globalDeviceId,
                "msn" => $msn,
                "indv_status" => "0",
                "remarks" => config('udil.meter_not_exists')
            ];
        }

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId)
            ->first();

        if (!$meter) {
            return [
                "global_device_id" => $globalDeviceId,
                "msn" => $msn,
                "indv_status" => "0",
                "remarks" => config('udil.meter_not_exists')
            ];
        }

        $meterMsn = $meter->msn;

        insert_transaction_status($globalDeviceId, $meterMsn, $transaction, "WDMDT");

        $com_type = $form_params['communication_type'];
        $keepAliveConfig = $this->getKeepAliveConfig($com_type);

        $meterSettings = $this->getMeterSettings($form_params);

        $data = array_merge($keepAliveConfig, $meterSettings, [
            'bidirectional_device' => $form_params['bidirectional_device'],
            'super_immediate_pq' => '0',
        ]);

        DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId)
            ->update($data);

        $this->updateDeviceMetadataVisuals($globalDeviceId, $form_params, $requestDatetime);

        insert_event(
            $meterMsn,
            $globalDeviceId,
            102,
            'Parameterization Programmed',
            $requestDatetime
        );

        update_on_transaction_success($slug, $globalDeviceId);

        return [
            "global_device_id" => $globalDeviceId,
            "msn" => $meterMsn,
            "indv_status" => "1",
            "remarks" => "Meter Communication Mode will be changed",
        ];
    }

    private function getKeepAliveConfig($com_type)
    {
        if ($com_type == 2) {
            return [
                'class' => 'keepalive',
                'type' => 1,
                'sch_pq' => 3, 'interval_pq' => '00:15:00',
                'sch_cb' => 1, 'interval_cb' => '00:15:00',
                'sch_mb' => 3, 'interval_mb' => '00:15:00',
                'sch_ev' => 3, 'interval_ev' => '00:05:00',
                'sch_lp' => 3, 'sch_lp2' => 3, 'sch_lp3' => 3,
                'interval_lp' => '00:15:00', 'kas_interval' => '00:00:10',
                'save_sch_pq' => 2, 'save_interval_pq' => '00:15:00',
                'sch_ss' => 2, 'sch_cs' => 3, 'interval_cs' => '00:15:00',
                'set_keepalive' => 1,
            ];
        } else {
            return [
                'class' => 'non-keepalive',
                'type' => 0,
                'sch_pq' => 2, 'interval_pq' => '00:15:00',
                'sch_cb' => 1, 'interval_cb' => '00:01:00',
                'sch_mb' => 2, 'interval_mb' => '00:01:00',
                'sch_ev' => 2, 'interval_ev' => '00:01:00',
                'sch_lp' => 2, 'sch_lp2' => 2, 'sch_lp3' => 2,
                'interval_lp' => '00:01:00', 'kas_interval' => '00:01:00',
                'save_sch_pq' => 2, 'save_interval_pq' => '00:15:00',
                'sch_ss' => 2, 'sch_cs' => 2, 'interval_cs' => '00:01:00',
                'set_keepalive' => 2,
            ];
        }
    }

    private function getMeterSettings($form_params)
    {
        $meter_type = $form_params['meter_type'];
        $is_bidirectional = $form_params['bidirectional_device'] == 1;
        $energy_param_id = $is_bidirectional ? 1 : 2;

        $settings = [
            'energy_param_id' => $energy_param_id,
        ];

        switch ($meter_type) {
            case '1': // Normal (Single Phase)
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 8;
                    $settings['dw_alternate_mode_id'] = 9;
                } else {
                    $settings['dw_normal_mode_id'] = 10;
                    $settings['dw_alternate_mode_id'] = 11;
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
                $settings['lp2_invalid_update'] = 0;
                $settings['lp3_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            case '2': // Whole Current (Three Phase)
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 8;
                    $settings['dw_alternate_mode_id'] = 9;
                } else {
                    $settings['dw_normal_mode_id'] = 10;
                    $settings['dw_alternate_mode_id'] = 11;
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
                $settings['lp2_invalid_update'] = 0;
                $settings['lp3_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            case '3': // CTO
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 12;
                    $settings['dw_alternate_mode_id'] = 13;
                } else {
                    $settings['dw_normal_mode_id'] = 14;
                    $settings['dw_alternate_mode_id'] = 15;
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
                $settings['lp2_invalid_update'] = 2;
                $settings['lp3_invalid_update'] = 2;
                $settings['ev_invalid_update'] = 2;
                $settings['schedule_plan'] = "7,3,4,5,6,8,10,11";
                $settings['interval_lp'] = '00:30:00';
                break;

            case '4': // CTPT
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 12;
                    $settings['dw_alternate_mode_id'] = 13;
                } else {
                    $settings['dw_normal_mode_id'] = 14;
                    $settings['dw_alternate_mode_id'] = 15;
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
                $settings['lp2_invalid_update'] = 2;
                $settings['lp3_invalid_update'] = 2;
                $settings['ev_invalid_update'] = 2;
                $settings['schedule_plan'] = "7,3,4,5,6,8,10,11";
                $settings['interval_lp'] = '00:30:00';
                break;

            case '5': // AMPS (Other)
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 8;
                    $settings['dw_alternate_mode_id'] = 9;
                } else {
                    $settings['dw_normal_mode_id'] = 10;
                    $settings['dw_alternate_mode_id'] = 11;
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
                $settings['lp2_invalid_update'] = 0;
                $settings['lp3_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;

            default: // Fallback
                if ($is_bidirectional) {
                    $settings['dw_normal_mode_id'] = 8;
                    $settings['dw_alternate_mode_id'] = 9;
                } else {
                    $settings['dw_normal_mode_id'] = 10;
                    $settings['dw_alternate_mode_id'] = 11;
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
                $settings['lp2_invalid_update'] = 0;
                $settings['lp3_invalid_update'] = 0;
                $settings['ev_invalid_update'] = 0;
                $settings['schedule_plan'] = "3,4,5,6,7,8,10,11";
                $settings['interval_lp'] = '12:00:00';
                break;
        }

        return $settings;
    }

    private function updateDeviceMetadataVisuals(string $globalDeviceId, array $form_params, string $requestDatetime): void
    {
        if (!config('udil.update_meter_visuals_for_write_services')) {
            return;
        }

        // Fetch msn from meter table
        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId)
            ->first();

        if (!$meter || !isset($meter->msn)) {
            return; // Cannot proceed without msn
        }

        $data = [
            'global_device_id' => $globalDeviceId,
            'msn' => $meter->msn,
            'dmdt_datetime' => $requestDatetime,
            'dmdt_communication_interval' => $form_params['communication_interval'],
            'dmdt_communication_mode' => $form_params['communication_mode'],
            'dmdt_bidirectional_device' => $form_params['bidirectional_device'],
            'dmdt_communication_type' => $form_params['communication_type'],
            'dmdt_initial_communication_time' => $form_params['initial_communication_time'],
            'dmdt_phase' => $form_params['phase'],
            'dmdt_meter_type' => $form_params['meter_type'],
        ];

        DB::connection('mysql2')
            ->table('meter_visuals')
            ->updateOrInsert(
                ['global_device_id' => $globalDeviceId],
                $data
            );
    }

}
