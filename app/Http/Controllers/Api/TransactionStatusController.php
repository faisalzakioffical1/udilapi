<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TransactionStatusController extends Controller
{
    public function transactionStatus(Request $request)
    {
        $transaction = $request->header('transactionid', '');

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];

        if ($transaction === '') {
            $response['message'] = 'Transaction ID is required';
            return response()->json($response, $response['http_status']);
        }

        $records = get_modified_transaction_status($transaction) ?? [];

        if (!empty($records)) {
            $response['status'] = 1;
            $response['http_status'] = 200;
            $response['message'] = 'Transaction status records fetched successfully';
            $response['data'] = $records;

            return response()->json($response, $response['http_status']);
        }

        $fallback = chk_transaction_and_wakeup($transaction);
        $response['data'] = $fallback;

        $firstRow = $fallback[0] ?? null;
        $globalDeviceId = null;
        if (is_array($firstRow)) {
            $globalDeviceId = $firstRow['global_device_id'] ?? null;
        } elseif (is_object($firstRow)) {
            $globalDeviceId = $firstRow->global_device_id ?? null;
        }

        if (empty($globalDeviceId)) {
            $response['message'] = 'Transaction ID not found';
            $response['http_status'] = 404;
            return response()->json($response, $response['http_status']);
        }

        $response['status'] = 1;
        $response['http_status'] = 200;
        $response['message'] = 'Transaction request is queued and pending meter wakeup';

        return response()->json($response, $response['http_status']);
    }
}
