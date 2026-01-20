<?php

namespace Tests\Feature\Api;

class MeterStatusTest extends ApiTestCase
{
    public function test_meter_activation_status_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_meter_status', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Meter Activation Status variable is missing',
            ]);
    }

    public function test_meter_activation_status_must_be_boolean_flag(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
            'meter_activation_status' => 3,
        ];

        $this->postJson('/api/update_meter_status', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Meter Activation Status is not valid Only 0 for Inactive or 1 for Active is acceptable',
            ]);
    }

    public function test_request_datetime_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'meter_activation_status' => 1,
        ];

        $this->postJson('/api/update_meter_status', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_request_datetime_must_be_valid(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'meter_activation_status' => 1,
            'request_datetime' => 'invalid-date',
        ];

        $this->postJson('/api/update_meter_status', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime must be in Y-m-d H:i:s format',
            ]);
    }

    public function test_meter_status_updates_meter_and_visuals(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);

        $this->seedMysql2Meter('GD-200', '555666777');

        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-200',
                'msn' => '555666777',
            ]],
            'meter_activation_status' => 1,
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/update_meter_status', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Status of meters has been changed with indv_status = 1',
                'transactionid' => 'txn-test',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-200',
            'status' => 1,
        ], 'mysql2');

        $this->assertDatabaseHas('meter_visuals', [
            'global_device_id' => 'GD-200',
            'mtst_meter_activation_status' => 1,
            'last_command' => 'update_meter_status',
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-200',
            'type' => 'WMTST',
        ], 'mysql2');
    }
}
