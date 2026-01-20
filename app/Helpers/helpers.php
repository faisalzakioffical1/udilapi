<?php
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Meter;

if (!function_exists('update_meter')) {
    function update_meter($global_device_id, $datau)
    {
        $in = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $global_device_id)
            ->update($datau);
    }
}

if (!function_exists('update_response_fail')) {
    function update_response_fail(&$response, $http_status, $message)
    {
        update_response($response, 0, $http_status, $message);
    }
}

if (!function_exists('update_response')) {
    function update_response(&$response, $status, $http_status, $message)
    {
        $response['status'] = $status;
        $response['http_status'] = $http_status;
        $response['message'] = $message;
    }
}

if (!function_exists('insert_event')) {
    function insert_event($msn, $gdid, $code, $description, $now)
    {
        $maxValue = DB::connection('mysql2')
            ->table('events')
            ->max('event_counter');
        $maxValue_use = $maxValue + 1;

        $data_ev = [
            'msn' => $msn,
            'global_device_id' => $gdid,
            'event_datetime' => $now,
            'event_code' => $code,
            'event_counter' => $maxValue_use,
            'event_description' => $description,
            'mdc_read_datetime' => $now,
            'db_datetime' => $now,
        ];

        $in = DB::connection('mysql2')
            ->table('events')
            ->insert($data_ev);
    }
}

if (!function_exists('insert_data')) {
    function insert_data($table, $data)
    {
        return DB::connection('mysql2')
            ->table($table)
            ->insertGetId($data);
    }
}

if (!function_exists('apply_common_validation')) {
    function apply_common_validation($slug, &$headers, &$form_params)
    {
        $response['status'] = 1;
        $services_list = array(
            'authorization_service',
            'on_demand_data_read',
            'on_demand_parameter_read',
            'aux_relay_operations',
            'update_wake_up_sim_number',
            'time_synchronization',
            'update_mdi_reset_date',
            'transaction_status',
            'update_device_metadata',
            'device_creation',
            'meter_data_sampling',
            'update_meter_status',
            'sanctioned_load_control',
            'update_ip_port',
            'activate_meter_optical_port',
            'load_shedding_scheduling',
            'update_time_of_use',
            'parameterization_cancellation',
            'update_major_alarms',
            'transaction_cancel',
            'apms_tripping_events'
        );

        if (!in_array($slug, $services_list)) {
            $response['status'] = 0;
            $response['message'] = 'Requested Service is not implemented yet';
        } else if ($slug != 'authorization_service' && (!isset($headers['privatekey']) || !isset($headers['transactionid']))) {
            $response['status'] = 0;
            $response['message'] = 'Private Key or Transaction ID is missing in Request';
        } else if ($slug != 'authorization_service' && $slug != 'device_creation'  && $slug != 'transaction_status') {
            // Checking Global devices
            if (!isset($form_params['global_device_id'])) {
                $response['status'] = 0;
                $response['message'] = 'Global Device ID is required';
            } else if (has_invalid_msns($form_params['global_device_id'])) {
                $response['status'] = 0;
                $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
            }
        } else if ($slug != 'authorization_service' && $slug != 'on_demand_parameter_read' && $slug != 'transaction_status' && $slug != 'on_demand_data_read' &&  $slug != 'parameterization_cancellation') {
            // Checking Request datetime
            if (!isset($form_params['request_datetime'])) {
                $response['status'] = 0;
                $response['message'] = 'Request Datetime Field is required';
            } else if (!is_date_valid($form_params['request_datetime'], "Y-m-d H:i:s")) {
                $response['status'] = 0;
                $response['message'] = 'Request Date is invalid';
            }
        } else if ($slug == 'on_demand_parameter_read' || $slug == 'on_demand_data_read') {
            if (is_array(json_decode($form_params['global_device_id'], true))) {
                $response['status'] = 0;
                $response['message'] = 'Single Global Device ID is required';
            }
            if (!isset($form_params['type'])) {
                $response['status'] = 0;
                $response['message'] = 'Type field is required';
            }

            $meter_status = DB::connection('mysql2')->table('meter')
                ->where('global_device_id', json_decode($form_params['global_device_id'], true))
                ->get()->toArray();

            if ($meter_status[0]->status == 0) {
                $response['status'] = 0;
                $response['message'] = 'This meter having msn "' . $meter_status[0]->msn . '"is marked de-activated. Please, activate it first';
            }
        }

        if ($slug != 'authorization_service' && $slug != 'transaction_status' && $slug != 'transaction_cancel') {
            $tt = chk_duplicate_transaction($headers['transactionid']);

            if ($tt) {
                $response['status'] = 0;
                $response['message'] = 'This Transaction-ID is already being used. Please, change transaction ID';
            }
        }
        return $response;
    }
}

if (!function_exists('validate_login')) {
    function validate_login(&$headers)
    {
        $now = \Carbon\Carbon::now();
        $pk = DB::connection('mysql2')->table('udil_auth')
            ->where('key', '=', $headers['privatekey'])
            ->where('key_time', '>', $now)->count();

        if ($pk > 0)
            return true;

        return false;
    }
}

if (!function_exists('filter_integer')) {
    function filter_integer($val, $min, $max)
    {
        $val = trim($val, "\"' ");
        if ($min == 0 && $val == 0)
            return true;

        return filter_var(
            $val,
            FILTER_VALIDATE_INT,
            array(
                'options' => array('min_range' => $min, 'max_range' => $max)
            )
        );
    }
}

if (!function_exists('is_num')) {
    function is_num($val)
    {
        $val = trim($val, '"');
        return is_numeric($val);
    }
}

if (!function_exists('append_prefix')) {
    function append_prefix($sim_num)
    {
        $op = substr($sim_num, 0, 3);
        $no_zero = substr($sim_num, 1, 10);
        $sim_num = '92' . $no_zero . '';
        return $sim_num;
    }
}

if (!function_exists('get_meter_by_gdid')) {
    function get_meter_by_gdid($gd_id)
    {
        return Meter::where('gd_id', $gd_id)->first();
    }
}

if (!function_exists('insert_transaction_status')) {
    function insert_transaction_status($global_device_id, $msn, $transaction, $on_demand_type)
    {
        $data_transaction_status = [
            'transaction_id'   => $transaction,
            'msn'   => $msn,
            'global_device_id'   => $global_device_id,
            'command_receiving_datetime'  => now(),
            'type'   => $on_demand_type,
            'status_level' => 2,
            'status_1_datetime' => now(),
            'status_2_datetime' => now()->addSeconds(5),
        ];


        $transaction_status_id = DB::connection('mysql2')
            ->table('transaction_status')
            ->insertGetId($data_transaction_status);

        return $transaction_status_id;
    }
}

if (!function_exists('set_on_demand_read_transaction_status')) {
    function set_on_demand_read_transaction_status($is_data_read, $msn, $now, $slug, $transaction, $global_device_id, $on_demand_type, $start_time, $end_time)
    {
        $transaction_status_id = insert_transaction_status($global_device_id, $msn, $transaction, $on_demand_type);

        if ($is_data_read) {
            $ondemand_meter = [
                'ondemand_start_time' => $start_time,
                'ondemand_end_time'  => $end_time
            ];

            $upd = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $global_device_id)
                ->update($ondemand_meter);
        }

        return $transaction_status_id;
    }
}

if (!function_exists('set_wakeup_and_transaction')) {
    function set_wakeup_and_transaction($msn, $now, $slug, $transaction, $global_device_id)
    {
        $datai = [
            'wakeup_status_log_id'  => NULL,
            'msn'   => $msn,
            'reference_no'   => 0,
            'action'   => $slug,
            'req_time'   => $now,
            'req_state' => 1,
            'modem_ack_time'   => $now,
            'modem_ack_status'   => 1,
            'wakeup_sent_time'   => $now,
            'wakeup_send_status' => 1,
            'conn_time'   => NULL,
            'conn_status'   => 0,
            'completion_time'   => NULL,
            'completion_status'   => 0
        ];

        $wakeup_id = DB::connection('mysql2')
            ->table('wakeup_status_log')
            ->insertGetId($datai);

        $dataii = [
            'id'  => NULL,
            'wakeup_id'    => $wakeup_id,
            'transaction_id' => $transaction,
            'request_time' => $now,
            'type' => $slug,
            'global_device_id' => $global_device_id,
            'msn' => $msn
        ];

        DB::connection('mysql2')
            ->table('udil_transaction')
            ->insert($dataii);

        return $wakeup_id;
    }
}

if (!function_exists('code_to_slug')) {
    function code_to_slug($mti_type)
    {
        switch ($mti_type) {
            case "WSIM":
                return "update_wake_up_sim_number";
            case "WMDI":
                return "update_mdi_reset_date";
            case "WAUXR":
                return "aux_relay_operations";
            case "WSANC":
                return "sanctioned_load_control";
            case "WIPPO":
                return "update_ip_port";
            case "WMDSM":
                return "meter_data_sampling";
            case "WDMDT":
                return "update_device_metadata";
            case "WOPPO":
                return "activate_meter_optical_port";
            case "WDVTM":
                return "time_synchronization";
            case "WLSCH":
                return "load_shedding_scheduling";
            case "WTIOU":
                return "update_time_of_use";
            case "WMTST":
                return "update_meter_status";
            case "WDVCR":
                return "device_creation";
            case "WPMAL":
                return "update_major_alarms";
            default:
                return "unknown";
        }
    }
}

if (!function_exists('get_modified_transaction_status')) {
    function get_modified_transaction_status($transaction)
    {
        $columns = [
            'transaction_id AS transactionid',
            'msn',
            'global_device_id',
            'type',
            'command_receiving_datetime',
            'status_level',
            'status_1_datetime',
            'status_2_datetime',
            'status_3_datetime',
            'status_4_datetime',
            'status_5_datetime',
            'indv_status',
            'request_cancelled',
            'request_cancel_reason',
            'request_cancel_datetime',
            'response_data'
        ];

        $datag = DB::connection('mysql2')->table('transaction_status')
            ->where('transaction_id', '=', $transaction)
            ->get($columns)->toArray();

        foreach ($datag as $t => $dt) {
            $dt->type = code_to_slug($dt->type);

            if (($dt->status_5_datetime != null) && ($dt->status_4_datetime == null)) {
                $dt->status_4_datetime = date('Y-m-d H:i:s', strtotime($dt->status_5_datetime . ' -1 second'));
            }

            if (($dt->status_4_datetime != null) && ($dt->status_3_datetime == null)) {
                $dt->status_3_datetime = date('Y-m-d H:i:s', strtotime($dt->status_4_datetime . ' -1 second'));
            }

            if (($dt->status_3_datetime != null) && ($dt->status_2_datetime == null)) {
                $dt->status_2_datetime = date('Y-m-d H:i:s', strtotime($dt->status_3_datetime . ' -1 second'));
            }

            if (($dt->status_2_datetime != null) && ($dt->status_1_datetime == null)) {
                $dt->status_1_datetime = date('Y-m-d H:i:s', strtotime($dt->status_2_datetime . ' -1 second'));
            }
        }

        return $datag;
    }
}

if (!function_exists('chk_transaction_and_wakeup')) {
    function chk_transaction_and_wakeup($transaction)
    {
        $t = 0;
        $rr[$t]['status_level'] = 0;
        $rr[$t]['indv_status']  = 0;
        $rr[$t]['transactionid']  = $transaction;
        $rr[$t]['global_device_id']  = 0;
        $rr[$t]['msn']  = 0;

        $rr[$t]['request_cancelled']  = 0;
        $rr[$t]['request_cancel_reason']  = '';
        $rr[$t]['request_cancel_datetime']  = '';
        $rr[$t]['response_data']  = 'Transaction ID not exists';
        $rr[$t]['type'] = '';
        $rr[$t]['command_receiving_datetime'] = '';
        $rr[$t]['status_1_datetime'] = '';
        $rr[$t]['status_2_datetime'] = '';
        $rr[$t]['status_3_datetime'] = '';
        $rr[$t]['status_4_datetime'] = '';
        $rr[$t]['status_5_datetime'] = '';

        $datag = DB::connection('mysql2')->table('udil_transaction')
            ->join('wakeup_status_log', 'udil_transaction.wakeup_id', '=', 'wakeup_status_log.wakeup_status_log_id')
            ->where('transaction_id', '=', $transaction)
            ->get()->toArray();

        $finishx = 0;
        foreach ($datag as $t => $dt) {
            $rr[$t]['status_level'] = 2;
            $rr[$t]['indv_status']  = 0;

            $rr[$t]['request_cancelled']  = 0;
            $rr[$t]['request_cancel_reason']  = '';
            $rr[$t]['request_cancel_datetime']  = '';
            $rr[$t]['response_data']  = '';
            $rr[$t]['type'] = $dt->type;
            $rr[$t]['command_receiving_datetime'] = $dt->request_time;
            $rr[$t]['status_1_datetime'] = $dt->request_time;
            $rr[$t]['status_2_datetime'] = $dt->request_time;
            $rr[$t]['status_3_datetime'] = '';
            $rr[$t]['status_4_datetime'] = '';
            $rr[$t]['status_5_datetime'] = '';
            $rr[$t]['transactionid']  = $transaction;
            $rr[$t]['global_device_id']  = $dt->global_device_id;
            $rr[$t]['msn']  = $dt->msn;

            $m_basic = DB::connection('mysql2')->table('meter')
                ->where('global_device_id', '=', $dt->msn)
                ->get()->toArray();

            if ($m_basic[0]->type == 0) {
                if ($dt->action == 'aux_relay_operations') {
                    if ($m_basic[0]->apply_new_contactor_state == 0) {
                        $finishx = 1;
                    }
                } else if ($dt->action == 'time_synchronization') {
                    if ($m_basic[0]->super_immediate_cs == 0) {
                        $finishx = 1;
                    }
                } else if ($dt->action == 'update_device_metadata') {
                    if ($m_basic[0]->set_keepalive == 0) {
                        $finishx = 1;
                    }
                } else if ($dt->action == 'sanctioned_load_control') {
                    if ($m_basic[0]->write_contactor_param == 0) {
                        $finishx = 1;
                        $event_cd = 303;
                        $event_disc = 'Sanction Load Control Programmed';
                    }
                } else if ($dt->action == 'meter_data_sampling') {
                    if ($m_basic[0]->lp_write_interval_request == 0) {
                        $finishx = 1;
                    }
                } else if ($dt->action == 'update_wake_up_sim_number') {
                    if ($m_basic[0]->number_profile_group_id == 0) {
                        $finishx = 1;
                    }
                } else if ($dt->action == 'update_ip_port') {
                    if ($m_basic[0]->set_ip_profiles == 0) {
                        $finishx = 1;
                        $event_cd = 305;
                        $event_disc = 'IP & Port Programmed';
                    }
                } else if ($dt->action == 'update_mdi_reset_date') {
                    if ($m_basic[0]->write_mdi_reset_date == 0) {
                        $finishx = 1;
                    }
                }

                if ($finishx == 1) {
                    $now = date('Y-m-d H:i:s');
                    $now_c = date('Y-m-d H:i:s', strtotime('-26 Seconds'));
                    $now_d = date('Y-m-d H:i:s', strtotime('-7 Seconds'));
                    $rr[$t]['status_level'] = 5;
                    $rr[$t]['status_3_datetime'] = $now_c;
                    $rr[$t]['status_4_datetime'] = $now_d;
                    $rr[$t]['status_5_datetime'] = $now;
                    $rr[$t]['indv_status']  = 1;
                }
            } else {
                if ($dt->action == 'device_creation') {
                    if ($m_basic[0]->super_immediate_pq == 0) {
                        $now = date('Y-m-d H:i:s');
                        $now_c = date('Y-m-d H:i:s', strtotime('-26 Seconds'));
                        $now_d = date('Y-m-d H:i:s', strtotime('-7 Seconds'));
                        $rr[$t]['status_level'] = 5;
                        $rr[$t]['status_3_datetime'] = $now_c;
                        $rr[$t]['status_4_datetime'] = $now_d;
                        $rr[$t]['status_5_datetime'] = $now;
                        $rr[$t]['indv_status']  = 1;
                    }
                } else {
                    if ($dt->completion_status == 1) {
                        $rr[$t]['status_level'] = 5;
                        $rr[$t]['status_3_datetime'] = $dt->conn_time;
                        $rr[$t]['status_4_datetime'] = $dt->conn_time;
                        $rr[$t]['status_5_datetime'] = $dt->completion_time;
                        $rr[$t]['indv_status']  = 1;
                    }
                }
            }
        }

        return $rr;
    }
}

if (!function_exists('chk_duplicate_transaction')) {
    function chk_duplicate_transaction($transaction)
    {
        $cc = DB::connection('mysql2')->table('transaction_status')
            ->where('transaction_id', $transaction)
            ->count();

        if ($cc > 0)
            return true;
        return false;
    }
}

if (!function_exists('chk_ondemand_response')) {
    function chk_ondemand_response($transaction, $type, $msn)
    {
        for ($i = 0; $i <= 26; $i++) {
            $type = strtoupper($type);
            if ($type == 'INST') {
                $columnx = 'super_immediate_pq';
            } else if ($type == 'BILL') {
                $columnx = 'super_immediate_cb';
            } else if ($type == 'MBIL') {
                $columnx = 'super_immediate_mb';
            } else if ($type == 'EVNT') {
                $columnx = 'super_immediate_ev';
            } else if ($type == 'LPRO') {
                $columnx = 'super_immediate_lp';
            } else if ($type == 'AUXR') {
                $columnx = 'super_immediate_pq';
            } else if ($type == 'DVTM') {
                $columnx = 'super_immediate_pq';
            } else if ($type == 'WSIM') {
                $columnx = 'super_immediate_pq';
            }

            $datag = DB::connection('mysql2')->table('meter')
                ->where('msn', '=', $msn)
                ->get($columnx)->toArray();

            if ($datag[0]->$columnx == 1) {
                sleep(11);
            } else {
                return true;
                break;
            }
        }

        return false;
    }
}

if (!function_exists('read_ondemand_status_level')) {
    function read_ondemand_status_level($transaction, $type, $global_device_id)
    {
        for ($i = 0; $i <= 26; $i++) {
            $datag = DB::connection('mysql2')->table('transaction_status')
                ->where('global_device_id', $global_device_id)
                ->where('transaction_id', '=', $transaction)
                ->get('status_level')->toArray();

            $arrLength = count($datag);
            if ($arrLength > 0) {
                $status_level = $datag[0]->status_level;
                if ($status_level >= 5) {
                    return true;
                    break;
                } else {
                    sleep(11);
                }
            } else {
                sleep(11);
            }
        }

        return false;
    }
}

if (!function_exists('validate_device_creation_params')) {
    function validate_device_creation_params($params)
    {
        $mandatory = array(
            "device_identity",
            "request_datetime",
            "communication_interval",
            "device_type",
            "mdi_reset_date",
            "mdi_reset_time",
            "sim_number",
            "sim_id",
            "phase",
            "meter_type",
            "communication_mode",
            "communication_type",
            "bidirectional_device",
            "initial_communication_time"
        );
        for ($i = 0; $i < sizeof($mandatory); $i++) {
            if (!array_key_exists($mandatory[$i], $params)) {
                return "$mandatory[$i] field is mandatory";
            }

            $value = $params[$mandatory[$i]];
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                return "$mandatory[$i] field contains blank value";
            }
        }

        if (!filter_integer($params['device_type'], 1, 5)) {
            return "Invalid value for field 'device_type'. Acceptable Values are 1 - 5";
        }

        if (!filter_integer($params['mdi_reset_date'], 1, 28)) {
            return "Invalid value for field 'mdi_reset_date'. Acceptable Values are 1 - 28";
        }

        if (!is_num($params['sim_number']) || strlen($params['sim_number']) != 11) {
            return "Invalid value for field 'sim_number'. Only 11 Digit Numbers are Allowed";
        }

        if (!is_num($params['sim_id'])) {
            return "Invalid value for field 'sim_id'. Only Numbers are Allowed";
        }

        if (!filter_integer($params['phase'], 1, 3)) {
            return "Invalid value for field 'phase'. Acceptable Values are 1 - 3";
        }

        if (!filter_integer($params['meter_type'], 1, 5)) {
            return "Invalid value for field 'meter_type'. Acceptable Values are 1 - 5";
        }

        if (!filter_integer($params['communication_interval'], 0, 1440)) {
            return "Invalid value for field 'communication_interval'. Acceptable Values are 0 - 1440";
        }

        if (!is_time_valid($params['initial_communication_time'])) {
            return "Invalid value for field 'initial_communication_time'. Expected format HH:mm:ss";
        }

        if (!is_date_valid($params['request_datetime'], "Y-m-d H:i:s")) {
            return "Invalid value for field 'request_datetime'. Expected format Y-m-d H:i:s";
        }

        return "";
    }
}

if (!function_exists('validate_tiou_update')) {
    function validate_tiou_update($params)
    {
        $mandatory = array("activation_datetime", "day_profile", "week_profile", "season_profile");

        for ($i = 0; $i < sizeof($mandatory); $i++) {
            if (!isset($params[$mandatory[$i]])) {
                return "$mandatory[$i] field is mandatory";
            } else if ($params[$mandatory[$i]] == "") {
                return "$mandatory[$i] field contains blank value";
            }
        }

        $day_profiles = json_decode($params['day_profile'], true);
        if (!(json_last_error() === JSON_ERROR_NONE)) {
            return "Invalid json in day_profile field";
        }

        $week_profiles = json_decode($params['week_profile'], true);
        if (!(json_last_error() === JSON_ERROR_NONE)) {
            return "Invalid json in week_profile field";
        }

        $season_profiles = json_decode($params['season_profile'], true);
        if (!(json_last_error() === JSON_ERROR_NONE)) {
            return "Invalid json in season_profile field";
        }

        $day_profile_counter = 0;
        $day_profile_names = array();
        foreach ($day_profiles as $day_profile) {
            if (!isset($day_profile['name'])) {
                return "Day Profile field 'name' is not provided for Day Entry at Index: " . $day_profile_counter;
            }

            if (!isset($day_profile['tariff_slabs'])) {
                return "Day Profile field 'tariff_slabs' is not provided for Day Entry at Index: " . $day_profile_counter;
            }

            $day_profile_name = $day_profile['name'];
            $day_profile_names[] = $day_profile_name;
            $tariff_slabs = $day_profile['tariff_slabs'];
            if (sizeof($tariff_slabs) == 0) {
                return "No Tariff Slabs are defined for Day Profile '" . $day_profile_name . "'";
            }

            $day_profile_counter++;
        }

        $week_profile_counter = 0;
        $week_profile_names = array();
        foreach ($week_profiles as $week_profile) {
            if (!isset($week_profile['name'])) {
                return "Week Profile field 'name' is not provided for Week Entry at Index: " . $week_profile_counter;
            }

            if (!isset($week_profile['weekly_day_profile'])) {
                return "Week Profile field 'weekly_day_profile' is not provided for Week Entry at Index: " . $week_profile_counter;
            }

            $week_profile_name = $week_profile['name'];
            $week_profile_names[] = $week_profile_name;
            $week_profile_days = $week_profile['weekly_day_profile'];

            if (sizeof($week_profile_days) != 7) {
                return "Week Profile must have 7 days entries. Week Profile " . $week_profile['name'] . " contains " . sizeof($week_profile_days) . " Entries";
            }

            foreach ($week_profile_days as $week_profile_day) {
                if (!in_array($week_profile_day, $day_profile_names)) {
                    return "Week Profile '" . $week_profile_name . "' contains DayProfile named '" . $week_profile_day . "' which is not defined in DayProfiles List";
                }
            }

            $week_profile_counter++;
        }

        $season_profile_counter = 0;
        foreach ($season_profiles as $season_profile) {
            if (!isset($season_profile['name'])) {
                return "Season Profile field 'name' is not provided for Season Entry at Index: " . $season_profile_counter;
            }

            if (!isset($season_profile['week_profile_name'])) {
                return "Season Profile field 'week_profile_name' is not provided for Season Entry at Index: " . $season_profile_counter;
            }

            if (!isset($season_profile['start_date'])) {
                return "Season Profile field 'start_date' is not provided for Season Entry at Index: " . $season_profile_counter;
            }

            if (!in_array($season_profile['week_profile_name'], $week_profile_names)) {
                return "Season Profile '" . $season_profile['name'] . "' contains WeekProfile named '" . $season_profile['week_profile_name'] . "' which is not defined in WeekProfiles List";
            }

            $season_profile_counter++;
        }

        if (isset($params['holiday_profile'])) {
            $holiday_profiles = json_decode($params['holiday_profile'], true);
            if (!(json_last_error() === JSON_ERROR_NONE)) {
                return "Invalid json in holiday_profile field";
            }

            $holiday_profile_counter = 0;
            foreach ($holiday_profiles as $holiday_profile) {
                if (!isset($holiday_profile['name'])) {
                    return "Holiday Profile field 'name' is not provided for Holiday Entry at Index: " . $holiday_profile_counter;
                }

                if (!isset($holiday_profile['date'])) {
                    return "Holiday Profile field 'date' is not provided for Holiday Entry at Index: " . $holiday_profile_counter;
                }

                if (!isset($holiday_profile['day_profile_name'])) {
                    return "Holiday Profile field 'day_profile_name' is not provided for Holiday Entry at Index: " . $holiday_profile_counter;
                }

                if (!in_array($holiday_profile['day_profile_name'], $day_profile_names)) {
                    return "Holiday Profile '" . $holiday_profile['name'] . "' contains DayProfile named '" . $holiday_profile['day_profile_name'] . "' which is not defined in DayProfiles List";
                }

                $holiday_profile_counter++;
            }
        }

        return "";
    }
}

if (!function_exists('validate_load_shedding_slabs')) {
    function validate_load_shedding_slabs($str)
    {
        $slabs = json_decode($str, true);

        if (!(json_last_error() === JSON_ERROR_NONE)) {
            return false;
        }

        $all_okay = true;

        foreach ($slabs as $slab) {
            if (!isset($slab['action_time'])) {
                $all_okay = false;
            }

            if (!isset($slab['relay_operate'])) {
                $all_okay = false;
            }
        }

        return $all_okay;
    }
}

if (!function_exists('is_valid_sim_number')) {
    function is_valid_sim_number($number)
    {
        if ($number === null) {
            return false;
        }

        $number = trim((string)$number, "\"' ");

        if (strlen($number) !== 11) {
            return false;
        }

        return ctype_digit($number);
    }
}

if (!function_exists('is_valid_msn')) {
    function is_valid_msn($msn)
    {
        if ($msn === null) {
            return false;
        }

        $msn = trim((string)$msn, "\"' ");
        return $msn !== '' && ctype_digit($msn);
    }
}

if (!function_exists('is_valid_global_device_id')) {
    function is_valid_global_device_id($global_device_id)
    {
        if ($global_device_id === null) {
            return false;
        }

        $value = trim((string)$global_device_id, "\"' ");
        if ($value === '') {
            return false;
        }

        return (bool)preg_match('/^[A-Za-z0-9_-]+$/', $value);
    }
}

if (!function_exists('has_invalid_msns')) {
    function has_invalid_msns($global_device_input)
    {
        $devices = [];

        if (is_array($global_device_input)) {
            $devices = $global_device_input;
        } elseif (is_string($global_device_input)) {
            $decoded = json_decode($global_device_input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $devices = $decoded;
            } else {
                $devices = [$global_device_input];
            }
        } else {
            return false;
        }

        if (!is_array($devices)) {
            return false;
        }

        foreach ($devices as $device) {
            if (is_object($device)) {
                $device = (array)$device;
            }

            if (is_array($device)) {
                if (array_key_exists('global_device_id', $device) && !is_valid_global_device_id($device['global_device_id'])) {
                    return true;
                }

                if (array_key_exists('msn', $device) && !is_valid_msn($device['msn'])) {
                    return true;
                }

                if (array_key_exists('dsn', $device) && !is_valid_msn($device['dsn'])) {
                    return true;
                }
            } else {
                if (!is_valid_global_device_id($device)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('validate_device_identity_list')) {
    function validate_device_identity_list($device_identity_input)
    {
        $result = [
            'error' => null,
            'devices' => null,
        ];

        if (is_string($device_identity_input)) {
            $decoded = json_decode($device_identity_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['error'] = 'device_identity must be a JSON array of devices';
                return $result;
            }
            $devices = $decoded;
        } elseif (is_array($device_identity_input)) {
            $devices = $device_identity_input;
        } else {
            $result['error'] = 'device_identity must be a JSON array of devices';
            return $result;
        }

        if (!is_array($devices)) {
            $result['error'] = 'device_identity must be a JSON array of devices';
            return $result;
        }

        if (empty($devices)) {
            $result['error'] = 'device_identity must contain at least one device';
            return $result;
        }

        foreach ($devices as $device) {
            if (is_object($device)) {
                $device = (array)$device;
            }

            if (!is_array($device) || !isset($device['global_device_id']) || !isset($device['dsn'])) {
                $result['error'] = 'Each device entry must contain both global_device_id and dsn';
                return $result;
            }

            $gdid = $device['global_device_id'];
            $dsn = $device['dsn'];

            if (trim((string)$gdid) === '' || trim((string)$dsn) === '') {
                $result['error'] = 'Each device entry must contain both global_device_id and dsn';
                return $result;
            }

            if (!is_valid_global_device_id($gdid)) {
                $result['error'] = "Invalid value for field 'global_device_id'. Only letters, numbers, underscore, and hyphen are allowed";
                return $result;
            }

            if (!is_valid_msn($dsn)) {
                $result['error'] = "Invalid value for field 'dsn'. Only digits are allowed";
                return $result;
            }
        }

        $result['devices'] = $devices;
        return $result;
    }
}

if (!function_exists('get_from_meter_single_result')) {
    function get_from_meter_single_result($global_device_id, $columns)
    {
        $data_on = DB::connection('mysql2')->table('meter')
            ->where('global_device_id',  $global_device_id)
            ->get($columns)->first();
        return $data_on;
    }
}

if (!function_exists('update_on_transaction_success')) {
    function update_on_transaction_success($slug, $gd_id, $timestamp = null)
    {
        $datauuu = [
            'last_command' => $slug,
            'last_command_datetime' => $timestamp ?? now(),
        ];

        $in = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', $gd_id)
            ->update($datauuu);
    }
}

if (!function_exists('get_transaction_cancel_response')) {
    function get_transaction_cancel_response($global_device_id, $msn, $slug_type)
    {
        return [
            "global_device_id" => $global_device_id,
            "msn" => $msn,
            "indv_status" => "1",
            "remarks" => "Transaction Successfully Cancelled for type: " . code_to_slug($slug_type),
        ];
    }
}

if (!function_exists('is_time_valid')) {
    function is_time_valid($val)
    {
        $val = trim($val, '"');
        return preg_match('#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#', $val);
    }
}

if (!function_exists('is_date_valid')) {
    function is_date_valid($date, $format)
    {
        $date = trim($date, "\"' ");
        if ($date === '') {
            return false;
        }

        $dt = \DateTime::createFromFormat($format, $date);
        if ($dt === false || $dt->format($format) !== $date) {
            return false;
        }

        $year = (int)$dt->format('Y');
        return $year >= 1900;
    }
}

if (!function_exists('get_json_from_string')) {
    function get_json_from_string($str)
    {
        if ($str != null) {
            $json_obj = json_decode($str, true);
            if ((json_last_error() === JSON_ERROR_NONE)) {
                return $json_obj;
            } else {
                return $str;
            }
        } else {
            return $str;
        }
    }
}

if (!function_exists('initialize_api_request')) {
    function initialize_api_request()
    {
        session_start();
        $http_status = 200;
        $chk = false;
        $content = '';

        $get_first = function ($x) {
            return $x[0];
        };

        $headers = array_map($get_first, request()->headers->all());
        $form_params = request()->all();

        return [
            'headers' => $headers,
            'form_params' => $form_params,
            'http_status' => $http_status
        ];
    }
}

if (!function_exists('validate_api_request')) {
    function validate_api_request($slug, $headers, $form_params)
    {
        // Initializing Request
        $resp = apply_common_validation($slug, $headers, $form_params);

        $transaction = (isset($headers['transactionid']) ? $headers['transactionid'] : '0');
        if ($resp['status'] == 0) {
            $resp['transactionid'] = $transaction;
            return [
                'valid' => false,
                'response' => $resp,
                'http_status' => 400
            ];
        } else {
            if ($slug != 'authorization_service') {
                $sess = validate_login($headers);
                if (!$sess) {
                    $response['status'] = 0;
                    $response['transactionid'] = $transaction;
                    $response['message'] = 'Private Key Expired. Please, regenerate new private key';
                    return [
                        'valid' => false,
                        'response' => $response,
                        'http_status' => 401
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'transaction' => $transaction
        ];
    }
}

if (!function_exists('get_empty_response')) {
    function get_empty_response($status, $message, $transaction_id)
    {
        $resp_fixed['status'] = $status;
        $resp_fixed['message'] = $message;

        if ($transaction_id !== 0) {
            $resp_fixed['transactionid'] = $transaction_id;
        }

        return $resp_fixed;
    }
}

if (!function_exists('parseDevices')) {

    function parseDevices($form_params)
    {
        $all_devices = [];

        if (!isset($form_params['global_device_id'])) {
            return $all_devices;
        }

        // Try JSON decode
        $devices = json_decode($form_params['global_device_id'], true);

        // If invalid JSON → treat as single device string
        if (is_null($devices) || $devices === '') {
            $devices = [strval($form_params['global_device_id'])];
        }

        // Ensure array format
        if (!is_array($devices)) {
            $devices = [$devices];
        }

        foreach ($devices as $dvc) {

            $msn_count = DB::connection('mysql2')->table('meter')
                ->where('global_device_id', $dvc)
                ->count();

            $msn = $msn_count > 0
                ? DB::connection('mysql2')->table('meter')->where('global_device_id', $dvc)->value('msn')
                : 0;

            $all_devices[] = [
                'global_device_id' => (string) $dvc,
                'msn'              => $msn
            ];
        }

        return $all_devices;
    }
}

if (!function_exists('applyCommonValidation')) {

    function applyCommonValidation($slug, &$headers, &$form_params)
    {
        $response['status'] = 1;

        $services_list = [
            'authorization_service',
            'on_demand_data_read',
            'on_demand_parameter_read',
            'aux_relay_operations',
            'update_wake_up_sim_number',
            'time_synchronization',
            'update_mdi_reset_date',
            'transaction_status',
            'update_device_metadata',
            'device_creation',
            'meter_data_sampling',
            'update_meter_status',
            'sanctioned_load_control',
            'update_ip_port',
            'activate_meter_optical_port',
            'load_shedding_scheduling',
            'update_time_of_use',
            'parameterization_cancellation',
            'transaction_cancel',
            'apms_tripping_events'
        ];

        if (!in_array($slug, $services_list)) {
            $response['status'] = 0;
            $response['message'] = 'Requested Service is not implemented yet';
        } elseif ($slug != 'authorization_service' && (!isset($headers['privatekey']) || !isset($headers['transactionid']))) {
            $response['status'] = 0;
            $response['message'] = 'Private Key or Transaction ID is missing in Request';
        } elseif ($slug != 'authorization_service' && $slug != 'device_creation' && $slug != 'transaction_status') {
            if (!isset($form_params['global_device_id'])) {
                $response['status'] = 0;
                $response['message'] = 'Global Device ID is required';
            } elseif (has_invalid_msns($form_params['global_device_id'])) {
                $response['status'] = 0;
                $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
            }
        } elseif ($slug != 'authorization_service' && $slug != 'on_demand_parameter_read' && $slug != 'transaction_status' && $slug != 'on_demand_data_read' && $slug != 'parameterization_cancellation') {
            if (!isset($form_params['request_datetime'])) {
                $response['status'] = 0;
                $response['message'] = 'Request Datetime Field is required';
            } elseif (!isDateValid($form_params['request_datetime'], "Y-m-d H:i:s")) {
                $response['status'] = 0;
                $response['message'] = 'Request Date is invalid';
            }
        } elseif ($slug == 'on_demand_parameter_read' || $slug == 'on_demand_data_read') {
            if (is_array(json_decode($form_params['global_device_id'], true))) {
                $response['status'] = 0;
                $response['message'] = 'Single Global Device ID is required';
            }
            if (!isset($form_params['type'])) {
                $response['status'] = 0;
                $response['message'] = 'Type field is required';
            }

            $meter_status = DB::connection('mysql2')->table('meter')
                ->where('global_device_id', json_decode($form_params['global_device_id'], true))
                ->get()->toArray();

            if ($meter_status[0]->status == 0) {
                $response['status'] = 0;
                $response['message'] = 'This meter having msn "' . $meter_status[0]->msn . '" is marked de-activated. Please, activate it first';
            }
        }

        if ($slug != 'authorization_service' && $slug != 'transaction_status' && $slug != 'transaction_cancel') {
            if (chkDuplicateTransaction($headers['transactionid'])) {
                $response['status'] = 0;
                $response['message'] = 'This Transaction-ID is already being used. Please, change transaction ID';
            }
        }

        return $response;
    }


    // function applyCommonValidation($slug, array &$headers, array &$formParams)
    // {
    //     $response = ['status' => 1];

    //     // Supported services
    //     $services = [
    //         'authorization_service','on_demand_data_read','on_demand_parameter_read',
    //         'aux_relay_operations','update_wake_up_sim_number','time_synchronization',
    //         'update_mdi_reset_date','transaction_status','update_device_metadata',
    //         'device_creation','meter_data_sampling','update_meter_status',
    //         'sanctioned_load_control','update_ip_port','activate_meter_optical_port',
    //         'load_shedding_scheduling','update_time_of_use','parameterization_cancellation',
    //         'transaction_cancel','apms_tripping_events'
    //     ];

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 1) Validate Service Name
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!in_array($slug, $services)) {
    //         return ['status' => 0, 'message' => 'Requested Service is not implemented yet'];
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 2) PrivateKey + TransactionID (Skip for Authorization)
    //     |--------------------------------------------------------------------------
    //     */
    //     if ($slug !== 'authorization_service') {
    //         if (empty($headers['privatekey']) || empty($headers['transactionid'])) {
    //             return ['status' => 0, 'message' => 'Private Key or Transaction ID is missing in Request'];
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 3) Global Device ID Required (Except few services)
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!in_array($slug, ['authorization_service','device_creation','transaction_status'])) {
    //         if (empty($formParams['global_device_id'])) {
    //             return ['status' => 0, 'message' => 'Global Device ID is required'];
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Normalize global_device_id (supports string / JSON / array)
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!empty($formParams['global_device_id'])) {

    //         $raw = $formParams['global_device_id'];

    //         // Case A: JSON string
    //         $decoded = json_decode($raw, true);

    //         if (json_last_error() === JSON_ERROR_NONE) {
    //             $deviceList = $decoded;
    //         }
    //         // Case B: raw array
    //         else if (is_array($raw)) {
    //             $deviceList = $raw;
    //         }
    //         // Case C: plain string
    //         else {
    //             $deviceList = [$raw];
    //         }

    //         // Normalize objects e.g. [{global_device_id: "123"}]
    //         if (is_array($deviceList) && isset($deviceList[0]) && is_array($deviceList[0]) && isset($deviceList[0]['global_device_id'])) {
    //             $deviceList = [$deviceList[0]['global_device_id']];
    //         }

    //         // Save normalized list back
    //         $formParams['global_device_id'] = $deviceList;
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 4) Request Datetime Validation
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!in_array($slug, [
    //         'authorization_service','on_demand_parameter_read','transaction_status',
    //         'on_demand_data_read','parameterization_cancellation'
    //     ])) {

    //         if (empty($formParams['request_datetime'])) {
    //             return ['status' => 0, 'message' => 'Request Datetime Field is required'];
    //         }

    //         if (!isDateValid($formParams['request_datetime'], 'Y-m-d H:i:s')) {
    //             return ['status' => 0, 'message' => 'Request Date is invalid'];
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 5) on_demand_* Special Validation
    //     |--------------------------------------------------------------------------
    //     */
    //     if (in_array($slug, ['on_demand_parameter_read','on_demand_data_read'])) {

    //         $deviceList = $formParams['global_device_id'];

    //         // Must contain exactly 1 device
    //         if (!is_array($deviceList) || count($deviceList) !== 1) {
    //             return ['status' => 0, 'message' => 'Single Global Device ID is required'];
    //         }

    //         $deviceId = $deviceList[0]; // final usable ID

    //         // Type field required
    //         if (empty($formParams['type'])) {
    //             return ['status' => 0, 'message' => 'Type field is required'];
    //         }

    //         // Meter status check
    //         $meter = DB::connection('mysql2')
    //             ->table('meter')
    //             ->where('global_device_id', $deviceId)
    //             ->first();

    //         if ($meter && $meter->status == 0) {
    //             return [
    //                 'status'  => 0,
    //                 'message' => 'This meter having msn "' . $meter->msn . '" is deactivated.'
    //             ];
    //         }
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | 6) Duplicate Transaction Check
    //     |--------------------------------------------------------------------------
    //     */
    //     if (!in_array($slug, ['authorization_service','transaction_status','transaction_cancel'])) {

    //         $transactionId = $headers['transactionid'];

    //         if (function_exists('chkDuplicateTransaction') && chkDuplicateTransaction($transactionId)) {
    //             return [
    //                 'status'  => 0,
    //                 'message' => 'This Transaction-ID is already being used. Please, change transaction ID'
    //             ];
    //         }
    //     }

    //     return $response;
    // }


}

if (!function_exists('validateLogin')) {
    function validateLogin(&$headers)
    {
        $now = Carbon::now();
        $pk = DB::connection('mysql2')->table('udil_auth')
            ->where('key', $headers['privatekey'])
            ->where('key_time', '>', $now)
            ->count();

        return $pk > 0;
    }
}

if (!function_exists('isDateValid')) {
    function isDateValid($date, $format = 'Y-m-d H:i:s')
    {
        return is_date_valid($date, $format);
    }
}

if (!function_exists('chkDuplicateTransaction')) {
    function chkDuplicateTransaction($transaction)
    {
        return DB::connection('mysql2')->table('transaction_status')
            ->where('transaction_id', $transaction)
            ->exists();
    }
}

if (!function_exists('filterInteger')) {
    /**
     * Validate if a value is an integer within a specified range.
     *
     * @param mixed $val
     * @param int $min
     * @param int $max
     * @return bool
     */
    function filterInteger($val, $min, $max)
    {
        $val = trim($val, "\"' ");

        if ($min == 0 && $val == 0) {
            return true;
        }

        return filter_var(
            $val,
            FILTER_VALIDATE_INT,
            [
                'options' => ['min_range' => $min, 'max_range' => $max]
            ]
        ) !== false;
    }
}


if (!function_exists('readOndemandStatusLevel')) {
    function readOndemandStatusLevel($transaction, $type, $global_device_id)
    {
        for ($i = 0; $i <= 26; $i++) {
            $datag = DB::connection('mysql2')->table('transaction_status')
                ->where('global_device_id', $global_device_id)
                ->where('transaction_id', $transaction)
                ->get('status_level')
                ->toArray();

            $arrLength = count($datag);
            if ($arrLength > 0) {
                $status_level = $datag[0]->status_level;
                if ($status_level >= 5) {
                    return true;
                } else {
                    sleep(11);
                }
            } else {
                sleep(11);
            }
        }

        return false;
    }
}

if (!function_exists('setOnDemandReadTransactionStatus')) {
    function setOnDemandReadTransactionStatus($is_data_read, $msn, $now, $slug, $transaction, $global_device_id, $on_demand_type, $start_time, $end_time)
    {
        // Insert transaction status
        $transaction_status_id = insert_transaction_status($global_device_id, $msn, $transaction, $on_demand_type);

        if ($is_data_read) {
            $ondemand_meter = [
                'ondemand_start_time' => $start_time,
                'ondemand_end_time'  => $end_time
            ];

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $global_device_id)
                ->update($ondemand_meter);
        }

        return $transaction_status_id;
    }
}


if (!function_exists('setWakeupAndTransaction')) {

    function setWakeupAndTransaction($msn, $now, $slug, $transaction, $global_device_id)
    {
        // Insert into wakeup_status_log
        $dataWakeup = [
            'wakeup_status_log_id' => null,
            'msn'                  => $msn,
            'reference_no'         => 0,
            'action'               => $slug,
            'req_time'             => $now,
            'req_state'            => 1,
            'modem_ack_time'       => $now,
            'modem_ack_status'     => 1,
            'wakeup_sent_time'     => $now,
            'wakeup_send_status'   => 1,
            'conn_time'            => null,
            'conn_status'          => 0,
            'completion_time'      => null,
            'completion_status'    => 0
        ];

        $wakeup_id = DB::connection('mysql2')
            ->table('wakeup_status_log')
            ->insertGetId($dataWakeup);

        // Insert into udil_transaction
        $dataTransaction = [
            'id'              => null,
            'wakeup_id'       => $wakeup_id,
            'transaction_id'  => $transaction,
            'request_time'    => $now,
            'type'            => $slug,
            'global_device_id'=> $global_device_id,
            'msn'             => $msn
        ];

        DB::connection('mysql2')
            ->table('udil_transaction')
            ->insert($dataTransaction);

        return $wakeup_id;
    }
}
