<?php

namespace Tests\Feature\Api;

class MeterDataSamplingTest extends ApiTestCase
{
    public function test_required_fields_are_validated(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/meter_data_sampling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Mandatory Input Fields are missing',
            ]);
    }

    public function test_data_type_must_be_supported(): void
    {
        $payload = $this->validSamplingPayload([
            'data_type' => 'INVALID',
        ]);

        $this->postJson('/api/meter_data_sampling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Field data_type has invalid value. Only these fields are applicable: INST BILL LPRO',
            ]);
    }

    public function test_sampling_interval_must_be_in_range(): void
    {
        $payload = $this->validSamplingPayload([
            'sampling_interval' => 0,
        ]);

        $this->postJson('/api/meter_data_sampling', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Provided Sampling Interval value is not in valid range i.e. 15 to 1440 Minutes',
            ]);
    }

    public function test_inst_type_updates_lp2_columns(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-300', '999888777');

        $payload = $this->validSamplingPayload([
            'data_type' => 'INST',
            'sampling_interval' => 30,
            'global_device_id' => [[
                'global_device_id' => 'GD-300',
                'msn' => '999888777',
            ]],
        ]);

        $this->postJson('/api/meter_data_sampling', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Load Profile Sampling Interval will be programmed in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-300',
            'lp2_write_interval_request' => 1,
            'lp2_write_interval' => 30,
            'lp2_interval_activation_datetime' => '2024-01-02 00:00:00',
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-300',
            'type' => 'WMDSM',
        ], 'mysql2');
    }

    public function test_bill_type_updates_lp3_columns(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-301', '123123123');

        $payload = $this->validSamplingPayload([
            'data_type' => 'BILL',
            'sampling_interval' => 60,
            'global_device_id' => [[
                'global_device_id' => 'GD-301',
                'msn' => '123123123',
            ]],
        ]);

        $this->postJson('/api/meter_data_sampling', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-301',
            'lp3_write_interval_request' => 1,
            'lp3_write_interval' => 60,
            'lp3_interval_activation_datetime' => '2024-01-02 00:00:00',
        ], 'mysql2');
    }

    private function validSamplingPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-999',
                'msn' => '111222333',
            ]],
            'activation_datetime' => '2024-01-02 00:00:00',
            'data_type' => 'LPRO',
            'sampling_interval' => 15,
            'sampling_initial_time' => '00:00:00',
        ];

        return array_merge($payload, $overrides);
    }
}
