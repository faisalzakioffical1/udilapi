<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class TimeOfUseTest extends ApiTestCase
{
    public function test_request_datetime_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/update_time_of_use', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime field is required',
            ]);
    }

    public function test_request_datetime_must_follow_format(): void
    {
        $payload = $this->validTiouPayload([
            'request_datetime' => '2024/01/01',
        ]);

        $this->postJson('/api/update_time_of_use', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'request_datetime must be in Y-m-d H:i:s format',
            ]);
    }

    public function test_profile_validation_errors_are_reported(): void
    {
        $payload = $this->validTiouPayload();
        unset($payload['day_profile']);

        $this->postJson('/api/update_time_of_use', $payload, $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'day_profile field is mandatory',
            ]);
    }

    public function test_time_of_use_creates_calendar_and_updates_meter(): void
    {
        $this->migrateMysql2CoreTables();
        config()->set('udil.update_meter_visuals_for_write_services', true);

        $this->seedMysql2Meter('GD-123', '111222333');

        $payload = $this->validTiouPayload();

        $this->postJson('/api/update_time_of_use', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'transactionid' => 'txn-test',
                'message' => 'Time of Use will be programmed against meters having indv_status equal to 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1');

        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertNotNull($meter->activity_calendar_id);

        $this->assertDatabaseHas('param_activity_calendar', [
            'id' => $meter->activity_calendar_id,
        ], 'mysql2');

        $this->assertEquals(1, DB::connection('mysql2')
            ->table('param_day_profile')
            ->where('calendar_id', $meter->activity_calendar_id)
            ->count());

        $this->assertEquals(1, DB::connection('mysql2')
            ->table('param_week_profile')
            ->where('calendar_id', $meter->activity_calendar_id)
            ->count());

        $this->assertEquals(1, DB::connection('mysql2')
            ->table('param_season_profile')
            ->where('calendar_id', $meter->activity_calendar_id)
            ->count());

        $this->assertGreaterThan(0, DB::connection('mysql2')
            ->table('param_day_profile_slots')
            ->where('calendar_id', $meter->activity_calendar_id)
            ->count());

        $visuals = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', 'GD-123')
            ->first();

        $this->assertSame('update_time_of_use', $visuals->last_command);
        $this->assertNotNull($visuals->tiou_day_profile);

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-123',
        ], 'mysql2');

        $this->assertDatabaseHas('events', [
            'global_device_id' => 'GD-123',
            'event_code' => 306,
        ], 'mysql2');
    }

    private function validTiouPayload(array $overrides = []): array
    {
        $dayProfile = json_encode([
            [
                'name' => 'DP1',
                'tariff_slabs' => ['00:00:00', '12:00:00'],
            ],
        ]);

        $weekProfile = json_encode([
            [
                'name' => 'WP1',
                'weekly_day_profile' => ['DP1','DP1','DP1','DP1','DP1','DP1','DP1'],
            ],
        ]);

        $seasonProfile = json_encode([
            [
                'name' => 'SP1',
                'week_profile_name' => 'WP1',
                'start_date' => '01-01',
            ],
        ]);

        $holidayProfile = json_encode([
            [
                'name' => 'HP1',
                'day_profile_name' => 'DP1',
                'date' => '14-08',
            ],
        ]);

        $payload = [
            'global_device_id' => [
                [
                    'global_device_id' => 'GD-123',
                    'msn' => '111222333',
                ],
            ],
            'request_datetime' => '2024-01-01 00:00:00',
            'activation_datetime' => '2024-01-10 00:00:00',
            'day_profile' => $dayProfile,
            'week_profile' => $weekProfile,
            'season_profile' => $seasonProfile,
            'holiday_profile' => $holidayProfile,
            'slug' => 'update_time_of_use',
        ];

        return array_merge($payload, $overrides);
    }
}
