<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class MdiResetTest extends ApiTestCase
{
    public function test_mdi_fields_are_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_mdi_reset_date', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'mdi_reset_date OR mdi_reset_time fields are missing',
            ]);
    }

    public function test_mdi_fields_must_have_valid_values(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'mdi_reset_date' => 0,
            'mdi_reset_time' => 'invalid',
        ];

        $this->postJson('/api/update_mdi_reset_date', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid values are provided in mdi_reset_date OR mdi_reset_time fields',
            ]);
    }

    public function test_mdi_reset_programs_meter_and_visuals(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-610', '987654321');

        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-610',
                'msn' => '987654321',
            ]],
            'mdi_reset_date' => 5,
            'mdi_reset_time' => '12:34:00',
        ];

        $this->postJson('/api/update_mdi_reset_date', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'New MDI RESET date will be programmed in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-610',
            'mdi_reset_date' => 5,
            'mdi_reset_time' => '12:34:00',
            'write_mdi_reset_date' => 1,
        ], 'mysql2');

        $this->assertDatabaseHas('meter_visuals', [
            'global_device_id' => 'GD-610',
            'mdi_reset_date' => 5,
            'mdi_reset_time' => '00:00:00',
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-610',
            'type' => 'WMDI',
        ], 'mysql2');
    }
}
