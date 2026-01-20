<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;

class TransactionCancelTest extends ApiTestCase
{
    public function test_global_device_id_is_required(): void
    {
        $this->postJson('/api/transaction_cancel', [], $this->defaultHeaders())
            ->assertStatus(400)
            ->assertJson([
                'message' => 'No devices provided',
            ]);
    }

    public function test_transaction_id_is_required(): void
    {
        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/transaction_cancel', $payload, ['privatekey' => 'key-test'])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Transaction ID is required',
            ]);
    }

    public function test_message_returned_when_no_operations_found(): void
    {
        $this->migrateMysql2CoreTables();

        $payload = [
            'global_device_id' => [$this->sampleDevice()],
        ];

        $this->postJson('/api/transaction_cancel', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.0.remarks', 'No Operations are queued for this Global Device ID for this transaction')
            ->assertJsonPath('data.0.indv_status', '0');
    }

    public function test_wmdsm_transaction_is_cancelled(): void
    {
        $this->migrateMysql2CoreTables();

        $this->seedMysql2Meter('GD-650', '999000111', [
            'lp_write_interval_request' => 1,
            'lp2_write_interval_request' => 1,
            'lp3_write_interval_request' => 1,
        ]);

        DB::connection('mysql2')->table('transaction_status')->insert([
            'transaction_id' => 'txn-test',
            'msn' => '999000111',
            'global_device_id' => 'GD-650',
            'command_receiving_datetime' => now(),
            'type' => 'WMDSM',
            'status_level' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'global_device_id' => [[
                'global_device_id' => 'GD-650',
                'msn' => '999000111',
            ]],
        ];

        $this->postJson('/api/transaction_cancel', $payload, $this->defaultHeaders())
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Transaction will be cancelled in meter with indv_status = 1',
            ])
            ->assertJsonPath('data.0.indv_status', '1')
            ->assertJsonPath('data.0.remarks', 'Transaction Successfully Cancelled for type: meter_data_sampling');

        $this->assertDatabaseHas('meter', [
            'global_device_id' => 'GD-650',
            'lp_write_interval_request' => 0,
            'lp2_write_interval_request' => 0,
            'lp3_write_interval_request' => 0,
        ], 'mysql2');

        $this->assertDatabaseHas('transaction_status', [
            'transaction_id' => 'txn-test',
            'global_device_id' => 'GD-650',
            'request_cancelled' => 1,
        ], 'mysql2');
    }
}
