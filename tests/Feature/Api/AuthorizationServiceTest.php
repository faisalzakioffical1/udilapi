<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthorizationServiceTest extends ApiTestCase
{
    public function test_missing_credentials_are_rejected(): void
    {
        $this->postJson('/api/authorization_service', [])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Required Parameters like Username, Password or Code are not present in header',
            ]);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/api/authorization_service', [], [
            'username' => 'wrong',
            'password' => 'bad',
            'code' => '00',
        ])
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Authentication Failed. Please re-try with correct username / password',
            ]);
    }

    public function test_valid_credentials_return_private_key(): void
    {
        $this->migrateMysql2CoreTables();
        Carbon::setTestNow('2024-01-01 00:00:00');

        $response = $this->postJson('/api/authorization_service', [], [
            'username' => 'mti',
            'password' => 'Mti@786#',
            'code' => '36',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 1,
                'http_status' => 200,
                'message' => 'Provided Private key is valid for 30 minutes and it will expire at 2024-01-01 00:30:00',
            ])
            ->assertJsonStructure(['privatekey']);

        $generatedKey = $response->json('privatekey');
        $this->assertNotEmpty($generatedKey);

        $this->assertDatabaseHas('udil_auth', [
            'key' => $generatedKey,
            'key_time' => '2024-01-01 00:30:00',
        ], 'mysql2');

        Carbon::setTestNow();
    }
}
