<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class OpticalPortTest extends ApiTestCase
{
    public function test_on_time_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'optical_port_off_datetime' => '2024-01-01 01:00:00',
        ];

        $this->postJson('/api/activate_meter_optical_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Optical Port ON Datetime is missing',
            ]);
    }

    public function test_off_time_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'optical_port_on_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/activate_meter_optical_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Optical Port OFF Datetime is missing',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validOpticalPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/activate_meter_optical_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_optical_port_activation_updates_meter_and_logs(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_udil_log_for_write_services', true);
        config()->set('udil.update_meter_visuals_for_write_services', true);

        $this->seedMysql2Meter('GD-400', '444555666');

        $payload = $this->validOpticalPayload([
            'global_device_id' => [[
                'global_device_id' => 'GD-400',
                'msn' => '444555666',
            ]],
        ]);

        $this->postJson('/api/activate_meter_optical_port', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Optical Port ON/OFF Time will be updated in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-400',
            'update_optical_port_access' => 1,
            'optical_port_start_time' => '2024-01-01 00:00:00',
            'optical_port_end_time' => '2024-01-01 01:00:00',
        ], 'mysql2');

        $logRow = DB::connection('mysql2')
            ->table('udil_log')
            ->where('global_device_id', 'GD-400')
            ->first();

        $this->assertNotNull($logRow);
        $this->assertStringContainsString('oppo_optical_port_on_datetime', $logRow->optical_port);

        $this->assertDatabaseHas('meter_visuals', [
            'global_device_id' => 'GD-400',
            'oppo_optical_port_on_datetime' => '2024-01-01 00:00:00',
            'oppo_optical_port_off_datetime' => '2024-01-01 01:00:00',
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-400',
            'type' => 'WOPPO',
        ], 'mysql2');
    }

    private function validOpticalPayload(array $overrides = []): array
    {
        $payload = [
            'transaction' => 'txn-test',
            'global_device_id' => [[
                'global_device_id' => 'GD-XXX',
                'msn' => '000111222',
            ]],
            'optical_port_on_datetime' => '2024-01-01 00:00:00',
            'optical_port_off_datetime' => '2024-01-01 01:00:00',
        ];

        return array_merge($payload, $overrides);
    }
}
