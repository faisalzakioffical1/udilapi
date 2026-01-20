<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class OnDemandDataTest extends ApiTestCase
{
    public function test_start_end_and_type_are_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/on_demand_data_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'start_datetime, end_datetime & type fields are mandatory and required',
            ]);
    }

    public function test_dates_must_be_valid(): void
    {
        $payload = $this->validOndemandPayload([
            'start_datetime' => '2024/01/01',
        ]);

        $this->postJson('/api/on_demand_data_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid dates are provided in start_datetime & end_datetime fields',
            ]);
    }

    public function test_type_must_be_valid(): void
    {
        $payload = $this->validOndemandPayload([
            'type' => 'BAD',
        ]);

        $this->postJson('/api/on_demand_data_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'type field has invalid value. Only these fields are applicable INST, BILL, MBIL, EVNT, LPRO',
            ]);
    }

    public function test_transaction_id_is_required(): void
    {
        $payload = $this->validOndemandPayload();

        $this->postJson('/api/on_demand_data_read', $payload, ['privatekey' => 'key-test'])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Transaction ID is required',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validOndemandPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/on_demand_data_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'No devices provided',
            ]);
    }

    public function test_inst_data_returns_payload(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-640', '888777666');

        DB::connection('mysql2')->table('instantaneous_data')->insert([
            'global_device_id' => 'GD-640',
            'msn' => '888777666',
            'db_datetime' => '2024-01-05 00:05:00',
            'voltage' => 230,
            'current' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mysql2')->table('transaction_status')->insert([
            'transaction_id' => 'txn-test',
            'msn' => '888777666',
            'global_device_id' => 'GD-640',
            'command_receiving_datetime' => now(),
            'type' => 'INST',
            'status_level' => 5,
            'status_1_datetime' => now(),
            'status_2_datetime' => now(),
            'status_3_datetime' => now(),
            'status_4_datetime' => now(),
            'status_5_datetime' => now(),
            'indv_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->validOndemandPayload([
            'type' => 'INST',
            'global_device_id' => [[
                'global_device_id' => 'GD-640',
                'msn' => '888777666',
            ]],
        ]);

        $this->postJson('/api/on_demand_data_read', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'On demand data results',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.data.0.voltage', 230)
            ->assertJsonPath('data.0.data.0.current', 5);
    }

    private function validOndemandPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-999',
                'msn' => '111222333',
            ]],
            'start_datetime' => '2024-01-01 00:00:00',
            'end_datetime' => '2024-01-02 00:00:00',
            'type' => 'LPRO',
        ];

        return array_merge($payload, $overrides);
    }
}
