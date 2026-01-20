<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnDemandParameterController extends Controller
{
    private const SUPPORTED_TYPES = [
        'AUXR', 'DVTM', 'SANC', 'LSCH', 'TIOU', 'IPPO', 'MDSM', 'OPPO', 'WSIM', 'MSIM',
        'MTST', 'DMDT', 'MDI', 'OVFC', 'UVFC', 'OCFC', 'OLFC', 'VUFC', 'PFFC', 'CUFC', 'HAPF',
    ];

    /**
     * On Demand Parameter Read for given meters.
     */
    public function read(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (empty($transaction)) {
            $response['message'] = 'Transaction ID is required';
            return response()->json($response, 400);
        }

        if (!isset($formParams['type']) || trim((string)$formParams['type']) === '') {
            $response['message'] = 'Type field is required';
            return response()->json($response, 400);
        }

        $type = strtoupper($formParams['type']);
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            $response['message'] = 'Type field has invalid value. Only these fields are applicable: ' . implode(' ', self::SUPPORTED_TYPES);
            return response()->json($response, 400);
        }

        if (!array_key_exists('global_device_id', $formParams)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, 400);
        }

        $devices = $this->normalizeDeviceList($formParams['global_device_id']);
        if (!is_array($devices)) {
            $response['message'] = 'global_device_id must be a JSON array of devices';
            return response()->json($response, 400);
        }

        if (empty($devices)) {
            $response['message'] = 'No devices provided';
            return response()->json($response, 400);
        }

        $normalizedDevices = [];
        foreach ($devices as $deviceEntry) {
            $device = is_object($deviceEntry) ? (array)$deviceEntry : $deviceEntry;

            if (!isset($device['global_device_id']) || trim((string)$device['global_device_id']) === '') {
                $response['message'] = 'Each device entry must contain global_device_id';
                return response()->json($response, 400);
            }

            $globalDeviceId = trim((string)$device['global_device_id']);
            if (!is_valid_global_device_id($globalDeviceId)) {
                $response['message'] = "Invalid value for field 'global_device_id'. Only letters, numbers, underscore, and hyphen are allowed";
                return response()->json($response, 400);
            }

            if (array_key_exists('msn', $device) && !is_valid_msn($device['msn'])) {
                $response['message'] = "Invalid value for field 'msn'. Only digits are allowed";
                return response()->json($response, 400);
            }

            $normalizedDevices[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $device['msn'] ?? 0,
            ];
        }

        $now = now()->format('Y-m-d H:i:s');
        $slug = $formParams['slug'] ?? 'on_demand_parameter_read';
        $successfulMeters = 0;

        foreach ($normalizedDevices as $device) {
            $globalDeviceId = $device['global_device_id'];

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $device['msn'],
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            setOnDemandReadTransactionStatus(false, $meterMsn, $now, $slug, $transaction, $globalDeviceId, $type, '0', '0');

            $wakeupResp = readOndemandStatusLevel($transaction, $type, $globalDeviceId);

            if ($wakeupResp) {
                $dataPayload = $this->buildParameterData($meter, $type);

                update_on_transaction_success($slug, $globalDeviceId);

                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $meterMsn,
                    'indv_status' => '1',
                    'remarks' => 'On Demand parameter fetched successfully',
                    'data' => $dataPayload,
                ];

                $successfulMeters++;
            } else {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $meterMsn,
                    'indv_status' => '0',
                    'remarks' => "Network Error, Meter didn't communicated in Maximum allowed threshold of 5.5 minutes",
                ];
            }
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = $successfulMeters > 0
            ? 'On Demand Parameter Read results'
            : 'All requested meters failed to respond within the allowed threshold';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }

    private function normalizeDeviceList($payload): ?array
    {
        if (is_null($payload)) {
            return null;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_string($decoded) || is_numeric($decoded)) {
                    $value = trim((string) $decoded);
                    return $value === '' ? [] : [['global_device_id' => $value]];
                }

                return is_array($decoded) ? $decoded : [];
            }

            $trimmed = trim($payload, "\"' ");
            return $trimmed === '' ? [] : [[
                'global_device_id' => $trimmed,
            ]];
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function buildParameterData($meter, string $type): array
    {
        $table = 'meter_visuals';
        $globalDeviceId = $meter->global_device_id;

        switch ($type) {
            case 'AUXR':
                return $this->visualRows($globalDeviceId, ['global_device_id', 'msn', 'auxr_status', 'auxr_datetime']);
            case 'DVTM':
                return $this->visualRows($globalDeviceId, ['global_device_id', 'msn', 'dvtm_datetime', 'dvtm_meter_clock']);
            case 'SANC':
                $this->hydrateSancRetryInterval($globalDeviceId);
                return $this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'sanc_datetime',
                    'sanc_load_limit',
                    'sanc_maximum_retries',
                    'sanc_retry_interval',
                    'sanc_threshold_duration',
                    'sanc_retry_clear_interval',
                ]);
            case 'LSCH':
                return $this->hydrateJsonColumns($this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'lsch_datetime',
                    'lsch_start_datetime',
                    'lsch_end_datetime',
                    'lsch_load_shedding_slabs',
                ]), ['lsch_load_shedding_slabs']);
            case 'TIOU':
                return $this->hydrateJsonColumns($this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'tiou_datetime',
                    'tiou_day_profile',
                    'tiou_week_profile',
                    'tiou_season_profile',
                    'tiou_holiday_profile',
                    'tiou_activation_datetime',
                ]), ['tiou_day_profile', 'tiou_week_profile', 'tiou_season_profile', 'tiou_holiday_profile']);
            case 'IPPO':
                return $this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'ippo_datetime',
                    'ippo_primary_ip_address',
                    'ippo_secondary_ip_address',
                    'ippo_primary_port',
                    'ippo_secondary_port',
                ]);
            case 'MDSM':
                return $this->buildMdsmPayload($meter);
            case 'OPPO':
                return $this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'oppo_datetime',
                    'oppo_optical_port_on_datetime',
                    'oppo_optical_port_off_datetime',
                ]);
            case 'WSIM':
                return $this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'wsim_wakeup_number_1',
                    'wsim_wakeup_number_2',
                    'wsim_wakeup_number_3',
                    'wsim_datetime',
                ]);
            case 'MSIM':
                return $this->visualRows($globalDeviceId, ['global_device_id', 'msn', 'msim_id']);
            case 'MTST':
                return $this->visualRows($globalDeviceId, ['global_device_id', 'msn', 'mtst_datetime', 'mtst_meter_activation_status']);
            case 'DMDT':
                return $this->hydrateDmdtDefaults($this->visualRows($globalDeviceId, [
                    'global_device_id',
                    'msn',
                    'dmdt_datetime',
                    'dmdt_communication_mode',
                    'dmdt_bidirectional_device',
                    'dmdt_communication_type',
                    'dmdt_communication_interval',
                    'dmdt_initial_communication_time',
                    'dmdt_phase',
                    'dmdt_meter_type',
                ]));
            case 'MDI':
                return $this->visualRows($globalDeviceId, ['global_device_id', 'msn', 'mdi_reset_date', 'mdi_reset_time']);
            case 'OVFC':
            case 'UVFC':
            case 'OCFC':
            case 'OLFC':
            case 'VUFC':
            case 'PFFC':
            case 'CUFC':
            case 'HAPF':
                return $this->buildApmsPayload($meter, $type);
            default:
                return [];
        }
    }

    private function visualRows(string $globalDeviceId, array $columns): array
    {
        return DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', $globalDeviceId)
            ->get($columns)
            ->toArray();
    }

    private function hydrateJsonColumns(array $rows, array $columns): array
    {
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $row->{$column} = get_json_from_string($row->{$column});
            }
        }

        return $rows;
    }

    private function hydrateDmdtDefaults(array $rows): array
    {
        foreach ($rows as $row) {
            $row->dmdt_meter_type = $row->dmdt_meter_type ?? '3';
            $row->dmdt_phase = $row->dmdt_phase ?? '3';
            $row->dmdt_bidirectional_device = $row->dmdt_bidirectional_device ?? '0';
        }

        return $rows;
    }

    private function buildMdsmPayload($meter): array
    {
        $visual = DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', $meter->global_device_id)
            ->get(['mdsm_datetime'])
            ->first();

        $mdsmDatetime = $visual->mdsm_datetime ?? null;

        return [
            $this->mdsmRow($meter, 'lp', 'LPRO', $mdsmDatetime),
            $this->mdsmRow($meter, 'lp2', 'INST', $mdsmDatetime),
            $this->mdsmRow($meter, 'lp3', 'BILL', $mdsmDatetime),
        ];
    }

    private function mdsmRow($meter, string $prefix, string $label, $mdsmDatetime): \stdClass
    {
        $activationField = $prefix . '_interval_activation_datetime';
        $intervalField = $prefix . '_write_interval';
        $initialField = $prefix . '_interval_initial_time';

        return (object) [
            'global_device_id' => $meter->global_device_id,
            'msn' => $meter->msn,
            'mdsm_datetime' => $mdsmDatetime,
            'mdsm_activation_datetime' => $meter->{$activationField} ?? null,
            'mdsm_sampling_interval' => $meter->{$intervalField} ?? null,
            'mdsm_sampling_initial_time' => $meter->{$initialField} ?? null,
            'mdsm_data_type' => $label,
        ];
    }

    private function hydrateSancRetryInterval(string $globalDeviceId): void
    {
        $meter = DB::connection('mysql2')
            ->table('meter')
            ->where('global_device_id', $globalDeviceId)
            ->select('contactor_param_id')
            ->first();

        $contactorParamId = $meter->contactor_param_id ?? null;
        if (!$contactorParamId) {
            return;
        }

        $retryIntervalMin = DB::connection('mysql2')
            ->table('contactor_params')
            ->where('contactor_param_id', $contactorParamId)
            ->value('on_retry_expire_auto_interval_min');

        if ($retryIntervalMin === null) {
            return;
        }

        DB::connection('mysql2')
            ->table('meter_visuals')
            ->where('global_device_id', $globalDeviceId)
            ->update(['sanc_retry_clear_interval' => $retryIntervalMin * 60]);
    }

    private function buildApmsPayload($meter, string $type): array
    {
        $column = $this->apmsColumnForType($type);
        $profileId = $column ? ($meter->{$column} ?? null) : null;

        if (!$profileId) {
            return [];
        }

        $apms = DB::connection('mysql2')
            ->table('param_apms_tripping_events')
            ->where('id', $profileId)
            ->first();

        if (!$apms) {
            return [];
        }

        return [
            (object) [
                'global_device_id' => $meter->global_device_id,
                'msn' => $meter->msn,
                'type' => $type,
                'critical_event_threshold_limit' => (int) $apms->critical_event_threshold_limit,
                'critical_event_log_time' => $apms->critical_event_log_time,
                'tripping_event_threshold_limit' => (int) $apms->tripping_event_threshold_limit,
                'tripping_event_log_time' => $apms->tripping_event_log_time,
                'enable_tripping' => (int) $apms->enable_tripping,
            ],
        ];
    }

    private function apmsColumnForType(string $type): ?string
    {
        return match ($type) {
            'OVFC' => 'write_ovfc',
            'UVFC' => 'write_uvfc',
            'OCFC' => 'write_ocfc',
            'OLFC' => 'write_olfc',
            'VUFC' => 'write_vufc',
            'PFFC' => 'write_pffc',
            'CUFC' => 'write_cufc',
            'HAPF' => 'write_hapf',
            default => null,
        };
    }
}
