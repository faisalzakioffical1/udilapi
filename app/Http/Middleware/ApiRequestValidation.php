<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiRequestValidation
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $slug = $request->route()->getName();

            $headersRaw  = $request->headers->all();
            $headers     = array_map(fn($x) => $x[0] ?? null, $headersRaw);
            $formParams  = $request->all();
            $transaction = $headers['transactionid'] ?? null;

            // Ensure transactionid is provided
            if (!$transaction) {
                return response()->json([
                    'status'        => 0,
                    'http_status'   => 400,
                    'transactionid' => '0',
                    'message'       => 'Transaction ID is required in headers'
                ], 400);
            }

            // Apply common validation
            $resp = applyCommonValidation($slug, $headers, $formParams);
            if (!isset($resp['status']) || $resp['status'] === 0) {
                return response()->json([
                    'status'        => 0,
                    'http_status'   => 400,
                    'transactionid' => $transaction,
                    'message'       => $resp['message'] ?? 'Invalid request parameters',
                ], 400);
            }

            // Private key validation
            if ($slug !== 'authorization_service') {
                if (!$this->validateLogin($headers)) {
                    return response()->json([
                        'status'        => 0,
                        'http_status'   => 401,
                        'transactionid' => $transaction,
                        'message'       => 'Private Key Expired. Please regenerate a new private key.',
                    ], 401);
                }
            }

            // Normalize request
            $this->applyRequestFilters($slug, $headers, $formParams);
            $request->merge($formParams);

            return $next($request);

        } catch (Exception $ex) {
            return new JsonResponse([
                'status'        => 0,
                'http_status'   => 500,
                'message'       => $ex->getMessage() ?: 'Unknown error occurred. Please try again.',
                'transactionid' => $request->headers->get('transactionid', '0'),
            ], 500);
        }
    }

    private function applyCommonValidation($slug, array &$headers, array &$formParams)
    {
        $response = ['status' => 1];

        $services = [
            'authorization_service','on_demand_data_read','on_demand_parameter_read',
            'aux_relay_operations','update_wake_up_sim_number','time_synchronization',
            'update_mdi_reset_date','transaction_status','update_device_metadata',
            'device_creation','meter_data_sampling','update_meter_status',
            'sanctioned_load_control','update_ip_port','activate_meter_optical_port',
            'load_shedding_scheduling','update_time_of_use','parameterization_cancellation',
            'transaction_cancel','apms_tripping_events'
        ];

        if (!in_array($slug, $services)) {
            return ['status' => 0, 'message' => 'Requested Service is not implemented yet'];
        }

        // Private key and transaction ID check
        if ($slug !== 'authorization_service') {
            if (empty($headers['privatekey']) || empty($headers['transactionid'])) {
                return ['status' => 0, 'message' => 'Private Key or Transaction ID is missing in Request'];
            }
        }

        // Global device ID
        if (!in_array($slug, ['authorization_service','device_creation','transaction_status'])) {
            if (empty($formParams['global_device_id'])) {
                return ['status' => 0, 'message' => 'Global Device ID is required'];
            }

            if (has_invalid_msns($formParams['global_device_id'])) {
                return ['status' => 0, 'message' => "Invalid value for field 'msn'. Only digits are allowed"];
            }
        }

        if ($slug === 'device_creation' && array_key_exists('device_identity', $formParams)) {
            $deviceIdentityValidation = validate_device_identity_list($formParams['device_identity']);
            if ($deviceIdentityValidation['error']) {
                return ['status' => 0, 'message' => $deviceIdentityValidation['error']];
            }
        }

        // Request datetime validation
        if (!in_array($slug, ['authorization_service','on_demand_parameter_read','transaction_status','on_demand_data_read','parameterization_cancellation'])) {
            if (empty($formParams['request_datetime'])) {
                return ['status' => 0, 'message' => 'Request Datetime Field is required'];
            }
            if (!$this->isDateValid($formParams['request_datetime'], 'Y-m-d H:i:s')) {
                return ['status' => 0, 'message' => 'Request Date is invalid'];
            }
        }

        // on_demand services validation
        if (in_array($slug, ['on_demand_parameter_read','on_demand_data_read'])) {
            $deviceId = json_decode($formParams['global_device_id'], true);
            if (!is_string($deviceId)) {
                return ['status' => 0, 'message' => 'Single Global Device ID is required'];
            }
            if (empty($formParams['type'])) {
                return ['status' => 0, 'message' => 'Type field is required'];
            }

            $meter = DB::connection('mysql2')->table('meter')->where('global_device_id', $deviceId)->first();
            if ($meter && $meter->status == 0) {
                return ['status' => 0, 'message' => 'This meter having msn "' . $meter->msn . '" is deactivated.'];
            }
        }

        // Duplicate transaction check
        if (!in_array($slug, ['authorization_service','transaction_status','transaction_cancel'])) {
            $transactionId = $headers['transactionid'];
            if (function_exists('chkDuplicateTransaction') && chkDuplicateTransaction($transactionId)) {
                return ['status' => 0, 'message' => 'This Transaction-ID is already being used. Please, change transaction ID'];
            }
        }

        return $response;
    }

    private function validateLogin(array &$headers)
    {
        $now = Carbon::now();
        return DB::connection('mysql2')->table('udil_auth')
            ->where('key', $headers['privatekey'] ?? '')
            ->where('key_time', '>', $now)
            ->exists();
    }

    private function isDateValid($date, $format)
    {
        $date = trim($date, "\"' ");
        if ($date === '') {
            return false;
        }

        $dt = \DateTime::createFromFormat($format, $date);
        return $dt !== false && $dt->format($format) === $date;
    }

    public function applyRequestFilters($slug, &$headers, &$form_params)
    {
        // Always enforce transaction ID
        $transaction = $headers['transactionid'] ?? '0';
        $form_params['transactionid'] = $transaction;

        // ----------------------------------------------------------
        // Normalize global_device_id into a clean device array
        // ----------------------------------------------------------
        $raw = $form_params['global_device_id'] ?? [];

        // Case 1: JSON string input
        if (is_string($raw)) {
            $devices = json_decode($raw, true);

            // If decoding fails → treat as single ID
            if (!is_array($devices)) {
                $devices = [$raw];
            }
        }
        // Case 2: Already array
        elseif (is_array($raw)) {
            $devices = $raw;
        }
        // Case 3: Unexpected type
        else {
            $devices = [];
        }

        // ----------------------------------------------------------
        // Build enriched device list: attach MSN for each GDID
        // ----------------------------------------------------------
        $all_devices = [];

        foreach ($devices as $dvc) {
            // Normalize each entry into a plain global device id string
            $normalizedId = $this->extractDeviceId($dvc);
            if ($normalizedId === null) {
                continue;
            }

            $msn = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $normalizedId)
                ->value('msn') ?? 0;

            $all_devices[] = [
                'global_device_id' => $normalizedId,
                'msn' => $msn
            ];
        }

        // Final unified request shape
        $form_params['global_device_id'] = $all_devices;
    }

    private function extractDeviceId($device)
    {
        if (is_string($device) || is_numeric($device)) {
            $id = trim((string)$device);
            return $id === '' ? null : $id;
        }

        if (is_array($device)) {
            $candidates = [
                $device['global_device_id'] ?? null,
                $device['gd_id'] ?? null,
                $device['gdid'] ?? null,
            ];

            // If array has a single scalar value without a known key, grab it
            if (count(array_filter($candidates, fn($value) => $value !== null)) === 0) {
                $onlyValue = count($device) === 1 ? reset($device) : null;
                $candidates[] = $onlyValue;
            }

            foreach ($candidates as $id) {
                if (is_string($id) || is_numeric($id)) {
                    $id = trim((string)$id);
                    if ($id !== '') {
                        return $id;
                    }
                }
            }
        }

        return null;
    }

}
