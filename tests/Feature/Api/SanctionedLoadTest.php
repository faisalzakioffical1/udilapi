<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class SanctionedLoadTest extends ApiTestCase
{
    public function test_load_limit_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
            'maximum_retries' => 1,
            'retry_interval' => 60,
            'threshold_duration' => 30,
            'retry_clear_interval' => 120,
        ];

        $this->postJson('/api/sanctioned_load_control', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'load_limit Field is missing',
            ]);
    }

    public function test_numeric_fields_are_validated(): void
    {
        $payload = $this->validSanctionedPayload([
            'maximum_retries' => 'abc',
        ]);

        $this->postJson('/api/sanctioned_load_control', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Only Numeric Fields are allowed',
            ]);
    }

    public function test_request_datetime_is_required(): void
    {
        $payload = $this->validSanctionedPayload();
        unset($payload['request_datetime']);

        $this->postJson('/api/sanctioned_load_control', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_no_devices_is_rejected(): void
    {
        $payload = $this->validSanctionedPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/sanctioned_load_control', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'No devices provided',
            ]);
    }

    public function test_sanctioned_load_updates_meter_and_logs(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);
        config()->set('udil.update_udil_log_for_write_services', true);

        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validSanctionedPayload();

        $this->postJson('/api/sanctioned_load_control', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Sanctioned Load Control function will be programmed against meters having indv_status equal to 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'Sanctioned Load Control function will be programmed in meter upon connection');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame(1, (int) $meter->write_contactor_param);
        $this->assertNotNull($meter->contactor_param_id);

        $this->assertDatabaseHas('contactor_params', [
            'id' => $meter->contactor_param_id,
            'retry_count' => 3,
        ], 'mysql2');

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame(500, (int) $visuals->sanc_load_limit);
        $this->assertSame('sanctioned_load_control', $visuals->last_command);

        $udilLog = DB::connection('mysql2')
            ->table('udil_log')
            ->where('global_device_id', 'GD-123')
            ->value('sanc_load_control');

        $this->assertNotNull($udilLog);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-123',
            'event_code' => 303,
        ], 'mysql2');
    }

    private function validSanctionedPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'request_datetime' => '2024-01-01 00:00:00',
            'load_limit' => 500,
            'maximum_retries' => 3,
            'retry_interval' => 60,
            'threshold_duration' => 30,
            'retry_clear_interval' => 180,
            'slug' => 'sanctioned_load_control',
        ];

        return array_merge($payload, $overrides);
    }
}
