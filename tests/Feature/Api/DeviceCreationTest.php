<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class DeviceCreationTest extends ApiTestCase
{
    public function test_device_identity_is_mandatory(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/device_creation', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'device_identity field is mandatory',
            ]);
    }

    public function test_device_identity_must_be_json_array(): void
    {
        $payload = $this->validDeviceCreationPayload([
            'device_identity' => 'not-an-array',
        ]);

        $this->postJson('/api/device_creation', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Only Array of devices is allowed',
            ]);
    }

    public function test_keepalive_interval_rules_are_enforced(): void
    {
        $payload = $this->validDeviceCreationPayload([
            'communication_type' => '2',
            'communication_interval' => '15',
        ]);

        $this->postJson('/api/device_creation', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Communication interval must be 0 for keep-alive devices',
            ]);
    }

    public function test_device_creation_updates_existing_meter_and_transaction_log(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validDeviceCreationPayload();

        $this->postJson('/api/device_creation', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Devices having indv_status equal to 1 are Created Successfully.',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('non-keepalive', $meter->class);
        $this->assertSame(2, (int) $meter->set_keepalive);
        $this->assertSame(0, (int) $meter->bidirectional_device);
        $this->assertNotNull($meter->wakeup_request_id);

        $this->assertSame('03001234567', DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->value('msim_id'));

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertEquals(1, DB::connection('mysql2')->table('wakeup_status_log')->count());
        $this->assertEquals(1, DB::connection('mysql2')->table('udil_transaction')->count());
    }

    private function validDeviceCreationPayload(array $overrides = []): array
    {
        $defaultDeviceList = json_encode([
            [
                'global_device_id' => 'GD-123',
                'dsn' => '111222333',
            ],
        ]);

        $payload = [
            'device_identity' => $defaultDeviceList,
            'communication_interval' => '15',
            'device_type' => '1',
            'mdi_reset_date' => '5',
            'mdi_reset_time' => '10:00:00',
            'sim_number' => '03001234567',
            'sim_id' => '1234567890',
            'phase' => '1',
            'meter_type' => '1',
            'communication_mode' => 1,
            'communication_type' => '1',
            'bidirectional_device' => '0',
            'initial_communication_time' => '00:15:00',
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'device_creation',
        ];

        return array_merge($payload, $overrides);
    }
}
