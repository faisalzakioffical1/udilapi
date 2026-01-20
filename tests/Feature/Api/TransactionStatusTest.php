<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class TransactionStatusTest extends ApiTestCase
{
    public function test_transaction_id_header_is_required(): void
    {
        $this->postJson('/api/transaction_status', [])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Transaction ID is required',
            ]);
    }

    public function test_transaction_status_payload_is_returned(): void
    {
        $this->migrateMysql2CoreTables();

        DB::connection('mysql2')->table('transaction_status')->insert([
            'transaction_id' => 'txn-test',
            'msn' => '555111222',
            'global_device_id' => 'GD-700',
            'command_receiving_datetime' => now(),
            'type' => 'WTIOU',
            'status_level' => 5,
            'status_1_datetime' => now(),
            'status_2_datetime' => now(),
            'status_3_datetime' => now(),
            'status_4_datetime' => now(),
            'status_5_datetime' => now(),
            'indv_status' => 1,
            'request_cancelled' => 0,
            'response_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/transaction_status', [], $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'status' => 1,
                'http_status' => 200,
            ])
            ->assertJsonPath('data.0.transactionid', 'txn-test')
            ->assertJsonPath('data.0.global_device_id', 'GD-700')
            ->assertJsonPath('data.0.type', 'update_time_of_use');
    }
}
