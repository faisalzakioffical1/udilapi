<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class LoadSheddingTest extends ApiTestCase
{
    public function test_start_and_end_datetimes_are_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'start_datetime, end_datetime & load_shedding_slabs fields are mandatory and required',
            ]);
    }

    public function test_dates_must_be_valid_and_in_order(): void
    {
        $payload = $this->validLoadSheddingPayload([
            'start_datetime' => 'invalid',
        ]);

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid dates are provided in start_datetime & end_datetime fields',
            ]);

        $payload = $this->validLoadSheddingPayload([
            'start_datetime' => '2024-01-02 00:00:00',
            'end_datetime' => '2024-01-01 00:00:00',
        ]);

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'end_datetime must be greater than start_datetime',
            ]);
    }

    public function test_slabs_structure_is_validated(): void
    {
        $payload = $this->validLoadSheddingPayload([
            'load_shedding_slabs' => json_encode([
                ['relay_operate' => 1],
            ]),
        ]);

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'action_time OR relay_operate field is missing from load_shedding_slabs',
            ]);
    }

    public function test_request_datetime_is_required(): void
    {
        $payload = $this->validLoadSheddingPayload();
        unset($payload['request_datetime']);

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_no_devices_is_rejected(): void
    {
        $payload = $this->validLoadSheddingPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'No devices provided',
            ]);
    }

    public function test_load_shedding_programs_schedule_and_logs(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);
        config()->set('udil.update_udil_log_for_write_services', true);

        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validLoadSheddingPayload();

        $this->postJson('/api/load_shedding_scheduling', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Load Shedding Schedule will be programmed against meters having indv_status equal to 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertNotNull($meter->load_shedding_schedule_id);
        $this->assertSame(1, (int) $meter->write_load_shedding_schedule);

        $this->assertDatabaseHas('load_shedding_schedule', [
            'id' => $meter->load_shedding_schedule_id,
            'name' => 'udil parameterization of loadshedding',
        ], 'mysql2');

        $this->assertEquals(2, DB::connection('mysql2')
            ->table('load_shedding_detail')
            ->where('schedule_id', $meter->load_shedding_schedule_id)
            ->count());

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('load_shedding_scheduling', $visuals->last_command);
        $this->assertNotNull($visuals->lsch_load_shedding_slabs);

        $udilLog = DB::connection('mysql2')
            ->table('udil_log')
            ->where('global_device_id', 'GD-123')
            ->value('lsch');

        $this->assertNotNull($udilLog);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-123',
            'event_code' => 304,
        ], 'mysql2');
    }

    private function validLoadSheddingPayload(array $overrides = []): array
    {
        $slabs = json_encode([
            ['action_time' => '00:00:00', 'relay_operate' => 1],
            ['action_time' => '06:00:00', 'relay_operate' => 0],
        ]);

        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'start_datetime' => '2024-01-01 00:00:00',
            'end_datetime' => '2024-01-31 23:59:59',
            'load_shedding_slabs' => $slabs,
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'load_shedding_scheduling',
        ];

        return array_merge($payload, $overrides);
    }
}
