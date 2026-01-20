<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimeSynchronizationTest extends ApiTestCase
{
    public function test_request_datetime_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/time_synchronization', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_request_datetime_must_follow_format(): void
    {
        $payload = $this->validTimeSyncPayload([
            'request_datetime' => '2024/01/01',
        ]);

        $this->postJson('/api/time_synchronization', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime must be in Y-m-d H:i:s format',
            ]);
    }

    public function test_global_device_id_is_required(): void
    {
        $payload = $this->validTimeSyncPayload([
            'global_device_id' => [],
        ]);

        $this->postJson('/api/time_synchronization', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Global Device ID is required',
            ]);
    }

    public function test_time_sync_updates_meter_and_status_logs(): void
    {
        $this->migrateMysql2CoreTables();
        $this->seedMysql2Meter('GD-123', '111222333');

        Carbon::setTestNow('2024-02-01 10:00:00');

        try {
            $payload = $this->validTimeSyncPayload();

            $this->postJson('/api/time_synchronization', $payload, $this->defaultHeaders())
                ->assertStatus(200)
                ->assertJsonFragment([
                    'transactionid' => 'txn-test',
                ])
                ->assertJsonPath('data.0.indv_status', '1')
                ->assertJsonPath('data.0.remarks', 'Time will be synced Soon');
        } finally {
            Carbon::setTestNow();
        }

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame(999999999, (int) $meter->max_cs_difference);
        $this->assertSame(1, (int) $meter->super_immediate_cs);
        $this->assertSame('2024-02-01 09:45:00', $meter->base_time_cs);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('time_synchronization', $visuals->last_command);
        $this->assertNotNull($visuals->last_command_datetime);
    }

    private function validTimeSyncPayload(array $overrides = []): array
    {
        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'request_datetime' => '2024-01-01 00:00:00',
            'slug' => 'time_synchronization',
        ];

        return array_merge($payload, $overrides);
    }
}
