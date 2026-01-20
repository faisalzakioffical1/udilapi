<?php

namespace App\Locale;

use DB;
use GuzzleHttp\Client;
use App\Models\Meter;
use Carbon\Carbon;

class Watchdog extends Controller
{

  public function __construct()
  {
    //parent::__construct();
    //$val = env('PROJECT_TYPE');
    //$this->project_type = (in_array($val, $this->supported_projects)) ? $val : "unsupported";

  }

  //private $project_type;
  private $supported_projects = array('mti_meter', 'mti_apms');

  // For Write Requests
  // Columns of Meter Visuals should NOT be updated by Wrapper.
  // If for any reason, the service is not updating the Columns in Meter Visuals
  // Then Enable this field and the wrapper will update the values in Meter Visuals

  private $update_meter_visuals_for_write_services = false;

  private $update_udil_log_for_write_services = false;

  // region Constant-Logs

  private $meter_not_exists = "This meter does not exists in MDC. Please, create it first";

  // endregion

  public function index($slug)
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

    //  if ($this->project_type == "unsupported") {
    //    $resp = $this->get_empty_response(0, "Unsupported MDC", 0);
    //    return response()->json( $resp , 409);
    //  }

    // Initializing Request
    $resp = $this->applyCommonValidation($slug, $headers, $form_params);

    $transaction = (isset($headers['transactionid']) ? $headers['transactionid'] : '0');
    if ($resp['status'] == 0) {
      $resp['transactionid'] = $transaction;
      $http_status = 400;
    } else {
      if ($slug != 'authorization_service') {
        $sess = $this->validateLogin($headers);
        if (!$sess) {
          $response['status'] = 0;
          $response['transactionid'] = $transaction;
          $response['message'] = 'Private Key Expired. Please, regenerate new private key';
          $resp = $response;
          $http_status = 401;
        } else {
          $chk = $this->applyRequestFilters($slug, $headers, $form_params);
          $http_status = $chk['http_status'];
          unset($chk['http_status']);
          $resp = $chk;
        }
      } else {
        $chk = $this->applyRequestFilters($slug, $headers, $form_params);
        $http_status = $chk['http_status'];
        unset($chk['http_status']);
        $resp = $chk;
      }
    }

    return response()->json($resp, $http_status);
  }

  public function get_empty_response($status, $message, $transaction_id)
  {
    $resp_fixed['status'] = $status;
    $resp_fixed['message'] = $message;

    if ($transaction_id === 0) {
    } else {
      $resp_fixed['transactionid'] = $transaction_id;
    }

    return $resp_fixed;
  }

  public function applyRequestFilters($slug, &$headers, &$form_params)
  {

    $global_device_ids = [];
    $datar = [];
    $response['status'] = 1;
    $response['http_status'] = 200;
    $response['message'] = '';
    $transaction = (isset($headers['transactionid']) ? $headers['transactionid'] : '0');

    if ($slug != 'authorization_service')
      $response['transactionid'] = $transaction;

    $now_30 = \Carbon\Carbon::now()->addMinutes(30);
    $now_day = \Carbon\Carbon::now()->addMinutes(1441);
    $now__15 = \Carbon\Carbon::now()->subMinute(15);
    $now = \Carbon\Carbon::now();

    if (isset($form_params['global_device_id'])) {
      $devices = json_decode($form_params['global_device_id'], true);

      /*
      if (sizeof($devices)==0) {
        $devices = $form_params['global_device_id'];
      }
      */

      if (is_null($devices) || $devices == '') {
        $devices[] = strval($form_params['global_device_id']);
      }

      if (!is_array($devices)) {
        $device = array();
        $device[] = $devices;
        $devices = $device;
      }
      $all_devices = array();

      foreach ($devices as $dvc) {

        $msn_count = DB::connection('mysql2')->table('meter')
          ->where('global_device_id',  $dvc)
          ->count();

        if ($msn_count > 0) {
          $msn = DB::connection('mysql2')->table('meter')
            ->where('global_device_id', $dvc)
            ->value('msn');
        } else {
          $msn = 0;
        }

        $all_devices[] = [
          'global_device_id' => (string) $dvc,
          'msn'              => $msn
        ];
      }
      //dd($devices , $msn, $msn_count, $form_params['global_device_id']);
    }

    try {

      switch ($slug) {

        // region authorization_service

        case 'authorization_service':
          if (!isset($headers['username']) || !isset($headers['password']) || !isset($headers['code'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Required Parameters like Username, Password or Code are not present in header';
            break;
          } else {
            $form_params['username'] = $headers['username'];
            $form_params['password'] = $headers['password'];
            $form_params['code'] = $headers['code'];

            if ($headers['username'] == 'mti' && $headers['password'] == 'Mti@786#' && $headers['code'] == '36') {

              $private_key = uniqid() . uniqid() . uniqid();
              $data[] = [
                'id'     => NULL,
                'key'     => $private_key,
                'key_time'   => $now_30
              ];

              $in = DB::connection('mysql2')
                ->table('udil_auth')
                ->insert($data);

              if ($in) {
                $response['status'] = 1;
                $response['http_status'] = 200;
                $response['privatekey'] = $private_key;
                $response['message'] = 'Provided Private key is valid for 30 minutes and it will expire at ' . $now_30;
              } else {
                $response['status'] = 0;
                $response['http_status'] = 500;
                $response['message'] = 'Database Connectivity Failed. Please, retry';
              }
              break;
            } else {
              $response['status'] = 0;
              $response['http_status'] = 401;
              $response['message'] = 'Authentication Failed. Please re-try with correct username / password';
              break;
            }
          }
          break;

        // endregion

        // region aux_relay_operations

        case 'aux_relay_operations':

          if (!isset($form_params['relay_operate'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'relay_operate Field is missing';
            break;
          } else if (!$this->filterInteger($form_params['relay_operate'], 0, 1)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid value provided in relay_operate Field';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {

                /*if (substr($dvc['msn'], 0, 4) == 3699) {
                  $datar[] = [
                      "global_device_id" => $dvc['global_device_id'],
                      "msn" => $dvc['msn'],
                      "indv_status" => "0",
                      "remarks" => "This meter has no relay facility",
                  ];
                }
                else */
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $curr_time = date('H:i:s');
                  //$curr_time = "23:58:00";
                  $special_treatment_type = 10; // 10 for Normal, 20 for Parameter Cancel and reprogram LSCH
                  $lsch_state = $this->get_from_meter_single_result($dvc['global_device_id'], ['load_shedding_schedule_id']);

                  $load_shedding_schedule_exists = true;
                  $load_shedding_schedule_list = array();
                  $load_shedding_schedule_id = null;
                  if (($lsch_state == null) || ($lsch_state->load_shedding_schedule_id == null) || ($lsch_state->load_shedding_schedule_id == 105)) {
                    $load_shedding_schedule_exists = false;
                  } else {
                    $load_shedding_schedule_id = $lsch_state->load_shedding_schedule_id;
                    $load_shedding_schedule_list = DB::connection('mysql2')->table('load_shedding_detail')
                      ->where('schedule_id',  $lsch_state->load_shedding_schedule_id)
                      ->orderBy('action_time', 'ASC')
                      ->get(['action_time', 'relay_operate'])->toArray();

                    // No Data Exists for Provided Loadshedding ID so proceed with regular operation
                    if (($load_shedding_schedule_list == null) || (sizeof($load_shedding_schedule_list) == 0)) {
                      $load_shedding_schedule_exists = false;
                    }
                  }

                  $current_time_slab = null;
                  $next_activation_time = null;

                  if ($load_shedding_schedule_exists) {

                    for ($i = 0; $i < sizeof($load_shedding_schedule_list); $i++) {
                      if ($curr_time > $load_shedding_schedule_list[$i]->action_time) {
                        $current_time_slab = $i;
                      }
                    }

                    if (is_null($current_time_slab)) {
                      $current_time_slab = sizeof($load_shedding_schedule_list) - 1;
                    }

                    if ($current_time_slab == sizeof($load_shedding_schedule_list) - 1) {
                      // If it is the last Entry then
                      // use First Entry with next Day if current time is before 23:59:59
                      // Otherwise use First Entry of same Day

                      if ($curr_time > $load_shedding_schedule_list[$current_time_slab]->action_time) {
                        $next_activation_time = date('Y-m-d', strtotime(' +1 day')) . " " . $load_shedding_schedule_list[0]->action_time;
                      } else {
                        // Next Day already started. Time greater then 00:00:00
                        $next_activation_time = date('Y-m-d') . " " . $load_shedding_schedule_list[0]->action_time;
                      }
                    } else {
                      $next_activation_time = date("Y-m-d") . " " . $load_shedding_schedule_list[$current_time_slab + 1]->action_time;
                    }

                    $load_shedding_relay_status = $load_shedding_schedule_list[$current_time_slab]->relay_operate;

                    if (($form_params['relay_operate'] == 0) && ($load_shedding_relay_status == 1)) {
                      $special_treatment_type = 20;
                    } else if (($form_params['relay_operate'] == 1) && ($load_shedding_relay_status == 0)) {
                      $special_treatment_type = 20;
                    }
                  }

                  // No Special Treatment
                  if ($special_treatment_type == 10) {

                    $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WAUXR");
                    //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                    $contactor_lock = ($form_params['relay_operate'] == 1) ? 0 : 1;
                    /*
                    if ($form_params['relay_operate'] ==1 )
                      $contactor_lock = 0;
                    else
                      $contactor_lock = 1;
            */

                    $datau = [
                      'apply_new_contactor_state' => 1,
                      'new_contactor_state'     => $form_params['relay_operate']
                      //'contactor_lock' 	=> $contactor_lock,
                      //'is_contactor' 	=> 1,
                      //'wakeup_request_id' => $log
                    ];

                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datau);

                    //                  if ($this->project_type == 'mti_meter') {
                    // SCH: pq power quaity inst
                    if ($form_params['relay_operate'] == 1) {
                      // Switch to Non-KeepAlive
                      $keepAlive = 0;
                      $sch_pq = 2;  //2 mean on every connection fetch/pull that type of data
                      $sch_cb = 1;
                      $sch_mb = 2;
                      $sch_ev = 2;
                      $sch_lp = 2;
                      $sch_lp2 = 2;
                      $sch_lp3 = 2;
                      $save_sch_pq = 2;
                      $sch_ss = 2;
                      $sch_cs = 2;
                      $kas = '00:01:00';
                      $set_keepalive = 2;
                      $interval_pq = '00:15:00';
                      $interval_cb = '00:01:00';
                      $interval_mb = '00:01:00';
                      $interval_ev = '00:01:00';
                      $interval_lp = '00:01:00';
                      $interval_cs = '00:01:00';
                      $meter_class = 'non-keepalive';
                    } else {
                      // Switch to KeepAlive
                      $keepAlive = 1;
                      $sch_pq = 3;   //3 mean fetch/pull  data only on schedule interval
                      $sch_cb = 1;
                      $sch_mb = 3;
                      $sch_ev = 3;
                      $sch_lp = 3;
                      $sch_lp2 = 3;
                      $sch_lp3 = 3;
                      $save_sch_pq = 2;
                      $sch_ss = 2;
                      $sch_cs = 3;
                      $kas = '00:00:10';
                      $meter_class = 'keepalive';
                      $set_keepalive = 1;
                      $interval_pq = '00:15:00';
                      $interval_cb = '00:15:00';
                      $interval_mb = '00:15:00';
                      $interval_ev = '00:05:00';
                      $interval_lp = '00:15:00';
                      $interval_cs = '00:15:00';
                    }

                    $datae = array(
                      //'tbe1_write_request_id' => $id_com_interval,
                      'class' => $meter_class,
                      'type' => $keepAlive,
                      'sch_pq' => $sch_pq,
                      'interval_pq' => $interval_pq,
                      'sch_cb' => $sch_cb,
                      'interval_cb' => $interval_cb,
                      'sch_mb' => $sch_mb,
                      'interval_mb' => $interval_mb,
                      'sch_ev' => $sch_ev,
                      'interval_ev' => $interval_ev,
                      'sch_lp' => $sch_lp,
                      'sch_lp2' => $sch_lp2,
                      'sch_lp3' => $sch_lp3,
                      'interval_lp' => $interval_lp,
                      'kas_interval' => $kas,
                      'save_sch_pq' => $save_sch_pq,
                      'save_interval_pq' => $interval_pq,
                      'sch_ss' => $sch_ss,
                      'sch_cs' => $sch_cs,
                      'interval_cs' => $interval_cs,
                      'set_keepalive' => $set_keepalive,
                      'super_immediate_pq' => '0',
                      //'bidirectional_device' => $form_params['bidirectional_device'],
                      //'dw_normal_mode_id' => $dw_normal,
                      //'dw_alternate_mode_id' => $dw_alternate,
                      //'energy_param_id' => $energy_param_id,
                      //'max_billing_months' => $max_billing_months,

                      // Previously not present in Update Device Metadata
                      //'association_id' => $association_id,
                      //'schedule_plan' => $schedule_plan,
                      //'read_logbook' => $read_logbook,
                      //'encryption_key' => $encryption_key,
                      //'authentication_key' => $authentication_key,
                      //'master_key' => $master_key,
                      //'dds_compatible' => $dds_compatible,
                      //'max_events_entries' => $max_events_entries,
                      //'lp_invalid_update' => $lp_invalid_update,
                      //'lp2_invalid_update' => $lp_invalid_update,
                      //'lp3_invalid_update' => $lp_invalid_update,
                      //'ev_invalid_update' => $ev_invalid_update,
                      //'load_profile_group_id' => $load_profile_group_id,
                    );

                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datae);
                    //                  }

                  } else if (($special_treatment_type == 20)) {

                    // STEP 01: Parameter Cancel First
                    $datau = [
                      'load_shedding_schedule_id' => 105,
                      'write_load_shedding_schedule'   => 1,
                    ];

                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datau);

                    sleep(41);

                    // STEP 02: Program Aux Relay Operation

                    $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WAUXR");
                    $datau = [
                      'apply_new_contactor_state' => 1,
                      'new_contactor_state'     => $form_params['relay_operate']
                    ];
                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datau);

                    // STEP 03: Reprogram Loadshedding with Next Interval

                    $cc = date('YmdHis');
                    $time_replace = str_replace(array('-', ':', ' '), '', $next_activation_time);
                    $event_query = "
                        CREATE
                        EVENT `LSCH_" . $dvc['global_device_id'] . "_" . $time_replace . "_" . $cc . "`
                        ON SCHEDULE AT '" . $next_activation_time . "'
                        DO
                        BEGIN
                        UPDATE meter SET load_shedding_schedule_id = " . $load_shedding_schedule_id . ", write_load_shedding_schedule = 1
                        WHERE global_device_id = '" . $dvc['global_device_id'] . "';
                        END
                        ";

                    DB::connection('mysql2')->unprepared($event_query);
                  }

                  /*
                                                                            $datav = [
                                                                                    'contactor_id' => NULL,
                                                                                    'msn' 		=> $dvc['msn'],
                                                                                    'reference_number' 	=> 786,
                                                                                    'customer_id' 	=> 1,
                                                                                    'command' => $form_params['relay_operate'],
                                                                                    'command_type' 	=> 2,
                                                                                    'command_date_time' 	=> $now,
                                                                                    'activation_time' 	=> $now,
                                                                                    'expiry_time' 	=> $now_day,
                                                                                    'status' 	=> 1,
                                                                                ];
                                                                            $cn = DB::connection('mysql2')
                                                                                    ->table('contactor_control_data')
                                                                                    ->insertGetId( $datav );
                                    */

                  if ($this->update_meter_visuals_for_write_services) {
                    $datav = [
                      'auxr_status' => $form_params['relay_operate'],
                      'auxr_datetime' => $now
                    ];

                    $inx = DB::connection('mysql2')
                      ->table('meter_visuals')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datav);
                  }

                  //                                    if ($form_params['relay_operate'] == 0) {
                  //                                      $this->insert_event($dvc['msn'], $dvc['global_device_id'],
                  //                                          203, 'Contactor OFF', $now);
                  //                                    } else {
                  //                                      $this->insert_event($dvc['msn'], $dvc['global_device_id'],
                  //                                          202, 'Contactor ON', $now);
                  //                                    }

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);

                  $response['status'] = 1;
                  $response['http_status'] = 200;
                  $rr = ($form_params['relay_operate'] == 0) ? 'OFF' : 'ON';
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Relay will be Turned $rr Soon",
                  ];

                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                }
              }

              $response['message'] = 'Relay will be turned ON or OFF against meters having indv_status equal to 1';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region update_time_of_use

        case 'update_time_of_use':

          $tiou_error_status = $this->validate_tiou_update($form_params);
          
          if ($tiou_error_status != "") {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = $tiou_error_status;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WTIOU");
                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                  $data1 = [
                    'pk_id' => NULL,
                    //'description' => '?',
                    'activation_date' => $form_params['activation_datetime'],
                  ];

                  $activity_calendar_id = $this->insert_data('param_activity_calendar', $data1);
                  //                  $activity_calendar_id = DB::connection('mysql2')
                  //                      ->table('param_activity_calendar')
                  //                      ->insertGetId( $data1 );

                  // Associative Arrays, which holds Profile Name as Key, and integer Counter as value
                  $day_profile_names = array();
                  $week_profile_names = array();

                  // Day Profile
                  $dp_counter = 0;
                  $initial_day_profile_counter = 1;
                  $day_profiles = json_decode($form_params['day_profile'], true);
                  foreach ($day_profiles as $day_profile) {

                    $dp_counter++;
                    $day_profile_names[$day_profile['name']] = $dp_counter;
                    $tariff_slabs = $day_profile['tariff_slabs'];

                    $data_day_profile = [
                      'pk_id' => NULL,
                      'calendar_id' => $activity_calendar_id,
                      'day_profile_id' => $dp_counter,
                      'day_profile_name' => $day_profile['name'],
                    ];
                    $id1 = $this->insert_data('param_day_profile', $data_day_profile);

                    //                    $id1 = DB::connection('mysql2')
                    //                        ->table('param_day_profile')
                    //                        ->insertGetId( $data_day_profile );

                    for ($i = 0; $i < sizeof($tariff_slabs); $i++) {
                      $data_day_profile_slots = [
                        'pk_id' => NULL,
                        'calendar_id' => $activity_calendar_id,
                        'day_profile_id' => $dp_counter,
                        'switch_time' => $tariff_slabs[$i],
                        'tariff' => $i + 1,
                      ];

                      $id1 = $this->insert_data('param_day_profile_slots', $data_day_profile_slots);

                      //                      $id1 = DB::connection('mysql2')
                      //                          ->table('param_day_profile_slots')
                      //                          ->insertGetId( $data_day_profile_slots );
                    }
                  }

                  // Week Profile
                  $wp_counter = 0;
                  $week_profiles = json_decode($form_params['week_profile'], true);
                  foreach ($week_profiles as $week_profile) {

                    $wp_counter++;
                    $week_profile_days = $week_profile['weekly_day_profile'];
                    // sizeof($week_profile_days) == 7 and this is checked in validation Method

                    $db_week_profile = [
                      'pk_id' => NULL,
                      'calendar_id' => $activity_calendar_id,
                      'week_profile_id' => $wp_counter,
                      'day1_profile_id' => $day_profile_names[$week_profile_days[0]],
                      'day2_profile_id' => $day_profile_names[$week_profile_days[1]],
                      'day3_profile_id' => $day_profile_names[$week_profile_days[2]],
                      'day4_profile_id' => $day_profile_names[$week_profile_days[3]],
                      'day5_profile_id' => $day_profile_names[$week_profile_days[4]],
                      'day6_profile_id' => $day_profile_names[$week_profile_days[5]],
                      'day7_profile_id' => $day_profile_names[$week_profile_days[6]],
                    ];

                    $id_week = $this->insert_data('param_week_profile', $db_week_profile);

                    //                    $id_week = DB::connection('mysql2')
                    //                        ->table('param_week_profile')
                    //                        ->insertGetId( $db_week_profile );

                    //                  $week_profile_names[$week_profile['name']] = $id_week;
                    $week_profile_names[$week_profile['name']] = $wp_counter;
                  }

                  // Season Profile
                  $season_profiles = json_decode($form_params['season_profile'], true);
                  foreach ($season_profiles as $season_profile) {

                    $parsed_date = date_parse_from_format("d-m", $season_profile['start_date']);
                    $db_season_profile = [
                      'pk_id' => NULL,
                      'calendar_id' => $activity_calendar_id,
                      'week_profile_id' => $week_profile_names[$season_profile['week_profile_name']],
                      'start_date' => "2023-" . $parsed_date['month'] . "-" . $parsed_date['day'],
                    ];

                    $id_season = $this->insert_data('param_season_profile', $db_season_profile);
                    //                    $id_season = DB::connection('mysql2')
                    //                        ->table('param_season_profile')
                    //                        ->insertGetId( $db_season_profile );
                  }

                  // Holiday Profile
                  if (isset($form_params['holiday_profile'])) {
                    $holiday_profiles = json_decode($form_params['holiday_profile'], true);
                    foreach ($holiday_profiles as $holiday_profile) {

                      $parsed_date = date_parse_from_format("d-m", $holiday_profile['date']);
                      $db_holiday_profile = [
                        'pk_id' => NULL,
                        'calendar_id' => $activity_calendar_id,
                        'day_profile_id' => $day_profile_names[$holiday_profile['day_profile_name']],
                        'special_date' => "2023-" . $parsed_date['month'] . "-" . $parsed_date['day'],
                      ];

                      $id_holiday = $this->insert_data('param_special_day_profile', $db_holiday_profile);
                      //                      $id_holiday = DB::connection('mysql2')
                      //                          ->table('param_special_day_profile')
                      //                          ->insertGetId( $db_holiday_profile );
                    }
                  } else {
                    $db_holiday_profile = [
                      'pk_id' => NULL,
                      'calendar_id' => $activity_calendar_id,
                      'day_profile_id' => $initial_day_profile_counter,
                      'special_date' => "2022-01-01",
                    ];

                    $id_holiday = $this->insert_data('param_special_day_profile', $db_holiday_profile);
                    //                    $id_holiday = DB::connection('mysql2')
                    //                        ->table('param_special_day_profile')
                    //                        ->insertGetId( $db_holiday_profile );
                  }

                  $datau = [
                    'activity_calendar_id' => $activity_calendar_id,
                  ];

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($datau);

                  $this->insert_event(
                    $dvc['msn'],
                    $dvc['global_device_id'],
                    306,
                    'Time of Use Programmed',
                    $now
                  );

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);

                  $response['status'] = 1;
                  $response['http_status'] = 200;

                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Time of Use will be programmed in meter upon connection",
                  ];

                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);
                }
              }
              $response['message'] = 'Time of Use will be programmed against meters having indv_status equal to 1';
              $response['data'] = $datar;
            }
          }

          break;

        // endregion

        // region load_shedding_scheduling

        case 'load_shedding_scheduling':

          if (!isset($form_params['start_datetime']) || !isset($form_params['end_datetime']) || !isset($form_params['load_shedding_slabs'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'start_datetime, end_datetime & load_shedding_slabs fields are mandatory and required';
          } else if (!$this->is_date_valid($form_params['start_datetime'], "Y-m-d H:i:s") || !$this->is_date_valid($form_params['end_datetime'], "Y-m-d H:i:s")) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid dates are provided in start_datetime & end_datetime fields';
          } else if (!$this->validate_load_shedding_slabs($form_params['load_shedding_slabs'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'action_time OR relay_operate field is missing from load_shedding_slabs';
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WLSCH");
                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                  $datav = [
                    'schedule_id' => NULL,
                    'name' => 'udil parameterization of loadshedding',
                    'activation_date' => $form_params['start_datetime'],
                    'expiry_date' => $form_params['end_datetime'],
                  ];

                  $cn = DB::connection('mysql2')
                    ->table('load_shedding_schedule')
                    ->insertGetId($datav);

                  $slabs = json_decode($form_params['load_shedding_slabs'], true);

                  foreach ($slabs as $slab) {

                    $dataw = [
                      //'pk_id' => $cn,
                      'schedule_id' => $cn,
                      'action_time' => $slab['action_time'],
                      'relay_operate' => $slab['relay_operate'],
                    ];

                    $cnn = DB::connection('mysql2')
                      ->table('load_shedding_detail')
                      ->insertGetId($dataw);
                  }

                  $datau = [
                    'load_shedding_schedule_id' => $cn,
                    'write_load_shedding_schedule'   => 1,
                  ];

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($datau);

                  $this->insert_event(
                    $dvc['msn'],
                    $dvc['global_device_id'],
                    304,
                    'Load Shedding Schedule Programmed',
                    $now
                  );

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);

                  $response['status'] = 1;
                  $response['http_status'] = 200;

                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Load Shedding Schedule will be programmed in meter upon connection",
                  ];

                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);
                }
              }

              $response['message'] = 'Load Shedding Schedule will be programmed against meters having indv_status equal to 1';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region sanctioned_load_control

        case 'sanctioned_load_control':

          if (!isset($form_params['load_limit'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'load_limit Field is missing';
            break;
          } else if (!isset($form_params['maximum_retries'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'maximum_retries Field is missing';
            break;
          } else if (!isset($form_params['retry_interval'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'retry_interval Field is missing';
            break;
          } else if (!isset($form_params['threshold_duration'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'threshold_duration Field is missing';
            break;
          } else if (!isset($form_params['retry_clear_interval'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'retry_clear_interval Field is missing';
            break;
          } else if (!is_numeric($form_params['maximum_retries']) || !is_numeric($form_params['retry_interval']) || !is_numeric($form_params['retry_clear_interval']) || !is_numeric($form_params['load_limit']) || !is_numeric($form_params['threshold_duration'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Only Numeric Fields are allowed';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                /*if (substr($dvc['msn'], 0, 4) == 3699) {
                                    $datar[] = [
                                        "global_device_id" => $dvc['global_device_id'],
                                        "msn" => $dvc['msn'],
                                        "indv_status" => "0",
                                        "remarks" => "This meter has no relay facility",
                                    ];
                }
                else */
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WSANC");
                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);


                  $datav = [
                    'contactor_param_id' => NULL,
                    //'contactor_param_name' => 'UDIL',
                    'contactor_param_name' => 'udil parameterization',
                    'retry_count' => $form_params['maximum_retries'],
                    'retry_auto_interval_in_sec' => $form_params['retry_interval'],
                    'on_retry_expire_auto_interval_min' => $form_params['retry_clear_interval'] / 60,
                    'write_monitoring_time' => 1,
                    'write_monitoring_time_t2' => 1,
                    'write_monitoring_time_t3' => 1,
                    'write_monitoring_time_t4' => 1,
                    'monitering_time_over_load' => $form_params['threshold_duration'],
                    'monitering_time_over_load_t2' => $form_params['threshold_duration'],
                    'monitering_time_over_load_t3' => $form_params['threshold_duration'],
                    'monitering_time_over_load_t4' => $form_params['threshold_duration'],

                    'write_limit_over_load_total_kW_t1' => 1,
                    'write_limit_over_load_total_kW_t2' => 1,
                    'write_limit_over_load_total_kW_t3' => 1,
                    'write_limit_over_load_total_kW_t4' => 1,
                    'limit_over_load_total_kW_t1' => $form_params['load_limit'],
                    'limit_over_load_total_kW_t2' => $form_params['load_limit'],
                    'limit_over_load_total_kW_t3' => $form_params['load_limit'],
                    'limit_over_load_total_kW_t4' => $form_params['load_limit'],


                    // Previously Disabled and added again
                    'contactor_on_pulse_time_ms' => 100,
                    'contactor_off_pulse_time_ms' => 100,
                    'interval_btw_contactor_state_change_sec' => 7,
                    'power_up_delay_to_change_state_sec' => 15,
                    'interval_to_contactor_failure_status_sec' => 300,
                    'optically_connect' => 0,
                    'optically_disconnect' => 0,
                    'tariff_change' => 0,
                    'is_retry_automatic_or_switch' => 1,
                    'reconnect_by_switch_on_expire' => 0,
                    'reconnect_automatic_on_expire' => 0,

                    'turn_contactor_off_overload_t1' => 1,
                    'tunr_contactor_off_overload_t2' => 1,
                    'turn_contactor_off_overload_t3' => 0,
                    'turn_contactor_off_overload_t4' => 0,

                    'write_contactor_param' => 1,


                    /*
                                          'contactor_on_pulse_time_ms' => 100,
                                          'contactor_off_pulse_time_ms' => 100,
                                          'interval_btw_contactor_state_change_sec' => 7,
                                          'power_up_delay_to_change_state_sec' => 15,
                                          'interval_to_contactor_failure_status_sec' => 300,
                                          'optically_connect' => 0,
                                          'optically_disconnect' => 0,
                                          'tariff_change' => 0,
                                          'is_retry_automatic_or_switch' => 1,
                                          'reconnect_by_switch_on_expire' => 0,
                                          'reconnect_automatic_on_expire' => 0,

                                          'turn_contactor_off_overload_t1' => 1,
                                          'tunr_contactor_off_overload_t2' => 1,
                                          'turn_contactor_off_overload_t3' => 1,
                                          'turn_contactor_off_overload_t4' => 1,
                                        */


                  ];

                  $cn = DB::connection('mysql2')
                    ->table('contactor_params')
                    ->insertGetId($datav);

                  $datau = [
                    'write_contactor_param' => 1,
                    'contactor_param_id'   => $cn,
                    //'is_contactor' 	=> 1,
                    //'wakeup_request_id' => $log
                  ];

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($datau);

                  $this->insert_event(
                    $dvc['msn'],
                    $dvc['global_device_id'],
                    303,
                    'Sanction Load Control Programmed',
                    $now
                  );

                  if ($this->update_udil_log_for_write_services) {
                    $datauuu = [
                      'msn' => $dvc['msn'],
                      'global_device_id' => $dvc['global_device_id'],
                      'sanc_datetime' => $now,
                      'sanc_load_limit' => $form_params['load_limit'],
                      'sanc_maximum_retries' => $form_params['maximum_retries'],
                      'sanc_retry_interval' => $form_params['retry_interval'],
                      'sanc_threshold_duration' => $form_params['threshold_duration'],
                      'sanc_retry_clear_interval' => $form_params['retry_clear_interval']
                    ];

                    $dataui = [
                      'sanc_load_control' => $datauuu
                    ];

                    $in = DB::connection('mysql2')
                      ->table('udil_log')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($dataui);
                  }

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);

                  $response['status'] = 1;
                  $response['http_status'] = 200;

                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Sanctioned Load Control function will be programmed in meter upon connection",
                  ];

                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);
                  if ($this->update_meter_visuals_for_write_services) {
                    $datavb = [
                      'sanc_datetime'     => $now,
                      'sanc_load_limit'    => $form_params['load_limit'],
                      'sanc_maximum_retries'  => $form_params['maximum_retries'],
                      'sanc_retry_interval'  => $form_params['retry_interval'],
                      'sanc_threshold_duration'  => $form_params['threshold_duration'],
                      'sanc_retry_clear_interval'  => $form_params['retry_clear_interval'],
                      'last_command'  => $slug,
                      'last_command_datetime'  => $now,
                      'last_command_resp'  => $response,
                      'last_command_resp_datetime' => date('Y-m-d H:i:s')
                    ];
                    DB::connection('mysql2')
                      ->table('meter_visuals')
                      ->where('global_device_id',  $dvc['global_device_id'])
                      ->update($datavb);
                  }
                }
              }

              $response['message'] = 'Sanctioned Load Control function will be programmed against meters having indv_status equal to 1';
              $response['data'] = $datar;
            }
          }

          break;

        // endregion

        // region update_ip_port

        case 'update_ip_port':

          if (!isset($form_params['primary_ip_address'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'primary_ip_address Field is missing';
            break;
          } else if (!isset($form_params['secondary_ip_address'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'secondary_ip_address Field is missing';
            break;
          } else if (!isset($form_params['primary_port'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'primary_port Field is missing';
            break;
          } else if (!isset($form_params['secondary_port'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'secondary_port Field is missing';
            break;
          } else if (!is_numeric($form_params['primary_port']) || !is_numeric($form_params['secondary_port']) || !filter_var($form_params['primary_ip_address'], FILTER_VALIDATE_IP) || !filter_var($form_params['secondary_ip_address'], FILTER_VALIDATE_IP)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid IP OR Port Fields are provided. Please, correct them';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {

                $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WIPPO");
                //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                $datav = [
                  'ip_profile_id' => NULL,
                  'ip_1' => $form_params['primary_ip_address'],
                  'ip_2' => $form_params['secondary_ip_address'],
                  //'ip_3' => $form_params['primary_ip_address'],
                  //'ip_4' => $form_params['secondary_ip_address'],
                  'w_tcp_port_1' => $form_params['primary_port'],
                  'w_tcp_port_2' => $form_params['secondary_port'],
                  //'w_tcp_port_3' => $form_params['primary_port'],
                  //'w_tcp_port_4' => $form_params['secondary_port'],
                  'w_udp_port' => 28525,    // Not Specified in Document
                  'h_tcp_port' => 26978,    // Not Specified in Document
                  'h_udp_port' => 26988     // Not Specified in Document
                ];

                $cn = DB::connection('mysql2')
                  ->table('ip_profile')
                  ->insertGetId($datav);

                $datau = [
                  'set_ip_profiles' => $cn,
                  //'wakeup_request_id' => $log
                ];

                $in = DB::connection('mysql2')
                  ->table('meter')
                  ->where('global_device_id',  $dvc['global_device_id'])
                  ->update($datau);


                $datauuu = [
                  'msn'           => $dvc['msn'],
                  'global_device_id'     => $dvc['global_device_id'],
                  'ippo_datetime'     => $now,
                  'ippo_primary_ip_address'  => $form_params['primary_ip_address'],
                  'ippo_secondary_ip_address'  => $form_params['secondary_ip_address'],
                  'ippo_primary_port'      => $form_params['primary_port'],
                  'ippo_secondary_port'    => $form_params['secondary_port']
                ];

                $dataui = [
                  'update_ip_port' => $datauuu
                ];

                $in = DB::connection('mysql2')
                  ->table('udil_log')
                  ->where('global_device_id',  $dvc['global_device_id'])
                  ->update($dataui);

                $this->insert_event(
                  $dvc['msn'],
                  $dvc['global_device_id'],
                  305,
                  'IP & Port Programmed',
                  $now
                );

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $response['status'] = 1;
                $response['http_status'] = 200;

                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "1",
                  "remarks" => "New IP & Port will be programmed in meter upon connection",
                ];

                if ($this->update_meter_visuals_for_write_services) {
                  $datavb = [
                    'ippo_datetime' => $now,
                    'ippo_primary_ip_address' => $form_params['primary_ip_address'],
                    'ippo_secondary_ip_address' => $form_params['secondary_ip_address'],
                    'ippo_primary_port' => $form_params['primary_port'],
                    'ippo_secondary_port' => $form_params['secondary_port'],
                    'last_command' => $slug,
                    'last_command_datetime' => $now,
                    'last_command_resp' => $response,
                    'last_command_resp_datetime' => date('Y-m-d H:i:s')
                  ];

                  DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->update($datavb);
                }


                //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);
              }
            }
            $response['message'] = 'New IP & Port will be programmed against meters having indv_status equal to 1';
            $response['data'] = $datar;
          }
          break;

        // endregion

        // region activate_meter_optical_port

        case 'activate_meter_optical_port':

          if (!isset($form_params['optical_port_on_datetime'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Optical Port ON Datetime is missing';
            break;
          } else if (!isset($form_params['optical_port_off_datetime'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Optical Port OFF Datetime is missing';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {

                $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WOPPO");
                //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                $data_oppo = [
                  'update_optical_port_access' => 1,
                  'optical_port_start_time' => $form_params['optical_port_on_datetime'],
                  'optical_port_end_time' => $form_params['optical_port_off_datetime'],
                ];

                $in = DB::connection('mysql2')
                  ->table('meter')
                  ->where('global_device_id',  $dvc['global_device_id'])
                  ->update($data_oppo);

                $datauuu = [
                  'msn' => $dvc['msn'],
                  'global_device_id' => $dvc['global_device_id'],
                  'oppo_datetime' => $now,
                  'oppo_optical_port_on_datetime' => $form_params['optical_port_on_datetime'],
                  'oppo_optical_port_off_datetime' => $form_params['optical_port_off_datetime'],
                ];

                if ($this->update_udil_log_for_write_services) {

                  $dataui = [
                    'optical_port' => $datauuu
                  ];

                  $in = DB::connection('mysql2')
                    ->table('udil_log')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->update($dataui);
                }

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $response['status'] = 1;
                $response['http_status'] = 200;

                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "1",
                  "remarks" => "Optical Port ON/OFF Time will be updated",
                ];

                if ($this->update_meter_visuals_for_write_services) {
                  DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->update($datauuu);
                }


                //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

              }
            }

            $response['message'] = 'Optical Port ON/OFF Time will be updated against meters having indv_status equal to 1';
            $response['data'] = $datar;
          }
          break;

        // endregion

        // region time_synchronization

        case 'time_synchronization':

          if (!isset($form_params['request_datetime'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'request_datetime Field is missing';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WDVTM");

                  $datau = [
                    'max_cs_difference'   => 999999999,
                    'super_immediate_cs'   => 1,
                    'base_time_cs'       => $now__15,
                    //'wakeup_request_id' => $log
                  ];

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($datau);

                  //$this->insert_event($dvc['msn'], $dvc['global_device_id'], 201, 'Time Synchronization', $now);

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                  $response['status'] = 1;
                  $response['http_status'] = 200;
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Time will be synced Soon",
                  ];
                }
              }
              $response['message'] = 'Time will be synchronized against meters having indv_status equal to 1. Make sure MDC has correct time & timezone settings';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region apms_tripping_events

        case 'apms_tripping_events':

          if (isset($form_params['type']))
            $form_params['type'] = strtolower($form_params['type']);

          $supported_apms = array('ovfc', 'uvfc', 'ocfc', 'olfc', 'vufc', 'pffc', 'cufc', 'hapf');

          /*
          if ($this->project_type != 'mti_apms') {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = $slug.' is only supported for APMS';
            break;
          }
          else */
          if (
            !isset($form_params['type']) || !isset($form_params['critical_event_threshold_limit']) ||
            !isset($form_params['critical_event_log_time']) || !isset($form_params['tripping_event_threshold_limit']) ||
            !isset($form_params['tripping_event_log_time']) || !isset($form_params['enable_tripping']) || !isset($form_params['request_datetime'])
          ) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'One or more mandatory fields are missing';
            break;
          } else if (!in_array(($form_params['type']), $supported_apms)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', $supported_apms);
            break;
          } else if (!$this->filterInteger($form_params['enable_tripping'], 0, 1)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Field enable_tripping can only have values 0-1';
            break;
          } else if (!$this->filterInteger($form_params['critical_event_log_time'], 0, 86399)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'critical_event_log_time can only have values between 0 - 86399';
            break;
          } else if (!$this->filterInteger($form_params['tripping_event_log_time'], 0, 86399)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'tripping_event_log_time can only have values between 0 - 86399';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $temp_type = "W" . strtoupper($form_params['type']);
                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, $temp_type);

                  $data_apms = [
                    'type'                           => $form_params['type'],
                    'critical_event_threshold_limit' => $form_params['critical_event_threshold_limit'],
                    'critical_event_log_time'        => gmdate("H:i:s", $form_params['critical_event_log_time']),
                    'tripping_event_threshold_limit' => $form_params['tripping_event_threshold_limit'],
                    'tripping_event_log_time'        => gmdate("H:i:s", $form_params['tripping_event_log_time']),
                    'enable_tripping'                => $form_params['enable_tripping'],
                  ];

                  $id_apms_event = DB::connection('mysql2')
                    ->table('param_apms_tripping_events')
                    ->insertGetId($data_apms);

                  $data_u = array('write_' . $form_params['type'] => $id_apms_event);

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($data_u);

                  /*
                  if ($this->update_meter_visuals_for_write_services) {
                    $datav = [
                        'meter_visual_column' => $meter_visual_column,
                    ];

                    $inx = DB::connection('mysql2')
                        ->table('meter_visuals')
                        ->where('global_device_id', $dvc['global_device_id'])
                        ->update($datav);
                  }

                  $this->insert_event($dvc['msn'], $dvc['global_device_id'], 307, 'Wakeup SIM Programmed', $now);
*/

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                  $response['status'] = 1;
                  $response['http_status'] = 200;
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "APMS Threshold Limits will be programmed in meter upon communication",
                  ];
                }
              }
              $response['message'] = 'APMS Threshold Limits will be programmed in meters with indv_status = 1';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region update_wake_up_sim_number

        case 'update_wake_up_sim_number':

          if (!isset($form_params['wakeup_number_1']) || !isset($form_params['wakeup_number_2']) || !isset($form_params['wakeup_number_3'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'wakeup_number_1 , 2 & 3 all fields are required';
            break;
          } else if ((!$this->is_valid_sim_number($form_params['wakeup_number_1'])) || (!$this->is_valid_sim_number($form_params['wakeup_number_2'])) || (!$this->is_valid_sim_number($form_params['wakeup_number_3']))) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Only 11 digit wakeup number is allowed';
            break;
          } else {
            //                        $form_params['wakeup_number_1'] = $this->append_prefix($form_params['wakeup_number_1']);
            //                        $form_params['wakeup_number_2'] = $this->append_prefix($form_params['wakeup_number_2']);
            //                        $form_params['wakeup_number_3'] = $this->append_prefix($form_params['wakeup_number_3']);

            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  /*
                                                          $number_profile = DB::connection('mysql2')->table('number_profile_params')
                                                                            ->max('number_profile_group_id');

                                                          ++$number_profile;

                                                          for($i=1; $i<6; $i++){
                                                              $j = 1;
                                                              $k = 1;
                                                              $l = 1;
                                                              $m = 1;
                                                              $n = 0;
                                                              $o = 0;

                                                              if($i==1)
                                                                  $numx = $form_params['wakeup_number_1'];
                                                              else if($i==2)
                                                                  $numx = $form_params['wakeup_number_2'];
                                                              else if($i==3)
                                                                  $numx = $form_params['wakeup_number_3'];
                                                              else if($i==4)
                                                                  $numx = '923004009347';
                                                              else{
                                                                  $numx = '';
                                                                  $j = 0;
                                                                  $k = 0;
                                                                  $l = 0;
                                                                  $m = 0;
                                                                  $n = 1;
                                                                  $o = 1;
                                                              }

                                                              $datai = [
                                                                  'sr_no' 	=> NULL,
                                                                  'number_profile_group_id' 	=> $number_profile,
                                                                  'unique_id' 	=> $i,
                                                                  'wakeup_on_voice_call_id' 	=> 1,
                                                                  'wakeup_on_sms_id' 	=> 1,
                                                                  'number' 	=> $numx,
                                                                  'verify_password' 	=> $m,
                                                                  'wakeup_on_sms' 	=> $j,
                                                                  'wakeup_on_voice_call' 	=> $k,
                                                                  'accept_params_in_wakeup_sms' 	=> $l,
                                                                  'allow_2way_sms_communication' 	=> 0,
                                                                  'reject_with_attend' 	=> $n,
                                                                  'is_aynonymous' 			=> $o
                                                              ];

                                                          $in = DB::connection('mysql2')
                                                                  ->table('number_profile_params')
                                                                  ->insert( $datai );

                                                          }
                  */
                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WSIM");

                  //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);
                  $datau = [
                    'wakeup_no1'   => $form_params['wakeup_number_1'],
                    'wakeup_no2'   => $form_params['wakeup_number_2'],
                    'wakeup_no3'   => $form_params['wakeup_number_3'],
                    'set_wakeup_profile_id'   => 1,
                    'number_profile_group_id'   => 1,
                    //'number_profile_group_id' 	=> $number_profile
                    //'wakeup_request_id' => $log
                  ];

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($datau);

                  if ($this->update_meter_visuals_for_write_services) {

                    $datav = [
                      'wsim_wakeup_number_1' => $form_params['wakeup_number_1'],
                      'wsim_wakeup_number_2' => $form_params['wakeup_number_2'],
                      'wsim_wakeup_number_3' => $form_params['wakeup_number_3'],
                      'wsim_datetime' => $now
                    ];

                    $inx = DB::connection('mysql2')
                      ->table('meter_visuals')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datav);
                  }

                  $this->insert_event(
                    $dvc['msn'],
                    $dvc['global_device_id'],
                    307,
                    'Wakeup SIM Programmed',
                    $now
                  );

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                  $response['status'] = 1;
                  $response['http_status'] = 200;
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Provided Wakeup Numbers will be programmed in meter upon communication",
                  ];
                }
              }
              $response['message'] = 'Wakeup Numbers will be programmed in meters with indv_status = 1. Try Wakeup by Call for fast connectivity';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region on_demand_data_read

        case 'on_demand_data_read':

          if (isset($form_params['type']))
            $form_params['type'] = strtoupper($form_params['type']);

          $typx = array('INST', 'BILL', 'MBIL', 'EVNT', 'LPRO');

          if (!isset($form_params['start_datetime']) || !isset($form_params['end_datetime']) || !isset($form_params['type'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'start_datetime, end_datetime & type fields are mandatory and required';
            break;
          } else if (!$this->is_date_valid($form_params['start_datetime'], "Y-m-d H:i:s") || !$this->is_date_valid($form_params['end_datetime'], "Y-m-d H:i:s")) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid dates are provided in start_datetime & end_datetime fields';
            break;
          } else if (!in_array(($form_params['type']), $typx)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'type field has invalid value. Only these fields are applicable INST, BILL, MBIL, EVNT, LPRO';
            break;
          } else {
            $dvc = $all_devices[0];
            if ($dvc['msn'] == 0) {
              $datar[] = [
                "global_device_id" => $dvc['global_device_id'],
                "msn" => $dvc['msn'],
                "indv_status" => "0",
                "remarks" => $this->meter_not_exists,
              ];
            } else {

              $wakeup = $this->setOnDemandReadTransactionStatus(true, $dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id'], $form_params['type'], $form_params['start_datetime'], $form_params['end_datetime']);
              //$wakeup = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

              $limitx = 1;
              $typxx = $form_params['type'];
              if ($typxx == 'LPRO') {
                $datau = [
                  'super_immediate_lp' => 1,
                  'wakeup_request_id'  => $wakeup
                ];
                $tablex = 'load_profile_data';
                $limitx = 5;
              } else if ($typxx == 'BILL') {
                $datau = [
                  'super_immediate_cb' => 1,
                  'wakeup_request_id'  => $wakeup
                ];
                $tablex = 'billing_data';
              } else if ($typxx == 'MBIL') {
                $datau = [
                  'super_immediate_mb' => 1,
                  'wakeup_request_id'  => $wakeup
                ];
                $tablex = 'monthly_billing_data';
                $limitx = 2;
              } else if ($typxx == 'EVNT') {
                $datau = [
                  'super_immediate_ev' => 1,
                  'wakeup_request_id'  => $wakeup
                ];
                $tablex = 'events';
                $limitx = 10;
              } else {
                $datau = [
                  'super_immediate_pq' => 1,
                  'wakeup_request_id'  => $wakeup
                ];
                $tablex = 'instantaneous_data';
              }


              /*							$in = DB::connection('mysql2')
                                                                ->table('meter')
                                                                ->where('global_device_id', $dvc['global_device_id'])
                                                                ->update( $datau );*/

              $response['status'] = 1;
              $response['http_status'] = 200;

              $wakeup_resp = $this->readOndemandStatusLevel($transaction, $typxx, $dvc['global_device_id']);

              //$wakeup_resp = $this->chkOndemandResponse($transaction, $typxx, $dvc['msn']);
              //$wakeup_resp = true;

              if ($wakeup_resp) {

                $response['status'] = 1;
                $response['http_status'] = 200;
                $response['message'] = 'On Demand data fetched Successfully';

                //DB::connection('mysql2')->enableQueryLog();
                $data_on = DB::connection('mysql2')->table($tablex)
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->orderBy('db_datetime', 'DESC')->limit($limitx)->get()->toArray();
                // dd(DB::connection('mysql2')->getQueryLog());

                $datar = $data_on;

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
              } else {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => "Network Error, Meter didn't communicated in Maximum allowed threshold of 5.5 minutes",
                ];
              }
            }

            $response['message'] = 'On demand data of requested meter';
            $response['data'] = $datar;
          }

          break;

        // endregion

        // region on_demand_parameter_read

        case 'on_demand_parameter_read':

          if (isset($form_params['type']))
            $form_params['type'] = strtoupper($form_params['type']);

          $typx = array(
            'AUXR',
            'DVTM',
            'SANC',
            'LSCH',
            'TIOU',
            'IPPO',
            'MDSM',
            'OPPO',
            'WSIM',
            'MSIM',
            'MTST',
            'DMDT',
            'MDI',
            'OVFC',
            'UVFC',
            'OCFC',
            'OLFC',
            'VUFC',
            'PFFC',
            'CUFC',
            'HAPF'
          );

          if (!in_array(($form_params['type']), $typx)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', $typx);
            break;
          } else {

            $dvc = $all_devices[0];
            if ($dvc['msn'] == 0) {
              $datar[] = [
                "global_device_id" => $dvc['global_device_id'],
                "msn" => $dvc['msn'],
                "indv_status" => "0",
                "remarks" => $this->meter_not_exists,
              ];
            } else {

              $wakeup = $this->setOnDemandReadTransactionStatus(false, $dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id'], $form_params['type'], "0", "0");
              //$wakeup = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

              $limitx = 1;
              $typxx = $form_params['type'];
              $datau = [
                'super_immediate_pq' => 1,
                'wakeup_request_id'  => $wakeup
              ];

              $tablex = 'meter_visuals';
              $limitx = 1;

              /*
              $in = DB::connection('mysql2')
                  ->table('meter')
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->update( $datau );
*/

              $response['status'] = 1;
              $response['http_status'] = 200;

              $wakeup_resp = $this->readOndemandStatusLevel($transaction, $typxx, $dvc['global_device_id']);
              //$wakeup_resp = $this->chkOndemandResponse($transaction, $typxx, $dvc['msn']);
              //$wakeup_resp = true;

              if ($wakeup_resp) {

                $response['status'] = 1;
                $response['http_status'] = 200;
                $response['message'] = 'On Demand parameter fetched Successfully';

                if ($typxx == 'AUXR') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'auxr_status', 'auxr_datetime'])
                    ->toArray();
                } else if ($typxx == 'DVTM') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'dvtm_datetime', 'dvtm_meter_clock'])
                    ->toArray();
                  //->get(['global_device_id','msn','mdc_read_datetime as dvtm_datetime', 'meter_datetime as dvtm_meter_clock'])
                } else if ($typxx == 'SANC') {

                  $mins_id = -1;
                  $data_cpid = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['contactor_param_id'])->first();

                  if (!is_null($data_cpid)) {

                    $cp_int_id = $data_cpid->contactor_param_id;
                    if (!is_null($cp_int_id)) {

                      $oreaim = DB::connection('mysql2')
                        ->table('contactor_params')
                        ->where('contactor_param_id',  $cp_int_id)
                        ->get(['on_retry_expire_auto_interval_min'])
                        ->first();

                      if (!is_null($oreaim)) {
                        $mins_id = $oreaim->on_retry_expire_auto_interval_min;
                      }
                    }
                  }

                  if ($mins_id != -1) {
                    $data_u1 = [
                      'sanc_retry_clear_interval' => ($mins_id * 60)
                    ];
                    $in_u1 = DB::connection('mysql2')
                      ->table('meter_visuals')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($data_u1);
                  }

                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get([
                      'global_device_id',
                      'msn',
                      'sanc_datetime',
                      'sanc_load_limit',
                      'sanc_maximum_retries',
                      'sanc_retry_interval',
                      'sanc_threshold_duration',
                      'sanc_retry_clear_interval'
                    ])
                    ->toArray();
                } else if ($typxx == 'LSCH') {
                  $data_on_temp = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'lsch_datetime', 'lsch_start_datetime', 'lsch_end_datetime', 'lsch_load_shedding_slabs'])
                    ->toArray();

                  foreach ($data_on_temp as $t => $dt) {
                    $dt->lsch_load_shedding_slabs = $this->get_json_from_string($dt->lsch_load_shedding_slabs);
                  }

                  $data_on = $data_on_temp;
                } else if ($typxx == 'TIOU') {
                  $data_on_temp = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'tiou_datetime', 'tiou_day_profile', 'tiou_week_profile', 'tiou_season_profile', 'tiou_holiday_profile', 'tiou_activation_datetime'])
                    ->toArray();

                  foreach ($data_on_temp as $t => $dt) {
                    $dt->tiou_day_profile = $this->get_json_from_string($dt->tiou_day_profile);
                    $dt->tiou_week_profile = $this->get_json_from_string($dt->tiou_week_profile);
                    $dt->tiou_season_profile = $this->get_json_from_string($dt->tiou_season_profile);
                    $dt->tiou_holiday_profile = $this->get_json_from_string($dt->tiou_holiday_profile);
                  }
                  $data_on = $data_on_temp;
                } else if ($typxx == 'IPPO') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'ippo_datetime', 'ippo_primary_ip_address', 'ippo_secondary_ip_address', 'ippo_primary_port', 'ippo_secondary_port'])->toArray();
                } else if ($typxx == 'MDSM') {
                  /*
                                    $data_on = DB::connection('mysql2')->table($tablex)
                                        ->where('global_device_id',  $dvc['global_device_id'])
                                        ->get( [ 'global_device_id','msn','mdsm_datetime', 'mdsm_activation_datetime', 'mdsm_data_type', 'mdsm_sampling_interval', 'mdsm_sampling_initial_time' ] )->toArray();
*/

                  $data_on_0 = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'mdsm_datetime'])->first();

                  $mdsm_dt = null;
                  if ($data_on_0) {
                    $mdsm_dt = $data_on_0->mdsm_datetime;
                  }






                  /*

                  // MDSM : LPRO. From Meter Table
                  'lp_interval_activation_datetime AS mdsm_activation_datetime'
                  'lp_write_interval AS mdsm_sampling_interval'
                  'lp_interval_initial_time AS mdsm_sampling_initial_time'

                  // MDSM : INST. From Meter Table
                  'lp2_interval_activation_datetime AS mdsm_activation_datetime'
                  'lp2_write_interval AS mdsm_sampling_interval'
                  'lp2_interval_initial_time AS mdsm_sampling_initial_time'

                  // MDSM : BILL. From Meter Table
                  'lp3_interval_activation_datetime AS mdsm_activation_datetime'
                  'lp3_write_interval AS mdsm_sampling_interval'
                  'lp3_interval_initial_time AS mdsm_sampling_initial_time'

*/











                  $data_on_1 = DB::connection('mysql2')->table('meter')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get([
                      'global_device_id',
                      'msn',
                      'lp_interval_activation_datetime AS mdsm_activation_datetime',
                      'lp_write_interval AS mdsm_sampling_interval',
                      'lp_interval_initial_time AS mdsm_sampling_initial_time'
                    ])->first();
                  $data_on_1->mdsm_datetime = $mdsm_dt;
                  $data_on_1->mdsm_data_type = 'LPRO';

                  $data_on_2 = DB::connection('mysql2')->table('meter')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get([
                      'global_device_id',
                      'msn',
                      'lp2_interval_activation_datetime AS mdsm_activation_datetime',
                      'lp2_write_interval AS mdsm_sampling_interval',
                      'lp2_interval_initial_time AS mdsm_sampling_initial_time'
                    ])->first();
                  $data_on_2->mdsm_datetime = $mdsm_dt;
                  $data_on_2->mdsm_data_type = 'INST';

                  $data_on_3 = DB::connection('mysql2')->table('meter')
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get([
                      'global_device_id',
                      'msn',
                      'lp3_interval_activation_datetime AS mdsm_activation_datetime',
                      'lp3_write_interval AS mdsm_sampling_interval',
                      'lp3_interval_initial_time AS mdsm_sampling_initial_time'
                    ])->first();
                  $data_on_3->mdsm_datetime = $mdsm_dt;
                  $data_on_3->mdsm_data_type = 'BILL';

                  $data_on = array($data_on_1, $data_on_2, $data_on_3);
                } else if ($typxx == 'OPPO') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'oppo_datetime', 'oppo_optical_port_on_datetime', 'oppo_optical_port_off_datetime'])
                    ->toArray();
                } else if ($typxx == 'WSIM') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'wsim_wakeup_number_1', 'wsim_wakeup_number_2', 'wsim_wakeup_number_3', 'wsim_datetime'])
                    ->toArray();
                } else if ($typxx == 'MSIM') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'msim_id'])
                    ->toArray();
                } else if ($typxx == 'MTST') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'mtst_datetime', 'mtst_meter_activation_status'])
                    ->toArray();
                } else if ($typxx == 'DMDT') {
                  $data_on_temp = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(
                      [
                        'global_device_id',
                        'msn',
                        'dmdt_datetime',
                        'dmdt_communication_mode',
                        'dmdt_bidirectional_device',
                        'dmdt_communication_type',
                        'dmdt_communication_interval',
                        'dmdt_initial_communication_time',
                        'dmdt_phase',
                        'dmdt_meter_type'
                      ]
                    )->toArray();

                  foreach ($data_on_temp as $t => $dt) {
                    if ($dt->dmdt_meter_type == null) {
                      $dt->dmdt_meter_type = "3";
                    }
                    if ($dt->dmdt_phase == null) {
                      $dt->dmdt_phase = "3";
                    }
                    if ($dt->dmdt_bidirectional_device == null) {
                      $dt->dmdt_bidirectional_device = "0";
                    }
                  }

                  $data_on = $data_on_temp;
                } else if ($typxx == 'MDI') {
                  $data_on = DB::connection('mysql2')->table($tablex)
                    ->where('global_device_id',  $dvc['global_device_id'])
                    ->get(['global_device_id', 'msn', 'mdi_reset_date', 'mdi_reset_time'])->toArray();
                }
                // Done Till Here
                /*else if($typxx == 'SANC'){
                                    $tablex = 'udil_log';
                                    $data_onn = DB::connection('mysql2')->table($tablex)
                                            ->where('global_device_id',  '%'.$dvc['global_device_id'])
                                            ->get( ['sanc_load_control' ])->toArray();
                                    $data_on[] = json_decode($data_onn[0]->sanc_load_control);
                                    //if(is_null(json_decode($data_onn[0]->sanc_load_control)))
                                        //$data_on[] = ($data_onn[0]->sanc_load_control);
                                }
                                else if($typxx == 'IPPO'){
                                    $tablex = 'udil_log';
                                    $data_onn = DB::connection('mysql2')->table($tablex)
                                            ->where('global_device_id',  '%'.$dvc['global_device_id'])
                                            ->get( ['update_ip_port' ])->toArray();
                                    $data_on[] = json_decode($data_onn[0]->update_ip_port);
                                }*/

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $datar = $data_on;
                //$datar = $data_array;
              } else {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => "Network Error, Meter didn't communicated in Maximum allowed threshold of 5.5 minutes",
                ];
              }
            }

            $response['message'] = 'On Demand Parameter Read of Requested Meter';
            $response['data'] = $datar;
          }

          break;

        // endregion

        // region parameterization_cancellation

        case 'parameterization_cancellation':

          if (isset($form_params['type']))
            $form_params['type'] = strtoupper($form_params['type']);

          $typx = array('SANC', 'LSCH', 'TIOU', 'OVFC', 'UVFC', 'OCFC', 'OLFC', 'VUFC', 'PFFC', 'CUFC', 'HAPF');
          $types_implemented = array('SANC', 'LSCH', 'TIOU');

          if (!in_array(($form_params['type']), $typx)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', $typx);
            break;
          } else if (!in_array(($form_params['type']), $types_implemented)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Type field ' . $form_params['type'] . ' is Valid but is NOT Implemented';
            break;
          } else {
            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $typxx = $form_params['type'];
                  if ($typxx == 'SANC') {

                    $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WSANC");

                    $datav = [
                      'contactor_param_id' => NULL,
                      'contactor_param_name' => 'udil parameterization', // Old: 'UDIL'
                      'retry_count' => '3',
                      'retry_auto_interval_in_sec' => '300',
                      'on_retry_expire_auto_interval_min' => 1,
                      'write_monitoring_time' => 1,
                      'write_monitoring_time_t2' => 1,
                      'write_monitoring_time_t3' => 1,
                      'write_monitoring_time_t4' => 1,
                      'monitering_time_over_load' => 180,
                      'monitering_time_over_load_t2' => 180,
                      'monitering_time_over_load_t3' => 180,
                      'monitering_time_over_load_t4' => 180,
                      'write_limit_over_load_total_kW_t1' => 1,
                      'write_limit_over_load_total_kW_t2' => 1,
                      'write_limit_over_load_total_kW_t3' => 1,
                      'write_limit_over_load_total_kW_t4' => 1,
                      'limit_over_load_total_kW_t1' => '69',
                      'limit_over_load_total_kW_t2' => '69',
                      'limit_over_load_total_kW_t3' => '69',
                      'limit_over_load_total_kW_t4' => '69',

                      // Previously Disabled and added again
                      'contactor_on_pulse_time_ms' => 100,
                      'contactor_off_pulse_time_ms' => 100,
                      'interval_btw_contactor_state_change_sec' => 7,
                      'power_up_delay_to_change_state_sec' => 15,
                      'interval_to_contactor_failure_status_sec' => 300,
                      'optically_connect' => 0,
                      'optically_disconnect' => 0,
                      'tariff_change' => 0,
                      'is_retry_automatic_or_switch' => 1,
                      'reconnect_by_switch_on_expire' => 0,
                      'reconnect_automatic_on_expire' => 0,
                      'turn_contactor_off_overload_t1' => 1,
                      'tunr_contactor_off_overload_t2' => 1,
                      'turn_contactor_off_overload_t3' => 0,
                      'turn_contactor_off_overload_t4' => 0,
                      'write_contactor_param' => 1,
                    ];

                    $cn = DB::connection('mysql2')
                      ->table('contactor_params')
                      ->insertGetId($datav);

                    $datau = [
                      'write_contactor_param' => 1,
                      'contactor_param_id'   => $cn,
                      //'is_contactor' 	=> 1,
                      //'wakeup_request_id' => $log
                    ];

                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datau);

                    $this->insert_event(
                      $dvc['msn'],
                      $dvc['global_device_id'],
                      324,
                      'Sanction Load Control Cancelled',
                      $now
                    );

                    $datar[] = [
                      "global_device_id" => $dvc['global_device_id'],
                      "msn" => $dvc['msn'],
                      "indv_status" => "1",
                      "remarks" => "Sanctioned Load Control function will be reset to default in Meter upon Connection",
                    ];
                  } else if ($typxx == 'LSCH') {

                    $datau = [
                      'load_shedding_schedule_id' => 105,
                      'write_load_shedding_schedule'   => 1,
                    ];

                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->update($datau);

                    $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WLSCH");

                    $this->insert_event(
                      $dvc['msn'],
                      $dvc['global_device_id'],
                      325,
                      'Load Shedding Schedule Cancelled',
                      $now
                    );

                    // TODO: No Defaults so just showing success message
                    $datar[] = [
                      "global_device_id" => $dvc['global_device_id'],
                      "msn" => $dvc['msn'],
                      "indv_status" => "1",
                      "remarks" => "Loadshedding Scheduling function will be reset to default in Meter upon Connection",
                    ];
                  } else if ($typxx == 'TIOU') {

                    $data_on = DB::connection('mysql2')->table('param_activity_calendar')
                      ->where('description', '=', 'udil_parameter_cancel_default')
                      ->limit(1)->get()->toArray();

                    $id_found = false;
                    $id_value = 0;
                    // If no Fallback Calendar Found then add a new one
                    if (sizeof($data_on) == 0) {

                      $data1 = [
                        'pk_id' => NULL,
                        'description' => 'udil_parameter_cancel_default',
                        'activation_date' => '2019-01-01 00:00:00',
                      ];

                      $activity_calendar_id = $this->insert_data('param_activity_calendar', $data1);
                      //                      $activity_calendar_id = DB::connection('mysql2')
                      //                          ->table('param_activity_calendar')
                      //                          ->insertGetId( $data1 );

                      $tariffs = array(
                        array("17:00", "21:00"),
                        array("18:00", "22:00"),
                        array("19:00", "23:00"),
                        array("18:00", "22:00"),
                      );

                      $seasons = array("2024-12-01", "2024-03-01", "2024-06-01", "2024-12-09");

                      for ($d = 0; $d < 4; $d++) {
                        $data_day_profile = [
                          'pk_id' => NULL,
                          'calendar_id' => $activity_calendar_id,
                          'day_profile_id' => $d + 1,
                          'day_profile_name' => 'd' . ($d + 1),
                        ];

                        $id1 = $this->insert_data('param_day_profile', $data_day_profile);
                        //                        $id1 = DB::connection('mysql2')
                        //                            ->table('param_day_profile')
                        //                            ->insertGetId($data_day_profile);

                        $tariff_sub = $tariffs[$d];
                        for ($i = 0; $i < sizeof($tariff_sub); $i++) {
                          $data_day_profile_slots = [
                            'pk_id' => NULL,
                            'calendar_id' => $activity_calendar_id,
                            'day_profile_id' => $d + 1,
                            'switch_time' => $tariff_sub[$i],
                            'tariff' => $i + 1,
                          ];

                          $id1 = $this->insert_data('param_day_profile_slots', $data_day_profile_slots);
                          //                          $id2 = DB::connection('mysql2')
                          //                              ->table('param_day_profile_slots')
                          //                              ->insertGetId($data_day_profile_slots);
                        }
                      }

                      for ($w = 1; $w <= 4; $w++) {

                        $db_week_profile = [
                          'pk_id' => NULL,
                          'calendar_id' => $activity_calendar_id,
                          'week_profile_id' => $w,
                          'day1_profile_id' => $w,
                          'day2_profile_id' => $w,
                          'day3_profile_id' => $w,
                          'day4_profile_id' => $w,
                          'day5_profile_id' => $w,
                          'day6_profile_id' => $w,
                          'day7_profile_id' => $w,
                        ];

                        $id_week = $this->insert_data('param_week_profile', $db_week_profile);
                        //                        $id_week = DB::connection('mysql2')
                        //                            ->table('param_week_profile')
                        //                            ->insertGetId( $db_week_profile );

                        //$parsed_date = date_parse_from_format("d-m", $season_profile['start_date']);

                        $db_season_profile = [
                          'pk_id' => NULL,
                          'calendar_id' => $activity_calendar_id,
                          //'week_profile_id' => $id_week,
                          'week_profile_id' => $w,
                          'start_date' => $seasons[$d - 1],
                        ];

                        $id_season = $this->insert_data('param_season_profile', $db_season_profile);
                        //                        $id_season = DB::connection('mysql2')
                        //                            ->table('param_season_profile')
                        //                            ->insertGetId( $db_season_profile );

                      }

                      $id_found = true;
                      $id_value = $activity_calendar_id;
                    } else {
                      foreach ($data_on as $t => $dt) {
                        if ($dt->pk_id != null) {
                          $id_found = true;
                          $id_value = $dt->pk_id;
                        }
                      }
                    }

                    if ($id_found) {
                      $datau = [
                        'activity_calendar_id' => $id_value,
                      ];

                      $in = DB::connection('mysql2')
                        ->table('meter')
                        ->where('global_device_id', $dvc['global_device_id'])
                        ->update($datau);
                    }

                    $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WTIOU");

                    $this->insert_event(
                      $dvc['msn'],
                      $dvc['global_device_id'],
                      326,
                      'Time of use Programmed Cancelled',
                      $now
                    );


                    $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                    $datar[] = [
                      "global_device_id" => $dvc['global_device_id'],
                      "msn" => $dvc['msn'],
                      "indv_status" => "1",
                      "remarks" => "Time of Use function will be reset to default in Meter upon Connection",
                    ];
                  }
                }
              }

              $response['message'] = 'Parameters will be set to default against meters having indv_status equal to 1';
              $response['data'] = $datar;
            }
          }

          break;

        // endregion

        // region update_mdi_reset_date

        case 'update_mdi_reset_date':

          if (!isset($form_params['mdi_reset_date']) || !isset($form_params['mdi_reset_time'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'mdi_reset_date OR mdi_reset_time fields are missing';
            break;
          } else if (!$this->filterInteger($form_params['mdi_reset_date'], 1, 28) || !$this->is_time_valid($form_params['mdi_reset_time'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid values are provided in mdi_reset_date OR mdi_reset_time fields';
            break;
          } else if (isset($all_devices)) {
            foreach ($all_devices as $dvc) {
              if ($dvc['msn'] == 0) {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => $this->meter_not_exists,
                ];
              } else {

                /*
                $datai = array(
                    'sub_div_id'     => 1,
                    'mdi_reset_day' => $form_params['mdi_reset_date'],
                    'batch_no'       => 29,
                    'pre_mdi_reset_day' => '0',
                    'action_date'    => $now
                );

                $in = DB::connection('mysql2')
                    ->table('mdi_reset_date_log')
                    ->insert( $datai );
*/

                $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WMDI");
                //$log = $this->setWakeupAndTransaction($dvc['msn'], $now, $slug, $transaction, $dvc['global_device_id']);

                $datau = [
                  'mdi_reset_date'    => $form_params['mdi_reset_date'],
                  'mdi_reset_time'    => $form_params['mdi_reset_time'],
                  'write_mdi_reset_date' => 1
                  //'wakeup_request_id' => $log
                ];

                $in = DB::connection('mysql2')
                  ->table('meter')
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->update($datau);

                // TODO: Check if this will be done by Wrapper ?
                $datav = [
                  'mdi_reset_date'   => $form_params['mdi_reset_date'],
                  'mdi_reset_time'   => '00:00:00'
                ];

                $inx = DB::connection('mysql2')
                  ->table('meter_visuals')
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->update($datav);

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $response['status'] = 1;
                $response['http_status'] = 200;
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "1",
                  "remarks" => "New MDI RESET date will be programmed in meter upon communication",
                ];
              }
            }
            $response['message'] = 'New MDI RESET date will be programmed in meters with indv_status = 1';
            $response['data'] = $datar;
          }
          break;

        // endregion

        // region transaction_status

        case 'transaction_status':
          $response['status'] = 1;
          $response['http_status'] = 200;
          $response['data'] = $this->get_modified_transaction_status($transaction);
          //sleep(12);
          break;

        // endregion

        // region transaction_cancel

        case 'transaction_cancel':

          if (isset($all_devices)) {
            foreach ($all_devices as $dvc) {

              $transaction_data = DB::connection('mysql2')->table('transaction_status')
                ->where('transaction_id', $transaction)
                ->where('global_device_id', $dvc['global_device_id'])
                ->get()->toArray();

              if ($dvc['msn'] == 0) {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => $this->meter_not_exists,
                ];
              } else if (is_null($transaction_data) || sizeof($transaction_data) == 0) {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => "No Operations are queued for this Global Device ID for this transaction",
                ];
              } else {

                foreach ($transaction_data as $key => $val) {

                  $slug_type = strtoupper($val->type);

                  if (is_null($val->status_level) || ($val->status_level < 3)) {

                    $tr_update = [
                      'request_cancelled'   => 1,
                      'request_cancel_reason' => 'Cancelled by Service',
                      'request_cancel_datetime' => now(),
                    ];

                    $in = DB::connection('mysql2')
                      ->table('transaction_status')
                      ->where('global_device_id', $dvc['global_device_id'])
                      ->where('transaction_id', $transaction)
                      ->update($tr_update);

                    switch ($slug_type) {

                      case "WSIM":

                        $datau = [
                          'set_wakeup_profile_id'   => 0,
                          'number_profile_group_id'   => 0,
                        ];

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WMDI":

                        $datau = [
                          'write_mdi_reset_date' => 0
                        ];

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WAUXR":

                        $datau = [
                          'apply_new_contactor_state' => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WSANC":

                        $datau = [
                          'write_contactor_param' => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WMDSM":

                        $datau = [
                          'lp_write_interval_request' => 0,
                          'lp2_write_interval_request' => 0,
                          'lp3_write_interval_request' => 0,
                        ];

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WOPPO":

                        $data_oppo = [
                          'update_optical_port_access' => 0,
                        ];

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id',  $dvc['global_device_id'])
                          ->update($data_oppo);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WLSCH":

                        $datau = [
                          'write_load_shedding_schedule'   => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WIPPO":

                        $datau = [
                          'set_ip_profiles' => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id',  $dvc['global_device_id'])
                          ->update($datau);
                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);
                        break;

                      case "WTIOU":

                        $datau = [
                          'activity_calendar_id' => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);
                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);
                        break;

                      case "WDVTM":

                        $datau = [
                          'super_immediate_cs'   => 0,
                        ];

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);
                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WDMDT":

                        $datau = [
                          'set_keepalive' => 0,
                          'tbe1_write_request_id' => 0,
                          'energy_param_id' => 0,
                        ];
                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->update($datau);
                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WMTST":

                        $data_on = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id',  $dvc['global_device_id'])
                          ->get(['status'])->first();

                        if (!is_null($data_on)) {
                          $datau = [
                            'status' => (($data_on->status == 1) ? 0 : 1),
                          ];
                          $in = DB::connection('mysql2')
                            ->table('meter')
                            ->where('global_device_id', $dvc['global_device_id'])
                            ->update($datau);
                        }

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      case "WDVCR":

                        $in = DB::connection('mysql2')
                          ->table('meter')
                          ->where('global_device_id', $dvc['global_device_id'])
                          ->where('msn', $dvc['msn'])
                          ->delete();

                        $datar[] = $this->get_transaction_cancel_response($dvc['global_device_id'], $dvc['msn'], $slug_type);

                        break;

                      /*                                              $datar[] = [
                                                    "global_device_id" => $dvc['global_device_id'],
                                                    "msn" => $dvc['msn'],
                                                    "indv_status" => "0",
                                                    "remarks" => "Unable to cancel '".$slug_type."'",
                                                ];
                                                break;*/

                      default:
                        $datar[] = [
                          "global_device_id" => $dvc['global_device_id'],
                          "msn" => $dvc['msn'],
                          "indv_status" => "0",
                          "remarks" => "Unknown Type '" . $slug_type . "' for this Transaction. Only Write Jobs can be cancelled",
                        ];
                        break;
                    }
                  } else {
                    $datar[] = [
                      "global_device_id" => $dvc['global_device_id'],
                      "msn" => $dvc['msn'],
                      "indv_status" => "0",
                      "remarks" => "Command sent to Meter so Transaction cannot be cancelled",
                    ];
                  }
                }
              }
            }
            $response['message'] = 'Transaction will be cancelled against meters with indv_status = 1';
            $response['data'] = $datar;
          }
          break;

        // endregion

        // region meter_data_sampling

        case 'meter_data_sampling':

          $types_valid = array('INST', 'BILL', 'LPRO');

          if (!isset($form_params['activation_datetime']) || !isset($form_params['data_type']) || !isset($form_params['sampling_interval']) || !isset($form_params['sampling_initial_time'])) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Mandatory Input Fields are missing';
            break;
          } else if (!in_array(strtoupper($form_params['data_type']), $types_valid)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Field data_type has invalid value. Only these fields are applicable: ' . implode(' ', $types_valid);
            break;
          } else if (!$this->filterInteger($form_params['sampling_interval'], 1, 1440)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Provided Sampling Interval value is not in valid range i.e. 15 to 1440 Minutes';
            break;
          } else if (isset($all_devices)) {
            foreach ($all_devices as $dvc) {
              if ($dvc['msn'] == 0) {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => $this->meter_not_exists,
                ];
              } else {

                $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WMDSM");
                //$log = $this->setWakeupAndTransaction($msn, $now, $slug, $transaction, $msn);


                if ($form_params['data_type'] == "INST") {
                  $datau = [
                    'lp2_write_interval_request' => 1,
                    'lp2_write_interval' => $form_params['sampling_interval'],
                    'lp2_interval_activation_datetime' => $form_params['activation_datetime'],
                  ];
                } else if ($form_params['data_type'] == "BILL") {
                  $datau = [
                    'lp3_write_interval_request' => 1,
                    'lp3_write_interval' => $form_params['sampling_interval'],
                    'lp3_interval_activation_datetime' => $form_params['activation_datetime'],
                  ];
                } else {
                  $datau = [
                    'lp_write_interval_request' => 1,
                    'lp_write_interval' => $form_params['sampling_interval'],
                    'lp_interval_activation_datetime' => $form_params['activation_datetime'],
                  ];
                }

                //                                $datau = [
                //                                    //'wakeup_request_id' => $log
                //                                ];
                $in = DB::connection('mysql2')
                  ->table('meter')
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->update($datau);

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $response['status'] = 1;
                $response['http_status'] = 200;
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "1",
                  "remarks" => "Load Profile Sampling Interval will be programmed in meter",
                ];
              }
            }
            $response['message'] = 'Sampling Interval will be programmed in meters with indv_status = 1';
            $response['data'] = $datar;
          }

          //sleep(12);
          break;

        // endregion

        // region update_meter_status

        case 'update_meter_status':
          if (!isset($form_params['meter_activation_status'])) {
            $this->update_response_fail(
              $response,
              400,
              'Meter Activation Status variable is missing'
            );
            /*          $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Meter Activation Status variable is missing'; */
            break;
          } else if (!$this->filterInteger($form_params['meter_activation_status'], 0, 1)) {
            $this->update_response_fail(
              $response,
              400,
              'Meter Activation Status is not valid Only 0 for Inactive or 1 for Active is acceptable'
            );
            /*          $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Meter Activation Status is not valid Only 0 for Inactive or 1 for Active is acceptable';*/
            break;
          }

          if (isset($all_devices)) {
            foreach ($all_devices as $dvc) {
              if ($dvc['msn'] == 0) {
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "0",
                  "remarks" => $this->meter_not_exists,
                ];
              } else {

                $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WMTST");

                $sts = ($form_params['meter_activation_status'] == 1) ? 'Activated' : 'De-activated';

                /*              $datau = [
                    'status' => $form_params['meter_activation_status']
                ];
                $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update( $datau ); */

                $this->update_meter(
                  $dvc['global_device_id'],
                  ['status' => $form_params['meter_activation_status']]
                );

                $datauuu = [
                  'mtst_meter_activation_status' => $form_params['meter_activation_status'],
                  'mtst_datetime' => now()
                ];

                $in = DB::connection('mysql2')
                  ->table('meter_visuals')
                  ->where('global_device_id', $dvc['global_device_id'])
                  ->update($datauuu);

                $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                $response['status'] = 1;
                $response['http_status'] = 200;
                $datar[] = [
                  "global_device_id" => $dvc['global_device_id'],
                  "msn" => $dvc['msn'],
                  "indv_status" => "1",
                  "remarks" => "Meter is $sts",
                ];
              }
            }

            $response['message'] = 'Status of meters has been changed with indv_status = 1';
            $response['data'] = $datar;
          }

          break;

        // endregion

        // region update_device_metadata

        case 'update_device_metadata':

          if (
            !isset($form_params['communication_mode'])  || !isset($form_params['bidirectional_device']) || !isset($form_params['communication_type'])
            || !isset($form_params['phase']) || !isset($form_params['meter_type']) || !isset($form_params['initial_communication_time'])
            || !isset($form_params['communication_interval'])
          ) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Mandatory fields are missing in update_device_metadata service';
            break;
          }
          //Validating Communication Interval
          else if (
            !$this->filterInteger($form_params['communication_mode'], 1, 5) || !$this->filterInteger($form_params['bidirectional_device'], 0, 1) || !$this->filterInteger($form_params['communication_type'], 1, 2)
            || !$this->filterInteger($form_params['phase'], 1, 3) || !$this->filterInteger($form_params['meter_type'], 1, 4) || !$this->is_time_valid($form_params['initial_communication_time'])
          ) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Wrong Values are provided in one or more fields';
            break;
          } else {
            $com_type = $form_params['communication_type'];

            // Keep Alive Meter
            if ($com_type == 2) {
              $keepAlive = 1;
              $sch_pq = 3;
              $sch_cb = 1;
              $sch_mb = 3;
              $sch_ev = 3;
              $sch_lp = 3;
              $sch_lp2 = 3;
              $sch_lp3 = 3;
              $save_sch_pq = 2;
              $sch_ss = 2;
              $sch_cs = 3;
              $kas = '00:00:10';
              $meter_class = 'keepalive';
              $set_keepalive = 1;
              $interval_pq = '00:15:00';
              $interval_cb = '00:15:00';
              $interval_mb = '00:15:00';
              $interval_ev = '00:05:00';
              $interval_lp = '00:15:00';
              $interval_cs = '00:15:00';
            } else {
              // Non-Keep Alive Meter
              $keepAlive = 0;
              $sch_pq = 2;
              $sch_cb = 1;
              $sch_mb = 2;
              $sch_ev = 2;
              $sch_lp = 2;
              $sch_lp2 = 2;
              $sch_lp3 = 2;
              $save_sch_pq = 2;
              $sch_ss = 2;
              $sch_cs = 2;
              $kas = '00:01:00';
              $set_keepalive = 2;
              $interval_pq = '00:15:00';
              $interval_cb = '00:01:00';
              $interval_mb = '00:01:00';
              $interval_ev = '00:01:00';
              $interval_lp = '00:01:00';
              $interval_cs = '00:01:00';
              $meter_class = 'non-keepalive';
            }

            if (isset($all_devices)) {
              foreach ($all_devices as $dvc) {
                if ($dvc['msn'] == 0) {
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "0",
                    "remarks" => $this->meter_not_exists,
                  ];
                } else {

                  $this->insert_transaction_status($dvc['global_device_id'], $dvc['msn'], $transaction, "WDMDT");


                  $isLtHt = false;
                  if (($form_params['meter_type'] == 3) || ($form_params['meter_type'] == 4)) {
                    $isLtHt = true;
                  }

                  if ($form_params['bidirectional_device'] == 0) {
                    if ($isLtHt) {
                      $dw_normal = 14;
                      $dw_alternate = 15;
                    } else {
                      $dw_normal = 10;
                      $dw_alternate = 11;
                    }
                    $energy_param_id = 2;
                    $bidirectional_flag = 0;
                  } else {
                    if ($isLtHt) {
                      $dw_normal = 12;
                      $dw_alternate = 13;
                    } else {
                      $dw_normal = 8;
                      $dw_alternate = 9;
                    }
                    $energy_param_id = 1;
                    $bidirectional_flag = 1;
                  }
                  /*
                                    $bi_status = $this->get_from_meter_single_result($dvc['global_device_id'], ['bidirectional_device']);
                                    if (!is_null($bi_status)) {

                                        if (!(
                                            (($bi_status->bidirectional_device == 0) && ($form_params['bidirectional_device'] == 0)) ||
                                            (($bi_status->bidirectional_device == 1) && ($form_params['bidirectional_device'] == 1))
                                        )) {

//                                        if (
//                                            (($bi_status->bidirectional_device == 0) && ($form_params['bidirectional_device'] == 1)) ||
//                                            (($bi_status->bidirectional_device == 1) && ($form_params['bidirectional_device'] == 0))
//                                        ) {

                                            if ($form_params['bidirectional_device'] == 0) {
                                                $dw_normal = 10;
                                                $dw_alternate = 11;
                                                $energy_param_id = 2;
                                                $bidirectional_flag = 0;
                                            } else {
                                                $dw_normal = 8;
                                                $dw_alternate = 9;
                                                $energy_param_id = 1;
                                                $bidirectional_flag = 1;
                                            }

                                            $data_01 = array(
                                                'dw_normal_mode_id' => $dw_normal,
                                                'dw_alternate_mode_id' => $dw_alternate,
                                                'energy_param_id' => $energy_param_id,
                                                'bidirectional_device' => $bidirectional_flag,
                                            );

                                            $in = DB::connection('mysql2')
                                                ->table('meter')
                                                ->where('global_device_id', $dvc['global_device_id'])
                                                ->update( $data_01 );

                                        }
                                    }*/


                  if ($isLtHt) {
                    $load_profile_group_id = 206;
                    $encryption_key = "F2CE6D0BC7E53DA0B23FCCEE9736D617";
                    $authentication_key = "C1CA4472EFE30A2668CC10A64DCCCED7";
                    $master_key = "00000000000000000000000000000000";
                    $dds_compatible = 0;
                    $max_events_entries = 300;
                    $association_id = 2;
                    $max_billing_months = 24;
                    $read_logbook = 2;
                    $lp_invalid_update = 2;
                    $ev_invalid_update = 2;
                    $schedule_plan = "7,3,4,5,6,8,10,11";
                    $interval_lp = '00:30:00';
                  } else { // Three Phase
                    $load_profile_group_id = 205;
                    $encryption_key = "000102030405060708090A0B0C0D0E0F";
                    $authentication_key = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                    $master_key = "000102030405060708090A0B0C0D0E0F";
                    $dds_compatible = 1;
                    $max_events_entries = 100;
                    $association_id = 10;
                    $max_billing_months = 12;
                    $read_logbook = 1;
                    $lp_invalid_update = 0;
                    $ev_invalid_update = 0;
                    $schedule_plan = "3,4,5,6,7,8,10,11";
                    $interval_lp = '12:00:00';
                  }

                  /*
                      $ins = [
                          'control'  => '1',
                          'datetime_year'  => '65535',
                          'datetime_month'  => '255',
                          'datetime_day_of_month'  => '255',
                          'datetime_day_of_week'  => '255',
                          'datetime_hours'  => '0',
                          'datetime_minutes'  => '0',
                          'datetime_seconds'  => '0',
                          'interval_timespan' => $form_params['initial_communication_time'],
                          'interval_sink_minutes' => $form_params['communication_interval'],
                          'interval_sink_seconds' => 0,
                          'interval_fixed_minutes' => 0,
                          'interval_fixed_seconds' => 0,
                      ];

                      $id_com_interval = DB::connection('mysql2')
                          ->table('time_base_events_detail')
                          ->insertGetId( $ins );
*/

                  $data = array(
                    // No need to update time_base_events_detail in update_device_metadata
                    //'tbe1_write_request_id' => $id_com_interval,
                    'class' => $meter_class,
                    'type' => $keepAlive,
                    'sch_pq' => $sch_pq,
                    'interval_pq' => $interval_pq,
                    'sch_cb' => $sch_cb,
                    'interval_cb' => $interval_cb,
                    'sch_mb' => $sch_mb,
                    'interval_mb' => $interval_mb,
                    'sch_ev' => $sch_ev,
                    'interval_ev' => $interval_ev,
                    'sch_lp' => $sch_lp,
                    'sch_lp2' => $sch_lp2,
                    'sch_lp3' => $sch_lp3,
                    'interval_lp' => $interval_lp,
                    'kas_interval' => $kas,
                    'save_sch_pq' => $save_sch_pq,
                    'save_interval_pq' => $interval_pq,
                    'sch_ss' => $sch_ss,
                    'sch_cs' => $sch_cs,
                    'interval_cs' => $interval_cs,
                    'set_keepalive' => $set_keepalive,
                    'super_immediate_pq' => '0',
                    'bidirectional_device' => $form_params['bidirectional_device'],
                    'dw_normal_mode_id' => $dw_normal,
                    'dw_alternate_mode_id' => $dw_alternate,
                    'energy_param_id' => $energy_param_id,
                    'max_billing_months' => $max_billing_months,

                    // Previously not present in Update Device Metadata
                    'association_id' => $association_id,
                    'schedule_plan' => $schedule_plan,
                    'read_logbook' => $read_logbook,
                    'encryption_key' => $encryption_key,
                    'authentication_key' => $authentication_key,
                    'master_key' => $master_key,
                    'dds_compatible' => $dds_compatible,
                    'max_events_entries' => $max_events_entries,
                    'lp_invalid_update' => $lp_invalid_update,
                    'lp2_invalid_update' => $lp_invalid_update,
                    'lp3_invalid_update' => $lp_invalid_update,
                    'ev_invalid_update' => $ev_invalid_update,
                    'load_profile_group_id' => $load_profile_group_id,
                  );

                  $in = DB::connection('mysql2')
                    ->table('meter')
                    ->where('global_device_id', $dvc['global_device_id'])
                    ->update($data);

                  $this->update_on_transaction_success($slug, $dvc['global_device_id']);
                  $response['status'] = 1;
                  $response['http_status'] = 200;
                  $datar[] = [
                    "global_device_id" => $dvc['global_device_id'],
                    "msn" => $dvc['msn'],
                    "indv_status" => "1",
                    "remarks" => "Meter Communication Mode will be changed",
                  ];
                }
              }

              $response['message'] = 'Device Metadata is applied to meters with indv_status = 1';
              $response['data'] = $datar;
            }
          }
          break;

        // endregion

        // region device_creation

        case 'device_creation':

          $device_creation_error_status = $this->validate_device_creation_params($form_params);

          if (isset($form_params['device_identity']))
            $devices = json_decode($form_params['device_identity'], true);

          // Checking Non initialized
          /*                  if ( !isset( $form_params['device_identity'] )
                        || !isset( $form_params['communication_interval'] ) || !isset( $form_params['device_type'] ) || !isset( $form_params['mdi_reset_date'] ) || !isset( $form_params['mdi_reset_time'] )
                        || !isset( $form_params['sim_number'] ) || !isset( $form_params['sim_id'] ) || !isset( $form_params['phase'] ) || !isset( $form_params['meter_type'] ) || !isset( $form_params['communication_mode'] ) || !isset( $form_params['communication_type'] )
                        || !isset( $form_params['bidirectional_device'])
                        || !isset( $form_params['initial_communication_time'])
                    ) {*/

          if ($device_creation_error_status != "") {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = $device_creation_error_status;
            $response['debug'] = $form_params;
            break;
          } else if (!is_array($devices)) {
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Only Array of devices is allowed';
            break;
          } else if (
            !$this->filterInteger($form_params['communication_mode'], 1, 5) || !$this->filterInteger($form_params['communication_type'], 1, 2) ||
            !$this->filterInteger($form_params['bidirectional_device'], 0, 1) || !$this->is_time_valid($form_params['mdi_reset_time'])
          ) {
            //|| !$this->is_time_valid( $form_params['initial_communication_time'])
            $response['status'] = 0;
            $response['http_status'] = 400;
            $response['message'] = 'Invalid values are provided in input variables';
            break;
          } else {

            foreach ($devices as $key => $val) {
              if (!isset($devices[$key]['dsn']) || !isset($devices[$key]['global_device_id']) || $devices[$key]['global_device_id'] == '' || $devices[$key]['dsn'] == '') {
                $response['status'] = 0;
                $response['http_status'] = 400;
                $response['message'] = 'Either DSN or Global Device ID index is missing';
                break;
              }
            }

            if ($response['status'] != 0) {

              $wakeup_no1 = '03018446741';
              $wakeup_no2 = '03212345686'; // '923028430392'
              $wakeup_no3 = '03004009347'; // '923324008801'
              $wakeup_no4 = '03236762704'; // '923004001036'

              foreach ($devices as $key => $ffc) {

                $gdid = $devices[$key]['global_device_id'];
                $msn = $devices[$key]['dsn'];
                $com_type = $form_params['communication_type'];
                $temp_ref = '30' . $msn;

                $this->insert_transaction_status($gdid, $msn, $transaction, "WDVCR");

                $replace_str = array('"', "'", ",");
                $msn_initial = str_replace($replace_str, '', substr($msn, 0, 4));
                $form_params['meter_type'] = str_replace($replace_str, '', $form_params['meter_type']);

                if ($msn_initial == '3697' && $form_params['phase'] != 1) {
                  $datar[] = [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Meter with Prefix of 97 can only be allowed for Single Phase Device",
                  ];
                  continue;
                } elseif (($msn_initial == '3698' || $msn_initial == '3699') && $form_params['phase'] != '3') {
                  $datar[] = [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Meter with Prefix of 98, 99 can only be allowed for Three Phase Device.",
                  ];
                  continue;
                } elseif ($msn_initial == '3698' && $form_params['meter_type'] != '2') {
                  $datar[] = [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Meter with Prefix of 98 can only be allowed for Three Phase Whole Current Device.",
                  ];
                  continue;
                } elseif ($msn_initial == '3699' && ($form_params['meter_type'] != 3 && $form_params['meter_type'] != 4)) {
                  $datar[] = [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Meter with Prefix of 99 can only be allowed for CTO OR CTPT Device.",
                  ];
                  continue;
                }

                // Validate that MSN does not already exist with a Different Global Device ID
                $data_on = DB::connection('mysql2')
                  ->table('meter')
                  ->where('msn',  $msn)
                  ->get(['global_device_id'])
                  ->first();

                if (!is_null($data_on)) {
                  if ($data_on->global_device_id != $gdid) {
                    $datar[] = [
                      "global_device_id" => $gdid,
                      "msn" => $msn,
                      "indv_status" => "0",
                      "remarks" => "Same MSN already exist with different Global Device ID (" . ($data_on->global_device_id) . ") Global Device ID can't be changed",
                    ];
                    continue;
                  }
                }

                $sch_cb = 1;

                if ($com_type == 2) { //Keepalive
                  $keepAlive = 1;
                  $sch_pq = 3;
                  $sch_mb = 3;
                  $sch_ev = 3;
                  $sch_lp = 3;
                  $sch_lp2 = 3;
                  $sch_lp3 = 3;
                  $save_sch_pq = 2;
                  $sch_ss = 2;
                  $sch_cs = 3;
                  $kas = '00:00:10';
                  $meter_class = 'keepalive';
                  $set_keepalive = 1;
                  $interval_pq = '00:15:00';
                  $interval_cb = '00:15:00';
                  $interval_mb = '00:15:00';
                  $interval_ev = '00:05:00';
                  $interval_lp = '00:15:00';
                  $interval_cs = '00:15:00';
                } else { //NON-Keepalive
                  $keepAlive = 0;
                  $sch_pq = 2;
                  $sch_mb = 2;
                  $sch_ev = 2;
                  $sch_lp = 2;
                  $sch_lp2 = 2;
                  $sch_lp3 = 2;
                  $save_sch_pq = 2;
                  $sch_ss = 2;
                  $sch_cs = 2;
                  $kas = '00:01:00';
                  $set_keepalive = 2;
                  $interval_pq = '00:15:00';
                  $interval_cb = '00:01:00';
                  $interval_mb = '00:01:00';
                  $interval_ev = '00:01:00';
                  $interval_lp = '00:01:00';
                  $interval_cs = '00:01:00';
                  $meter_class = 'non-keepalive';
                }

                $is_line = 0;
                $des = '';
                $code = 0;
                $mf = 1;
                $cat = 0;
                $class = 'folder-bookmark';
                $export_company = 0;

                if (substr($msn, 0, 4) == 3698) {
                  $model_id = 3;
                  $load_profile_group_id = 201;
                  $contactor_opt = 1;
                } else {
                  $model_id = 5;
                  $load_profile_group_id = 282;
                  $contactor_opt = 0;
                }

                /*
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
                    ->insertGetId( $ins );
*/

                /*
                                $bi_status = $this->get_from_meter_single_result($gdid, ['bidirectional_device']);
                                if (!is_null($bi_status)) {

                                    if (!(
                                        (($bi_status->bidirectional_device == 0) && ($form_params['bidirectional_device'] == 0)) ||
                                        (($bi_status->bidirectional_device == 1) && ($form_params['bidirectional_device'] == 1))
                                    )) {

                                        if ($form_params['bidirectional_device'] == 0) {
                                            $dw_normal = 10;
                                            $dw_alternate = 11;
                                            $energy_param_id = 2;
                                            $bidirectional_flag = 0;
                                        } else {
                                            $dw_normal = 8;
                                            $dw_alternate = 9;
                                            $energy_param_id = 1;
                                            $bidirectional_flag = 1;
                                        }

                                        $data_01 = array(
                                            'dw_normal_mode_id' => $dw_normal,
                                            'dw_alternate_mode_id' => $dw_alternate,
                                            'energy_param_id' => $energy_param_id,
                                            'bidirectional_device' => $bidirectional_flag,
                                        );

                                        $in = DB::connection('mysql2')
                                            ->table('meter')
                                            ->where('global_device_id', $gdid)
                                            ->update( $data_01 );

                                    }
                                }*/

                $isLtHt = false;
                if (($form_params['meter_type'] == 3) || ($form_params['meter_type'] == 4)) {
                  $isLtHt = true;
                }

                if ($form_params['bidirectional_device'] == 0) {
                  if ($isLtHt) {
                    $dw_normal = 14;
                    $dw_alternate = 15;
                  } else {
                    $dw_normal = 10;
                    $dw_alternate = 11;
                  }
                  $energy_param_id = 2;
                  $bidirectional_flag = 0;
                } else {
                  if ($isLtHt) {
                    $dw_normal = 12;
                    $dw_alternate = 13;
                  } else {
                    $dw_normal = 8;
                    $dw_alternate = 9;
                  }
                  $energy_param_id = 1;
                  $bidirectional_flag = 1;
                }

                DB::connection('mysql2')->beginTransaction();
                try {

                  // Reinitialized
                  $interval_mb = '12:00:00';
                  $interval_ev = '12:00:00';
                  $interval_lp = '12:00:00';
                  $contactor_opt = 0;
                  $kas = '00:00:05';

                  if ($isLtHt) {
                    $load_profile_group_id = 206;
                    $encryption_key = "F2CE6D0BC7E53DA0B23FCCEE9736D617";
                    $authentication_key = "C1CA4472EFE30A2668CC10A64DCCCED7";
                    $master_key = "00000000000000000000000000000000";
                    $dds_compatible = 0;
                    $max_events_entries = 300;
                    $association_id = 2;
                    $max_billing_months = 24;
                    $read_logbook = 2;
                    $lp_invalid_update = 2;
                    $ev_invalid_update = 2;
                    $schedule_plan = "7,3,4,5,6,8,10,11";
                    $interval_lp = '00:30:00';
                  } else { // Three Phase
                    $load_profile_group_id = 205;
                    $encryption_key = "000102030405060708090A0B0C0D0E0F";
                    $authentication_key = "D0D1D2D3D4D5D6D7D8D9DADBDCDDDEDF";
                    $master_key = "000102030405060708090A0B0C0D0E0F";
                    $dds_compatible = 1;
                    $max_events_entries = 100;
                    $association_id = 10;
                    $max_billing_months = 12;
                    $read_logbook = 1;
                    $lp_invalid_update = 0;
                    $ev_invalid_update = 0;
                    $schedule_plan = "3,4,5,6,7,8,10,11";
                    $interval_lp = '12:00:00';
                  }

                  $load_profile2_group_id = 202;
                  $load_profile3_group_id = 201;

                  // $set_keepalive = 0;
                  $interval_cs = '1.00:00:00';

                  $found_meter = DB::connection('mysql2')->table('meter')->where('global_device_id', $gdid)->count();

                  if ($found_meter == 0) {

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

                    // device_creation : Insert Meter
                    $data = array(
                      'tbe1_write_request_id' => $id_com_interval,
                      'name' => $msn,
                      'description' => 'Meter Created using UDIL Service',
                      'global_device_id' => $gdid,
                      'msn' => $msn,
                      'model_id' => $model_id,
                      'cat_id' => 1,
                      'class' => $meter_class,
                      'code' => 0,
                      'schedule_plan' => $schedule_plan,
                      'prioritize_wakeup' => '1',
                      'encryption_key' => $encryption_key,
                      'authentication_key' => $authentication_key,
                      'master_key' => $master_key,
                      'dds_compatible' => $dds_compatible,
                      'rated_mva' => '0',
                      'rated_amps' => '0',
                      'ct_ratio_num' => '1',
                      'ct_ratio_denum' => '1',
                      'type' => $keepAlive,
                      'sub_type' => '0',
                      'use_for_demand' => '0',
                      'export_company' => 0,
                      'mf' => 1,
                      'sim' => $form_params['sim_number'], //*
                      'sim_id' => $form_params['sim_id'], //*
                      'meter_type_id' => 1, //*
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
                      'sch_pq' => $sch_pq,
                      'base_time_pq' => '2022-01-01 00:00:00',
                      'interval_pq' => $interval_pq,
                      'sch_cb' => $sch_cb,
                      'base_time_cb' => '2022-01-01 00:00:00',
                      'interval_cb' => $interval_cb,
                      'sch_mb' => $sch_mb,
                      'base_time_mb' => '2022-01-01 00:00:00',
                      'interval_mb' => $interval_mb,
                      'sch_ev' => $sch_ev,
                      'base_time_ev' => '2022-01-01 00:00:00',
                      'interval_ev' => $interval_ev,
                      'sch_lp' => $sch_lp,
                      'sch_lp2' => $sch_lp2,
                      'sch_lp3' => $sch_lp3,
                      'base_time_lp' => '2022-01-01 00:00:00',
                      'interval_lp' => $interval_lp,
                      'interval_lp2' => $interval_lp,
                      'interval_lp3' => $interval_lp,
                      'max_load_profile_entries' => '4096',
                      'max_load_profile2_entries' => '4096',
                      'max_load_profile3_entries' => '4096',
                      'monthly_billing_counter' => '0',
                      'load_profile_group_id' => $load_profile_group_id,
                      'load_profile2_group_id' => $load_profile2_group_id,
                      'load_profile3_group_id' => $load_profile3_group_id,
                      'set_ip_profiles' => '0',
                      'set_modem_initialize_basic' => '0',
                      'set_modem_initialize_extended' => '0',
                      'Apply_Disable_Tbe_Flag_On_Powerfail' => '0000',
                      'set_keepalive' => $set_keepalive,
                      'major_alarm_group_id' => '0',
                      'max_lp_count_diff' => '500',
                      'min_lp_count_diff' => '1',
                      'kas_interval' => $kas,
                      'kas_due_time' => '2022-01-01 00:00:00',
                      'save_sch_pq' => $save_sch_pq,
                      'save_base_time_pq' => '2021-01-01 00:00:00',
                      'save_interval_pq' => $interval_pq,
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
                      'sch_ss' => $sch_ss,
                      'base_time_ss' => '2022-01-01 00:00:00',
                      'interval_ss' => '12:00:00',
                      'sch_cs' => $sch_cs,
                      'base_time_cs' => '2022-01-01 00:00:00',
                      'interval_cs' => $interval_cs,
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
                      'max_events_entries' => $max_events_entries,
                      'max_ev_count_diff' => '1500',
                      'min_ev_count_diff' => '1',
                      'is_prepaid' => $contactor_opt,
                      'apply_new_contactor_state' => '0',

                      'is_rf' => '0',
                      'is_line' => $is_line,
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
                      'ev_invalid_update' => $ev_invalid_update,
                      'new_meter_password' => 'microtek',
                      'new_password_activation_time' => '2021-01-01 00:00:00',
                      'lp_chunk_size' => '100',
                      'lp2_chunk_size' => '100',
                      'lp3_chunk_size' => '100',
                      'is_individual' => '0',
                      'lp_invalid_update' => $lp_invalid_update,
                      'lp2_invalid_update' => $lp_invalid_update,
                      'lp3_invalid_update' => $lp_invalid_update,
                      'dw_normal_mode_id' => $dw_normal,
                      'dw_alternate_mode_id' => $dw_alternate,
                      'energy_param_id' => $energy_param_id,
                      'bidirectional_device' => $bidirectional_flag,
                      'max_cs_difference' => '4000',
                      'min_cs_difference' => '40',
                      'no_show_kwh' => '0',
                      'modem_limits_time_id' => '0',
                      'new_contactor_state' => '0',
                      'no_show_ls' => '0',
                      'scroll_time' => '15',
                      'mdi_reset_date' => '1',
                      'number_profile_group_id' => '0',
                      'read_individual_events_sch' => '0',
                      'set_wakeup_profile_id' => '0',
                      'write_contactor_param' => '0',
                      'write_mdi_reset_date' => '0',
                      'read_logbook' => $read_logbook,
                      'write_modem_limits_time' => '0',
                      'write_password_flag' => '0',
                      'write_reference_no' => '0',
                      'read_cs' => '1',
                      'wakeup_no1' => $wakeup_no1,
                      'wakeup_no2' => $wakeup_no2,
                      'wakeup_no3' => $wakeup_no3,
                      'wakeup_no4' => $wakeup_no4,
                      'is_overload' => '0',
                      'is_time_sync' => '0',
                      'association_id' => $association_id,
                      'save_events_on_alarm' => 0,
                      'max_billing_months' => $max_billing_months
                    );

                    DB::connection('mysql2')
                      ->table('meter')
                      ->insertGetId($data);
                  } else {

                    // device_creation : Update Meter
                    $datav = array(
                      //'tbe1_write_request_id' => $id_com_interval,
                      'class' => $meter_class,
                      'type' => $keepAlive,
                      'sch_pq' => $sch_pq,
                      'interval_pq' => $interval_pq,
                      'sch_cb' => $sch_cb,
                      'interval_cb' => $interval_cb,
                      'sch_mb' => $sch_mb,
                      'interval_mb' => $interval_mb,
                      'sch_ev' => $sch_ev,
                      'interval_ev' => $interval_ev,
                      'sch_lp' => $sch_lp,
                      'sch_lp2' => $sch_lp2,
                      'sch_lp3' => $sch_lp3,
                      'interval_lp' => $interval_lp,
                      'kas_interval' => $kas,
                      'save_sch_pq' => $save_sch_pq,
                      'save_interval_pq' => $interval_pq,
                      'sch_ss' => $sch_ss,
                      'sch_cs' => $sch_cs,
                      'interval_cs' => $interval_cs,
                      'set_keepalive' => $set_keepalive,
                      'super_immediate_pq' => '1',
                      'bidirectional_device' => $bidirectional_flag,
                      'dw_normal_mode_id' => $dw_normal,
                      'dw_alternate_mode_id' => $dw_alternate,
                      'energy_param_id' => $energy_param_id,
                      'max_billing_months' => $max_billing_months,
                    );

                    DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $gdid)
                      ->update($datav);
                  }

                  /*
                  $found_meter = DB::connection('mysql2')->table('meter')->where( 'global_device_id', $gdid )->count();
                  if ($found_meter == 0) {
                    DB::connection('mysql2')
                        ->table('meter')
                        ->insertGetId( $data );
                  } else {
                    DB::connection('mysql2')
                        ->table('meter')
                        ->where('global_device_id', $gdid)
                        ->update( $datav );
                    $meter_id = false; // Previously 1

                    $datar[] = [
                        "global_device_id" => $gdid,
                        "msn" => $msn,
                        "indv_status" => "1",
                        "remarks" => "Meter Created Successfully",
                    ];
                  }
*/
                  $log = $this->setWakeupAndTransaction($msn, $now, $slug, $transaction, $gdid);

                  DB::connection('mysql2')->commit();

                  //if ($this->update_meter_visuals_for_write_services) {
                  $data_m = [
                    'msim_id' => $form_params['sim_number'],
                    'mdi_reset_time' => $form_params['mdi_reset_time'],
                    'mdi_reset_date' => $form_params['mdi_reset_date'],
                    'dmdt_communication_interval' => $form_params['communication_interval'],
                    'dmdt_datetime' => now(),
                  ];

                  $inx = DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $gdid)
                    ->update($data_m);
                  //}

                  if ($log) {
                    $datau = ['wakeup_request_id' => $log];
                    $in = DB::connection('mysql2')
                      ->table('meter')
                      ->where('global_device_id', $gdid)
                      ->update($datau);


                    $datar[] = [
                      "global_device_id" => $gdid,
                      "msn" => $msn,
                      "indv_status" => "1",
                      "remarks" => "Meter Created Successfully",
                    ];
                  } else {
                    $datar[] = [
                      "global_device_id" => $gdid,
                      "msn" => $msn,
                      "indv_status" => "0",
                      "remarks" => "Meter Creation Failed",
                    ];
                  }
                } catch (\Exception $e) {
                  DB::connection('mysql2')->rollback();
                  $datar[] = [
                    "global_device_id" => $gdid,
                    "msn" => $msn,
                    "indv_status" => "0",
                    "remarks" => "Unknown DB error occurred. $e",
                  ];
                }
              }

              $response['status'] = 1;
              $response['http_status'] = 200;
              $response['data'] = $datar;
              $response['message'] = 'Devices having indv_status equal to 1 are Created Successfully.';
            }
            break;
          }

          // endregion

        default:
          $response['status'] = 0;
          $response['http_status'] = 500;
          $response['message'] = 'Unknown error occured. Please, try again';
          break;
      }
    } catch (Exception $ex) {
      $response['status'] = false;
      $response['http_status'] = 500;
      $response['message'] = $ex;
    }

    return $response;
  }

  public function update_meter($global_device_id, $datau)
  {
    $in = DB::connection('mysql2')
      ->table('meter')
      ->where('global_device_id', $global_device_id)
      ->update($datau);
  }

  public function update_response_fail(&$response, $http_status, $message)
  {
    $this->update_response($response, 0, $http_status, $message);
  }

  public function update_response(&$response, $status, $http_status, $message)
  {
    $response['status'] = $status;
    $response['http_status'] = $http_status;
    $response['message'] = $message;
  }

  public function insert_event($msn, $gdid, $code, $description, $now)
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

  public function insert_data($table, $data)
  {
    return DB::connection('mysql2')
      ->table($table)
      ->insertGetId($data);
  }

  public function applyCommonValidation($slug, &$headers, &$form_params)
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
      }
    } else if ($slug != 'authorization_service' && $slug != 'on_demand_parameter_read' && $slug != 'transaction_status' && $slug != 'on_demand_data_read' &&  $slug != 'parameterization_cancellation') {
      // Checking Request datetime
      if (!isset($form_params['request_datetime'])) {
        $response['status'] = 0;
        $response['message'] = 'Request Datetime Field is required';
      } else if (!$this->is_date_valid($form_params['request_datetime'], "Y-m-d H:i:s")) {
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
      $tt = $this->chkDuplicateTransaction($headers['transactionid']);

      if ($tt) {
        $response['status'] = 0;
        $response['message'] = 'This Transaction-ID is already being used. Please, change transaction ID';
      }
    }
    return $response;
  }

  public function validateLogin(&$headers)
  {
    $now = \Carbon\Carbon::now();
    $pk = DB::connection('mysql2')->table('udil_auth')
      ->where('key', '=', $headers['privatekey'])
      ->where('key_time', '>', $now)->count();

    if ($pk > 0)
      return true;

    return false;
  }

  public function filterInteger($val, $min, $max)
  {

    $val = trim($val, '"');
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

  public function is_num($val)
  {

    $val = trim($val, '"');
    return is_numeric($val);
  }

  public function append_prefix($sim_num)
  {
    $op = substr($sim_num, 0, 3);
    $no_zero = substr($sim_num, 1, 10);
    $sim_num = '92' . $no_zero . '';
    return $sim_num;
  }

  public function getMeterByGDID($gd_id)
  {
    return Meter::where('gd_id', $gd_id)->first();
  }

  // Inserts an Entry in Transaction Status Table
  public function insert_transaction_status($global_device_id, $msn, $transaction, $on_demand_type)
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

  // Added New
  // Copied from setWakeupAndTransaction
  // Insert Data in Transaction Status and StartTime and EndTime in Meter Table.
  // $is_data_read = True for OnDemandDataRead, False for OnDemandParameterRead
  public function setOnDemandReadTransactionStatus($is_data_read, $msn, $now, $slug, $transaction, $global_device_id, $on_demand_type, $start_time, $end_time)
  {

    $transaction_status_id = $this->insert_transaction_status($global_device_id, $msn, $transaction, $on_demand_type);

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

    /*
                $datai = [
                    'wakeup_status_log_id'	=> NULL,
                    'msn' 	=> $msn,
                    'reference_no' 	=> 0,
                    'action' 	=> $slug,
                    'req_time' 	=> $now,
                    'req_state' => 1,
                    'modem_ack_time' 	=> $now,
                    'modem_ack_status' 	=> 1,
                    'wakeup_sent_time' 	=> $now,
                    'wakeup_send_status' => 1,
                    'conn_time' 	=> NULL,
                    'conn_status' 	=> 0,
                    'completion_time' 	=> NULL,
                    'completion_status' 	=> 0
                ];

                $wakeup_id = DB::connection('mysql2')
                        ->table('wakeup_status_log')
                        ->insertGetId( $datai );

                $dataii = [
                    'id'	=> NULL,
                    'wakeup_id' 	 => $wakeup_id,
                    'transaction_id' => $transaction,
                    'request_time' => $now,
                    'type' => $slug,
                    'global_device_id' => $global_device_id,
                    'msn' => $msn
                ];

                DB::connection('mysql2')
                        ->table('udil_transaction')
                        ->insert( $dataii );

                return $wakeup_id;
        */
  }

  public function setWakeupAndTransaction($msn, $now, $slug, $transaction, $global_device_id)
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

  public function code_to_slug($mti_type)
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
      default:
        return "unknown";
    }
  }

    public function get_modified_transaction_status($transaction)
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
        $dt->type = $this->code_to_slug($dt->type);

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

  public function chkTransactionAndWakeup($transaction)
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

    //dd($datag);

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
            // TODO: More Checks exists in APIController
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

  public function chkDuplicateTransaction($transaction)
  {
    $cc = DB::connection('mysql2')->table('transaction_status')
      ->where('transaction_id', $transaction)
      ->count();

    if ($cc > 0)
      return true;
    return false;
  }

  public function chkOndemandResponse($transaction, $type, $msn)
  {

    for ($i = 0; $i <= 26; $i++) {

      /*
            $datag = DB::connection('mysql2')->table('udil_transaction')
                        ->join('wakeup_status_log','udil_transaction.wakeup_id','=','wakeup_status_log.wakeup_status_log_id')
                        ->where('transaction_id', '=' ,$transaction)
                        ->get('completion_status')->toArray();

            if($datag[0]->completion_status == 0){
                sleep(26);
            }
            */
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

  // Previous Approach used super_immediate using the Method: chkOndemandResponse
  public function readOndemandStatusLevel($transaction, $type, $global_device_id)
  {

    for ($i = 0; $i <= 26; $i++) {

      /*
            $datag = DB::connection('mysql2')->table('udil_transaction')
                        ->join('wakeup_status_log','udil_transaction.wakeup_id','=','wakeup_status_log.wakeup_status_log_id')
                        ->where('transaction_id', '=' ,$transaction)
                        ->get('completion_status')->toArray();

            if($datag[0]->completion_status == 0){
                sleep(26);
            }
            */
      /*			$type = strtoupper($type);
                        if($type == 'INST'){
                            $columnx = 'super_immediate_pq';
                        }
                        else if($type == 'BILL'){
                            $columnx = 'super_immediate_cb';
                        }
                        else if($type == 'MBIL'){
                            $columnx = 'super_immediate_mb';
                        }
                        else if($type == 'EVNT'){
                            $columnx = 'super_immediate_ev';
                        }
                        else if($type == 'LPRO'){
                            $columnx = 'super_immediate_lp';
                        }
                        else if($type == 'AUXR'){
                            $columnx = 'super_immediate_pq';
                        }
                        else if($type == 'DVTM'){
                            $columnx = 'super_immediate_pq';
                        }
                        else if($type == 'WSIM'){
                            $columnx = 'super_immediate_pq';
                        }
            */

      $datag = DB::connection('mysql2')->table('transaction_status')
        ->where('global_device_id', $global_device_id)
        ->where('transaction_id', '=', $transaction)
        ->get('status_level')->toArray();

      $arrLength = count($datag);
      if ($arrLength > 0) {
        $status_level = $datag[0]->status_level;
        //return true;
        if ($status_level >= 5) {
          return true;
          break;
        } else {
          sleep(11);
        }
      } else {
        // TODO: Transaction_Status Record NOT Found. Should throw an error here instead.
        sleep(11);
      }
    }

    return false;
  }

  public function validate_device_creation_params($params)
  {

    $mandatory = array(
      "device_identity",
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
      if (!isset($params[$mandatory[$i]])) {
        return "$mandatory[$i] field is mandatory";
      } else if ($params[$mandatory[$i]] == "") {
        return "$mandatory[$i] field contains blank value";
      }
    }

    if (!$this->filterInteger($params['device_type'], 1, 1)) {
      return "Invalid value for field 'device_type'. Acceptable Values are 1";
    }

    if (!$this->filterInteger($params['mdi_reset_date'], 1, 28)) {
      return "Invalid value for field 'mdi_reset_date'. Acceptable Values are 1 - 28";
    }

    if (!$this->is_num($params['sim_number']) && strlen($params['sim_number']) != 11) {
      return "Invalid value for field 'sim_number'. Only 11 Digit Numbers are Allowed";
    }

    if (!$this->is_num($params['sim_id'])) {
      return "Invalid value for field 'sim_id'. Only Numbers are Allowed";
    }

    if (!$this->filterInteger($params['phase'], 1, 3)) {
      return "Invalid value for field 'phase'. Acceptable Values are 1 - 3";
    }

    if ($params['phase'] == 2) {
      return "Invalid value for field 'phase'. Acceptable Values are 1 - 3";
    }

    //        if (!$this->filterInteger($params['phase'], 3, 3)) {
    //            return "Invalid value for field 'phase'. Only Three Phase Meters are allowed on this MDC.";
    //        }

    if (!$this->filterInteger($params['meter_type'], 1, 5)) {
      return "Invalid value for field 'meter_type'. Acceptable Values are 1 - 5";
    }

    if (!$this->filterInteger($params['meter_type'], 1, 4)) {
      return "Invalid value for field 'meter_type'. Only Values 1 - 4 are allowed on this MDC.";
    }

    return "";
  }

  public function validate_tiou_update($params)
  {

    $mandatory = array("activation_datetime", "day_profile", "week_profile", "season_profile");

    // "holiday_profile"
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
      // TODO : Add Checks that Tariff Slabs has entries and does not exceeds 4 , and also is in proper time format

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


    // Season Profile
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
      // TODO : Add Start Date Format using date_parse_from_format

      if (!in_array($season_profile['week_profile_name'], $week_profile_names)) {
        return "Season Profile '" . $season_profile['name'] . "' contains WeekProfile named '" . $season_profile['week_profile_name'] . "' which is not defined in WeekProfiles List";
      }

      $season_profile_counter++;
    }

    // Holiday Profile
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

  /*
    public function validate_tiou_week_profile($str) {

        $all_okay = true;
        $week_profiles = json_decode( $str, true );
        foreach($week_profiles as $week_profile) {
            $week_profile_days = $week_profile['weekly_day_profile'];
            if (sizeof($week_profile_days) != 7) {
                $all_okay = false;
            }
        }
        return $all_okay;

    }

    public function is_valid_json($str) {

        $json = json_decode($str, true );

        if ((json_last_error() === JSON_ERROR_NONE)) {
            return true;
        }
        else {
            return false;
        }

    }

    public function validate_update_time_of_use($day_profile, $week_profile, $season_profile) {

        if (!$this->is_valid_json($day_profile)) {
            return false;
        }

        if (!$this->is_valid_json($week_profile)) {
            return false;
        }

        if (!$this->is_valid_json($season_profile)) {
            return false;
        }

    }
*/

  public function validate_load_shedding_slabs($str)
  {

    $slabs = json_decode($str, true);

    if (!(json_last_error() === JSON_ERROR_NONE)) {
      return false;
    }

    $all_okay = true;

    foreach ($slabs as $slab) {

      // TODO: Also Add Time Valid Check here
      if (!isset($slab['action_time'])) {
        $all_okay = false;
      }

      if (!isset($slab['relay_operate'])) {
        $all_okay = false;
      }
    }

    return $all_okay;
  }


  public function is_valid_sim_number($number)
  {
    return (($this->is_num($number)) && (strlen($number) == 11));
  }

  /**
   * Single Result from Database Table meter
   * @param $global_device_id GlobalDeviceID of the Meter
   * @param $columns Array containing the names of Columns to be fetched
   * @return mixed Row from Meter Table. Can be null if no Rcord exists
   */
  public function get_from_meter_single_result($global_device_id, $columns)
  {
    $data_on = DB::connection('mysql2')->table('meter')
      ->where('global_device_id',  $global_device_id)
      ->get($columns)->first();
    return $data_on;
  }

   public function update_on_transaction_success($slug, $gd_id)
  {

    $datauuu = [
      'last_command' => $slug,
      'last_command_datetime' => now(),
    ];

    $in = DB::connection('mysql2')
      ->table('meter_visuals')
      ->where('global_device_id', $gd_id)
      ->update($datauuu);
  }

  public function get_transaction_cancel_response($global_device_id, $msn, $slug_type)
  {
    return [
      "global_device_id" => $global_device_id,
      "msn" => $msn,
      "indv_status" => "1",
      "remarks" => "Transaction Successfully Cancelled for type: " . $this->code_to_slug($slug_type),
    ];
  }

  // region Utility Methods

  public function is_time_valid($val)
  {
    $val = trim($val, '"');
    return preg_match('#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#', $val);
  }

  public function is_date_valid($date, $format)
  {
    $date = trim($date, '"');
    $parsed_date = date_parse_from_format($format, $date);
    if (!$parsed_date['error_count'] && !$parsed_date['warning_count']) {
      return true;
    }
    return false;
  }

  /**
   * Converts a String to PHP Json Object
   * @param $str String to be Converted to Json Object
   * @return mixed Json Object
   */
  public function get_json_from_string($str)
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

  // endregion

}
