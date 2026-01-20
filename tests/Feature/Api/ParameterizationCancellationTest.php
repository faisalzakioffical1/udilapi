<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class ParameterizationCancellationTest extends ApiTestCase
{
    public function test_invalid_type_is_rejected(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'type' => 'INVALID',
        ];

        $this->postJson('/api/parameterization_cancellation', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Type field has invalid value. Only these fields are applicable: SANC LSCH TIOU OVFC UVFC OCFC OLFC VUFC PFFC CUFC HAPF',
            ]);
    }

    public function test_unimplemented_but_valid_type_is_rejected(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'type' => 'OVFC',
        ];

        $this->postJson('/api/parameterization_cancellation', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Type field OVFC is Valid but is NOT Implemented',
            ]);
    }

    public function test_sanc_cancellation_resets_contactor_parameters(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-630', '222333444');

        $payload = $this->validCancellationPayload('SANC', 'GD-630', '222333444');

        $this->postJson('/api/parameterization_cancellation', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Parameters will be set to default in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $contactor = DB::connection('mysql2')->table('contactor_params')->first();
        $this->assertNotNull($contactor);

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-630',
            'write_contactor_param' => 1,
            'contactor_param_id' => $contactor->id,
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-630',
            'event_code' => 324,
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-630',
            'type' => 'WSANC',
        ], 'mysql2');
    }

    public function test_lsch_cancellation_sets_default_schedule(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-631', '333444555');

        DB::connection('mysql2')->table('load_shedding_schedule')->insert([
            'schedule_id' => 105,
            'name' => 'default',
            'activation_date' => '2024-01-01 00:00:00',
            'expiry_date' => '2025-01-01 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->validCancellationPayload('LSCH', 'GD-631', '333444555');

        $this->postJson('/api/parameterization_cancellation', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-631',
            'load_shedding_schedule_id' => 105,
            'write_load_shedding_schedule' => 1,
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-631',
            'event_code' => 325,
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'global_device_id' => 'GD-631',
            'type' => 'WLSCH',
        ], 'mysql2');
    }

    public function test_tiou_cancellation_sets_default_calendar(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-632', '444555666');

        DB::connection('mysql2')->table('param_activity_calendar')->insert([
            'pk_id' => 777,
            'description' => 'udil_parameter_cancel_default',
            'activation_date' => '2023-01-01 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->validCancellationPayload('TIOU', 'GD-632', '444555666');

        $this->postJson('/api/parameterization_cancellation', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-632',
            'activity_calendar_id' => 777,
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-632',
            'event_code' => 326,
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'global_device_id' => 'GD-632',
            'type' => 'WTIOU',
        ], 'mysql2');
    }

    private function validCancellationPayload(string $type, string $gdid, string $msn): array
    {
        return [
            'type' => $type,
            'global_device_id' => [[
                'global_device_id' => $gdid,
                'msn' => $msn,
            ]],
        ];
    }
}
