<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiRequestValidation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Middleware hits external DBs; disable for focused controller tests
        $this->withoutMiddleware(ApiRequestValidation::class);
    }

    protected function defaultHeaders(array $overrides = []): array
    {
        return array_merge([
            'transactionid' => 'txn-test',
            'privatekey' => 'key-test',
        ], $overrides);
    }

    protected function sampleDevice(array $overrides = []): array
    {
        return array_merge([
            'global_device_id' => 'GDD-001',
            'msn' => 123456789,
        ], $overrides);
    }

    protected function useSqliteMysql2Connection(): void
    {
        config()->set('database.connections.mysql2', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('mysql2');
        DB::reconnect('mysql2');

        Schema::connection('mysql2')->dropAllTables();
    }

    protected function migrateMysql2CoreTables(): void
    {
        $this->useSqliteMysql2Connection();

        Schema::connection('mysql2')->create('meter', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id')->unique();
            $table->string('msn');
            $table->string('mdi_reset_time')->nullable();
            $table->string('mdi_reset_date')->nullable();
            $table->integer('write_mdi_reset_date')->nullable();
            $table->integer('status')->default(0);
            $table->string('class')->nullable();
            $table->integer('type')->nullable();
            $table->integer('sch_pq')->nullable();
            $table->string('interval_pq')->nullable();
            $table->integer('sch_cb')->nullable();
            $table->string('interval_cb')->nullable();
            $table->integer('sch_mb')->nullable();
            $table->string('interval_mb')->nullable();
            $table->integer('sch_ev')->nullable();
            $table->string('interval_ev')->nullable();
            $table->integer('sch_lp')->nullable();
            $table->integer('sch_lp2')->nullable();
            $table->integer('sch_lp3')->nullable();
            $table->string('interval_lp')->nullable();
            $table->string('interval_lp2')->nullable();
            $table->string('interval_lp3')->nullable();
            $table->integer('lp_write_interval_request')->nullable();
            $table->integer('lp2_write_interval_request')->nullable();
            $table->integer('lp3_write_interval_request')->nullable();
            $table->integer('lp_write_interval')->nullable();
            $table->integer('lp2_write_interval')->nullable();
            $table->integer('lp3_write_interval')->nullable();
            $table->string('lp_interval_initial_time')->nullable();
            $table->string('lp2_interval_initial_time')->nullable();
            $table->string('lp3_interval_initial_time')->nullable();
            $table->timestamp('lp_interval_activation_datetime')->nullable();
            $table->timestamp('lp2_interval_activation_datetime')->nullable();
            $table->timestamp('lp3_interval_activation_datetime')->nullable();
            $table->string('kas_interval')->nullable();
            $table->integer('save_sch_pq')->nullable();
            $table->string('save_interval_pq')->nullable();
            $table->integer('sch_ss')->nullable();
            $table->integer('sch_cs')->nullable();
            $table->string('interval_cs')->nullable();
            $table->integer('set_keepalive')->nullable();
            $table->integer('super_immediate_pq')->nullable();
            $table->integer('bidirectional_device')->nullable();
            $table->integer('dw_normal_mode_id')->nullable();
            $table->integer('dw_alternate_mode_id')->nullable();
            $table->integer('energy_param_id')->nullable();
            $table->integer('max_billing_months')->nullable();
            $table->integer('load_profile_group_id')->nullable();
            $table->string('encryption_key')->nullable();
            $table->string('authentication_key')->nullable();
            $table->string('master_key')->nullable();
            $table->integer('dds_compatible')->nullable();
            $table->integer('max_events_entries')->nullable();
            $table->integer('association_id')->nullable();
            $table->integer('read_logbook')->nullable();
            $table->integer('lp_invalid_update')->nullable();
            $table->integer('lp2_invalid_update')->nullable();
            $table->integer('lp3_invalid_update')->nullable();
            $table->integer('ev_invalid_update')->nullable();
            $table->string('schedule_plan')->nullable();
            $table->integer('wakeup_request_id')->nullable();
            $table->string('wakeup_no1')->nullable();
            $table->string('wakeup_no2')->nullable();
            $table->string('wakeup_no3')->nullable();
            $table->integer('set_wakeup_profile_id')->nullable();
            $table->integer('number_profile_group_id')->nullable();
            $table->timestamp('ondemand_start_time')->nullable();
            $table->timestamp('ondemand_end_time')->nullable();
            $table->integer('max_cs_difference')->nullable();
            $table->integer('super_immediate_cs')->nullable();
            $table->string('base_time_cs')->nullable();
            $table->integer('apply_new_contactor_state')->nullable();
            $table->integer('new_contactor_state')->nullable();
            $table->integer('load_shedding_schedule_id')->nullable();
            $table->integer('write_load_shedding_schedule')->nullable();
            $table->integer('set_ip_profiles')->nullable();
            $table->integer('write_contactor_param')->nullable();
            $table->integer('contactor_param_id')->nullable();
            $table->integer('update_optical_port_access')->nullable();
            $table->timestamp('optical_port_start_time')->nullable();
            $table->timestamp('optical_port_end_time')->nullable();
            $table->integer('write_ovfc')->nullable();
            $table->integer('write_uvfc')->nullable();
            $table->integer('write_ocfc')->nullable();
            $table->integer('write_olfc')->nullable();
            $table->integer('write_vufc')->nullable();
            $table->integer('write_pffc')->nullable();
            $table->integer('write_cufc')->nullable();
            $table->integer('write_hapf')->nullable();
            $table->integer('activity_calendar_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('meter_visuals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id')->unique();
            $table->string('msn')->nullable();
            $table->string('msim_id')->nullable();
            $table->string('mdi_reset_time')->nullable();
            $table->string('mdi_reset_date')->nullable();
            $table->string('dmdt_communication_interval')->nullable();
            $table->timestamp('dmdt_datetime')->nullable();
            $table->string('dmdt_communication_mode')->nullable();
            $table->integer('dmdt_bidirectional_device')->nullable();
            $table->string('dmdt_communication_type')->nullable();
            $table->string('dmdt_initial_communication_time')->nullable();
            $table->string('dmdt_phase')->nullable();
            $table->string('dmdt_meter_type')->nullable();
            $table->integer('mtst_meter_activation_status')->nullable();
            $table->timestamp('mtst_datetime')->nullable();
            $table->timestamp('dvtm_datetime')->nullable();
            $table->string('dvtm_meter_clock')->nullable();
            $table->timestamp('mdsm_datetime')->nullable();
            $table->string('last_command')->nullable();
            $table->timestamp('last_command_datetime')->nullable();
            $table->string('wsim_wakeup_number_1')->nullable();
            $table->string('wsim_wakeup_number_2')->nullable();
            $table->string('wsim_wakeup_number_3')->nullable();
            $table->timestamp('wsim_datetime')->nullable();
            $table->integer('auxr_status')->nullable();
            $table->timestamp('auxr_datetime')->nullable();
            $table->timestamp('ippo_datetime')->nullable();
            $table->string('ippo_primary_ip_address')->nullable();
            $table->string('ippo_secondary_ip_address')->nullable();
            $table->integer('ippo_primary_port')->nullable();
            $table->integer('ippo_secondary_port')->nullable();
            $table->timestamp('oppo_datetime')->nullable();
            $table->timestamp('oppo_optical_port_on_datetime')->nullable();
            $table->timestamp('oppo_optical_port_off_datetime')->nullable();
            $table->text('last_command_resp')->nullable();
            $table->timestamp('last_command_resp_datetime')->nullable();
            $table->timestamp('sanc_datetime')->nullable();
            $table->integer('sanc_load_limit')->nullable();
            $table->integer('sanc_maximum_retries')->nullable();
            $table->integer('sanc_retry_interval')->nullable();
            $table->integer('sanc_threshold_duration')->nullable();
            $table->integer('sanc_retry_clear_interval')->nullable();
            $table->timestamp('lsch_datetime')->nullable();
            $table->timestamp('lsch_start_datetime')->nullable();
            $table->timestamp('lsch_end_datetime')->nullable();
            $table->text('lsch_load_shedding_slabs')->nullable();
            $table->timestamp('tiou_datetime')->nullable();
            $table->text('tiou_day_profile')->nullable();
            $table->text('tiou_week_profile')->nullable();
            $table->text('tiou_season_profile')->nullable();
            $table->text('tiou_holiday_profile')->nullable();
            $table->timestamp('tiou_activation_datetime')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('transaction_status', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transaction_id');
            $table->string('msn');
            $table->string('global_device_id');
            $table->timestamp('command_receiving_datetime')->nullable();
            $table->string('type');
            $table->integer('status_level')->default(0);
            $table->timestamp('status_1_datetime')->nullable();
            $table->timestamp('status_2_datetime')->nullable();
            $table->timestamp('status_3_datetime')->nullable();
            $table->timestamp('status_4_datetime')->nullable();
            $table->timestamp('status_5_datetime')->nullable();
            $table->integer('indv_status')->nullable();
            $table->integer('request_cancelled')->nullable();
            $table->string('request_cancel_reason')->nullable();
            $table->timestamp('request_cancel_datetime')->nullable();
            $table->text('response_data')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('wakeup_status_log', function (Blueprint $table) {
            $table->increments('wakeup_status_log_id');
            $table->string('msn');
            $table->integer('reference_no')->default(0);
            $table->string('action');
            $table->timestamp('req_time')->nullable();
            $table->integer('req_state')->default(1);
            $table->timestamp('modem_ack_time')->nullable();
            $table->integer('modem_ack_status')->default(0);
            $table->timestamp('wakeup_sent_time')->nullable();
            $table->integer('wakeup_send_status')->default(0);
            $table->timestamp('conn_time')->nullable();
            $table->integer('conn_status')->default(0);
            $table->timestamp('completion_time')->nullable();
            $table->integer('completion_status')->default(0);
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('udil_transaction', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('wakeup_id');
            $table->string('transaction_id');
            $table->timestamp('request_time')->nullable();
            $table->string('type');
            $table->string('global_device_id');
            $table->string('msn');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('load_shedding_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schedule_id');
            $table->string('action_time');
            $table->integer('relay_operate');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('event_counter');
            $table->string('msn');
            $table->string('global_device_id');
            $table->timestamp('event_datetime')->nullable();
            $table->integer('event_code');
            $table->string('event_description');
            $table->timestamp('mdc_read_datetime')->nullable();
            $table->timestamp('db_datetime')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('ip_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('ip_profile_id')->nullable();
            $table->string('ip_1');
            $table->string('ip_2');
            $table->integer('w_tcp_port_1');
            $table->integer('w_tcp_port_2');
            $table->integer('w_udp_port')->nullable();
            $table->integer('h_tcp_port')->nullable();
            $table->integer('h_udp_port')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('udil_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id')->unique();
            $table->text('update_ip_port')->nullable();
            $table->text('sanc_load_control')->nullable();
            $table->text('lsch')->nullable();
            $table->text('optical_port')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('udil_auth', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->timestamp('key_time')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('instantaneous_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id');
            $table->string('msn')->nullable();
            $table->timestamp('db_datetime')->nullable();
            $table->integer('voltage')->nullable();
            $table->integer('current')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('load_profile_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id');
            $table->timestamp('db_datetime')->nullable();
            $table->integer('active_energy')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('billing_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id');
            $table->timestamp('db_datetime')->nullable();
            $table->integer('billing_cycle')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('monthly_billing_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('global_device_id');
            $table->timestamp('db_datetime')->nullable();
            $table->integer('month_index')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_apms_tripping_events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->integer('critical_event_threshold_limit');
            $table->string('critical_event_log_time');
            $table->integer('tripping_event_threshold_limit');
            $table->string('tripping_event_log_time');
            $table->integer('enable_tripping');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('contactor_params', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('contactor_param_id')->nullable();
            $table->string('contactor_param_name');
            $table->integer('retry_count');
            $table->integer('retry_auto_interval_in_sec');
            $table->integer('on_retry_expire_auto_interval_min');
            $table->integer('write_monitoring_time');
            $table->integer('write_monitoring_time_t2');
            $table->integer('write_monitoring_time_t3');
            $table->integer('write_monitoring_time_t4');
            $table->integer('monitering_time_over_load');
            $table->integer('monitering_time_over_load_t2');
            $table->integer('monitering_time_over_load_t3');
            $table->integer('monitering_time_over_load_t4');
            $table->integer('write_limit_over_load_total_kW_t1');
            $table->integer('write_limit_over_load_total_kW_t2');
            $table->integer('write_limit_over_load_total_kW_t3');
            $table->integer('write_limit_over_load_total_kW_t4');
            $table->integer('limit_over_load_total_kW_t1');
            $table->integer('limit_over_load_total_kW_t2');
            $table->integer('limit_over_load_total_kW_t3');
            $table->integer('limit_over_load_total_kW_t4');
            $table->integer('contactor_on_pulse_time_ms');
            $table->integer('contactor_off_pulse_time_ms');
            $table->integer('interval_btw_contactor_state_change_sec');
            $table->integer('power_up_delay_to_change_state_sec');
            $table->integer('interval_to_contactor_failure_status_sec');
            $table->integer('optically_connect');
            $table->integer('optically_disconnect');
            $table->integer('tariff_change');
            $table->integer('is_retry_automatic_or_switch');
            $table->integer('reconnect_by_switch_on_expire');
            $table->integer('reconnect_automatic_on_expire');
            $table->integer('turn_contactor_off_overload_t1');
            $table->integer('tunr_contactor_off_overload_t2');
            $table->integer('turn_contactor_off_overload_t3');
            $table->integer('turn_contactor_off_overload_t4');
            $table->integer('write_contactor_param');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('load_shedding_schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schedule_id')->nullable();
            $table->string('name');
            $table->timestamp('activation_date');
            $table->timestamp('expiry_date');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_activity_calendar', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('activation_date');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_day_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->integer('calendar_id');
            $table->integer('day_profile_id');
            $table->string('day_profile_name');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_day_profile_slots', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->integer('calendar_id');
            $table->integer('day_profile_id');
            $table->string('switch_time');
            $table->integer('tariff');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_week_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->integer('calendar_id');
            $table->integer('week_profile_id');
            $table->integer('day1_profile_id');
            $table->integer('day2_profile_id');
            $table->integer('day3_profile_id');
            $table->integer('day4_profile_id');
            $table->integer('day5_profile_id');
            $table->integer('day6_profile_id');
            $table->integer('day7_profile_id');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_season_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->integer('calendar_id');
            $table->integer('week_profile_id');
            $table->date('start_date');
            $table->timestamps();
        });

        Schema::connection('mysql2')->create('param_special_day_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_id')->nullable();
            $table->integer('calendar_id');
            $table->integer('day_profile_id');
            $table->date('special_date');
            $table->timestamps();
        });
    }

    protected function seedMysql2Meter(string $globalDeviceId, string $msn, array $meterOverrides = [], array $visualOverrides = []): void
    {
        $timestamp = now();

        DB::connection('mysql2')->table('meter')->insert(array_merge([
            'global_device_id' => $globalDeviceId,
            'msn' => $msn,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $meterOverrides));

        DB::connection('mysql2')->table('meter_visuals')->insert(array_merge([
            'global_device_id' => $globalDeviceId,
            'msn' => $msn,
            'msim_id' => 'old-sim',
            'mdi_reset_time' => '00:00:00',
            'mdi_reset_date' => '1',
            'dmdt_communication_interval' => '15',
            'dmdt_datetime' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $visualOverrides));

        DB::connection('mysql2')->table('udil_log')->insert([
            'global_device_id' => $globalDeviceId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
