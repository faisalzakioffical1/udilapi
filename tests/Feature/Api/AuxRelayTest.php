<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class AuxRelayTest extends ApiTestCase
{
    public function test_relay_operate_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'relay_operate field is required',
            ]);
    }

    public function test_request_datetime_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'relay_operate' => '1',
        ];

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_request_datetime_must_follow_format(): void
    {
        $payload = $this->validAuxPayload([
            'request_datetime' => '2024/01/01',
        ]);

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime must be in Y-m-d H:i:s format',
            ]);
    }

    public function test_relay_operate_must_be_zero_or_one(): void
    {
        $payload = $this->validAuxPayload([
            'relay_operate' => '5',
        ]);

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid value provided in relay_operate Field',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validAuxPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_aux_relay_updates_meter_and_visuals(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);
        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validAuxPayload([
            'relay_operate' => '1',
        ]);

        $this->postJson('/api/aux_relay_operations', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Relay will be turned ON or OFF against meters having indv_status equal to 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'Relay will be Turned ON Soon');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame(1, (int) $meter->apply_new_contactor_state);
        $this->assertSame(1, (int) $meter->new_contactor_state);
        $this->assertSame('non-keepalive', $meter->class);
        $this->assertSame(0, (int) $meter->type);
        $this->assertSame(2, (int) $meter->set_keepalive);

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame(1, (int) $visuals->auxr_status);
        $this->assertNotNull($visuals->auxr_datetime);
        $this->assertSame('aux_relay_operations', $visuals->last_command);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');
    }

    private function validAuxPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'request_datetime' => '2024-01-01 00:00:00',
            'relay_operate' => '0',
            'slug' => 'aux_relay_operations',
        ];

        return array_merge($payload, $overrides);
    }
}
