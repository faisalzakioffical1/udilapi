<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class WakeupSimTest extends ApiTestCase
{
    public function test_wakeup_numbers_are_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_wake_up_sim_number', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'wakeup_number_1, wakeup_number_2 & wakeup_number_3 fields are required',
            ]);
    }

    public function test_wakeup_numbers_must_be_eleven_digits(): void
    {
        $payload = $this->validWakeupPayload([
            'wakeup_number_1' => '12345',
        ]);

        $this->postJson('/api/update_wake_up_sim_number', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Only 11 digit wakeup numbers are allowed',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validWakeupPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/update_wake_up_sim_number', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_numbers_are_written_to_meter_and_logs(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);

        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validWakeupPayload();

        $this->postJson('/api/update_wake_up_sim_number', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Wakeup Numbers will be programmed in meters with indv_status = 1. Try Wakeup by Call for fast connectivity',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'Provided Wakeup Numbers will be programmed in meter upon communication');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('03001234567', $meter->wakeup_no1);
        $this->assertSame('03007654321', $meter->wakeup_no2);
        $this->assertSame('03009876543', $meter->wakeup_no3);
        $this->assertSame(1, (int) $meter->set_wakeup_profile_id);
        $this->assertSame(1, (int) $meter->number_profile_group_id);

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('03001234567', $visuals->wsim_wakeup_number_1);
        $this->assertSame('03007654321', $visuals->wsim_wakeup_number_2);
        $this->assertSame('03009876543', $visuals->wsim_wakeup_number_3);
        $this->assertNotNull($visuals->wsim_datetime);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-123',
            'event_code' => 307,
        ], 'mysql2');
    }

    private function validWakeupPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'wakeup_number_1' => '03001234567',
            'wakeup_number_2' => '03007654321',
            'wakeup_number_3' => '03009876543',
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'update_wake_up_sim_number',
        ];

        return array_merge($payload, $overrides);
    }
}
