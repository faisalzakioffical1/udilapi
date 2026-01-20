<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class ApmsTrippingTest extends ApiTestCase
{
    public function test_required_fields_are_checked(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        $this->postJson('/api/apms_tripping_events', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'One or more mandatory fields are missing',
            ]);
    }

    public function test_type_must_be_supported(): void
    {
        $payload = $this->validApmsPayload([
            'type' => 'invalid',
        ]);

        $this->postJson('/api/apms_tripping_events', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Type field has invalid value. Only these fields are applicable: ovfc uvfc ocfc olfc vufc pffc cufc hapf',
            ]);
    }

    public function test_enable_tripping_must_be_boolean(): void
    {
        $payload = $this->validApmsPayload([
            'enable_tripping' => 3,
        ]);

        $this->postJson('/api/apms_tripping_events', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Field enable_tripping can only have values 0-1',
            ]);
    }

    public function test_log_times_must_be_within_bounds(): void
    {
        $payload = $this->validApmsPayload([
            'critical_event_log_time' => 100000,
        ]);

        $this->postJson('/api/apms_tripping_events', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'critical_event_log_time can only have values between 0 - 86399',
            ]);
    }

    public function test_apms_tripping_creates_profile_and_updates_meter(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-500', '101010101');

        $payload = $this->validApmsPayload([
            'global_device_id' => [[
                'global_device_id' => 'GD-500',
                'msn' => '101010101',
            ]],
            'type' => 'ovfc',
            'critical_event_threshold_limit' => 440,
            'tripping_event_threshold_limit' => 480,
        ]);

        $this->postJson('/api/apms_tripping_events', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'APMS Threshold Limits will be programmed in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $apmsRow = DB::connection('mysql2')
            ->table('param_apms_tripping_events')
            ->where('type', 'ovfc')
            ->first();

        $this->assertNotNull($apmsRow);
        $this->assertSame(440, (int) $apmsRow->critical_event_threshold_limit);
        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-500',
            'write_ovfc' => $apmsRow->id,
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-500',
            'type' => 'WOVFC',
        ], 'mysql2');
    }

    private function validApmsPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-777',
                'msn' => '2020202020',
            ]],
            'type' => 'uvfc',
            'critical_event_threshold_limit' => 400,
            'critical_event_log_time' => 3600,
            'tripping_event_threshold_limit' => 420,
            'tripping_event_log_time' => 7200,
            'enable_tripping' => 1,
            'request_datetime' => '2024-01-01 00:00:00',
        ];

        return array_merge($payload, $overrides);
    }
}
