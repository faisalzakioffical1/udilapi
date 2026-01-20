<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class DeviceMetadataTest extends ApiTestCase
{
    public function test_mandatory_fields_are_enforced(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_device_metadata', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Mandatory fields are missing in update_device_metadata service',
            ]);
    }

    public function test_request_datetime_must_follow_expected_format(): void
    {
        $payload = $this->validDeviceMetadataPayload([
            'request_datetime' => '2024/01/01',
        ]);

        $this->postJson('/api/update_device_metadata', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime must be in Y-m-d H:i:s format',
            ]);
    }

    public function test_global_device_id_list_is_required(): void
    {
        $payload = $this->validDeviceMetadataPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/update_device_metadata', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_device_metadata_updates_meter_and_visuals(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validDeviceMetadataPayload();

        $this->postJson('/api/update_device_metadata', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Device Metadata is applied to meters with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'Meter Communication Mode will be changed');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('non-keepalive', $meter->class);
        $this->assertSame(10, (int) $meter->dw_normal_mode_id);
        $this->assertSame(2, (int) $meter->energy_param_id);
        $this->assertSame(0, (int) $meter->bidirectional_device);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('update_device_metadata', $visuals->last_command);
        $this->assertNotNull($visuals->last_command_datetime);
    }

    private function validDeviceMetadataPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'communication_mode' => '1',
            'bidirectional_device' => '0',
            'communication_type' => '1',
            'phase' => '1',
            'meter_type' => '1',
            'initial_communication_time' => '00:15:00',
            'communication_interval' => '15',
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'update_device_metadata',
        ];

        return array_merge($payload, $overrides);
    }
}
