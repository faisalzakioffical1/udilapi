<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class OnDemandParameterTest extends ApiTestCase
{
    public function test_invalid_type_is_rejected(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'type' => 'INVALID',
        ];

        $this->postJson('/api/on_demand_parameter_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Type field has invalid value. Only these fields are applicable: AUXR DVTM SANC LSCH TIOU IPPO MDSM OPPO WSIM MSIM MTST DMDT MDI OVFC UVFC OCFC OLFC VUFC PFFC CUFC HAPF',
            ]);
    }

    public function test_transaction_id_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'type' => 'AUXR',
        ];

        $this->postJson('/api/on_demand_parameter_read', $payload, ['privatekey' => 'key-test'])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Transaction ID is required',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = [
            'type' => 'AUXR',
        ];

        $this->postJson('/api/on_demand_parameter_read', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'No devices provided',
            ]);
    }

    public function test_auxr_read_returns_visual_payload(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-620', '123123123');

        DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-620')
            ->update([
                'msn' => '123123123',
                'auxr_status' => 1,
                'auxr_datetime' => '2024-01-01 00:00:00',
            ]);

        DB::connection('mysql2')->table('transaction_status')->insert([
            'transaction_id' => 'txn-test',
            'msn' => '123123123',
            'global_device_id' => 'GD-620',
            'command_receiving_datetime' => now(),
            'type' => 'AUXR',
            'status_level' => 5,
            'status_1_datetime' => now(),
            'status_2_datetime' => now(),
        ]);

        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-620',
                'msn' => '123123123',
            ]],
            'type' => 'AUXR',
        ];

        $this->postJson('/api/on_demand_parameter_read', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'On Demand Parameter Read results',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.data.0.auxr_status', 1)
            ->assertJsonPath('data.0.data.0.auxr_datetime', '2024-01-01 00:00:00');
    }
}
