<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class IpPortTest extends ApiTestCase
{
    public function test_primary_ip_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'secondary_ip_address' => '192.168.0.2',
            'primary_port' => 502,
            'secondary_port' => 503,
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_ip_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'primary_ip_address Field is missing',
            ]);
    }

    public function test_invalid_ip_or_port_is_rejected(): void
    {
        $payload = $this->validIpPortPayload([
            'primary_ip_address' => 'not-ip',
        ]);

        $this->postJson('/api/update_ip_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid IP OR Port Fields are provided. Please, correct them',
            ]);
    }

    public function test_request_datetime_is_required(): void
    {
        $payload = $this->validIpPortPayload();
        unset($payload['request_datetime']);

        $this->postJson('/api/update_ip_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validIpPortPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/update_ip_port', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_ip_and_port_updates_meter_and_logs(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);

        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validIpPortPayload();

        $this->postJson('/api/update_ip_port', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'New IP & Port will be programmed against meters having indv_status equal to 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'New IP & Port will be programmed in meter upon connection');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertNotNull($meter->set_ip_profiles);

        $ipProfile = DB::connection('mysql2')
            ->table('ip_profile')
            ->where('id', $meter->set_ip_profiles)
            ->first();

        $this->assertSame('10.0.0.1', $ipProfile->ip_1);
        $this->assertSame('10.0.0.2', $ipProfile->ip_2);
        $this->assertSame(5001, (int) $ipProfile->w_tcp_port_1);
        $this->assertSame(5002, (int) $ipProfile->w_tcp_port_2);

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('10.0.0.1', $visuals->ippo_primary_ip_address);
        $this->assertSame('10.0.0.2', $visuals->ippo_secondary_ip_address);
        $this->assertSame('update_ip_port', $visuals->last_command);
        $this->assertNotNull($visuals->last_command_resp);

        $udilLog = DB::connection('mysql2')
            ->table('udil_log')
            ->where('global_device_id', 'GD-123')
            ->value('update_ip_port');

        $this->assertNotNull($udilLog);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-123',
            'event_code' => 305,
        ], 'mysql2');
    }

    private function validIpPortPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'primary_ip_address' => '10.0.0.1',
            'secondary_ip_address' => '10.0.0.2',
            'primary_port' => 5001,
            'secondary_port' => 5002,
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'update_ip_port',
        ];

        return array_merge($payload, $overrides);
    }
}
