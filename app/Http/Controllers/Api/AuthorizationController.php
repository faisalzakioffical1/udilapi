<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthorizationController extends Controller
{
    public function authorizeClient(Request $request)
    {
        $headers = $request->headers->all();
        $getFirst = fn($x) => $x[0];
        $headers = array_map($getFirst, $headers);

        $now_30 = Carbon::now()->addMinutes(30);
        $response = [];

        // Validate headers
        if (!isset($headers['username']) || !isset($headers['password']) || !isset($headers['code'])) {
            return response()->json([
                'status' => 0,
                'http_status' => 400,
                'message' => 'Required Parameters like Username, Password or Code are not present in header'
            ], 400);
        }

        // Authentication check
        if ($headers['username'] === 'mti' && $headers['password'] === 'Mti@786#' && $headers['code'] === '36') {

            $private_key = uniqid() . uniqid() . uniqid();

            $inserted = DB::connection('mysql2')->table('udil_auth')->insert([
                'id' => NULL,
                'key' => $private_key,
                'key_time' => $now_30
            ]);

            if ($inserted) {
                return response()->json([
                    'status' => 1,
                    'http_status' => 200,
                    'privatekey' => $private_key,
                    'message' => 'Provided Private key is valid for 30 minutes and it will expire at ' . $now_30
                ], 200);
            }

            return response()->json([
                'status' => 0,
                'http_status' => 500,
                'message' => 'Database Connectivity Failed. Please, retry'
            ], 500);

        }

        return response()->json([
            'status' => 0,
            'http_status' => 401,
            'message' => 'Authentication Failed. Please re-try with correct username / password'
        ], 401);
    }
}
