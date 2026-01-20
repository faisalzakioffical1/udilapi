<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeOfUseController extends Controller
{

    public function update(Request $request)
    {
        $transaction = $request->header('transactionid', '0');
        $formParams = $request->all();

        $response = [
            'status' => 0,
            'http_status' => 400,
            'transactionid' => $transaction,
        ];
        $datar = [];

        if (!isset($formParams['request_datetime'])) {
            $response['message'] = 'request_datetime field is required';
            return response()->json($response, $response['http_status']);
        }

        if (function_exists('is_date_valid') && !is_date_valid($formParams['request_datetime'], 'Y-m-d H:i:s')) {
            $response['message'] = 'request_datetime must be in Y-m-d H:i:s format';
            return response()->json($response, $response['http_status']);
        }

        $requestDatetime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            trim($formParams['request_datetime'])
        )->format('Y-m-d H:i:s');

        $tiouErrorStatus = validate_tiou_update($formParams);
        if ($tiouErrorStatus !== '') {
            $response['message'] = $tiouErrorStatus;
            return response()->json($response, $response['http_status']);
        }

        $allDevices = $formParams['global_device_id'] ?? [];
        if (empty($allDevices)) {
            $response['message'] = 'Global Device ID is required';
            return response()->json($response, $response['http_status']);
        }

        $slug = $formParams['slug'] ?? 'update_time_of_use';
        $successfulMeters = 0;

        $dayProfiles = json_decode($formParams['day_profile'], true);
        $weekProfiles = json_decode($formParams['week_profile'], true);
        $seasonProfiles = json_decode($formParams['season_profile'], true);
        $holidayProfiles = isset($formParams['holiday_profile']) ? json_decode($formParams['holiday_profile'], true) : null;

        foreach ($allDevices as $device) {
            $globalDeviceId = $device['global_device_id'] ?? '';
            $msn = $device['msn'] ?? 0;

            if ($msn == 0 || empty($globalDeviceId)) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meter = DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->first();

            if (!$meter) {
                $datar[] = [
                    'global_device_id' => $globalDeviceId,
                    'msn' => $msn,
                    'indv_status' => '0',
                    'remarks' => config('udil.meter_not_exists'),
                ];
                continue;
            }

            $meterMsn = $meter->msn;

            insert_transaction_status($globalDeviceId, $meterMsn, $transaction, 'WTIOU');

            $activityCalendarId = insert_data('param_activity_calendar', [
                'pk_id' => null,
                'activation_date' => $formParams['activation_datetime'],
            ]);

            $dayProfileNames = [];
            $weekProfileNames = [];
            $initialDayProfile = 1;

            $dpCounter = 0;
            foreach ($dayProfiles as $dayProfile) {
                $dpCounter++;
                $dayProfileNames[$dayProfile['name']] = $dpCounter;
                $tariffSlabs = $dayProfile['tariff_slabs'];

                insert_data('param_day_profile', [
                    'pk_id' => null,
                    'calendar_id' => $activityCalendarId,
                    'day_profile_id' => $dpCounter,
                    'day_profile_name' => $dayProfile['name'],
                ]);

                foreach ($tariffSlabs as $index => $slot) {
                    insert_data('param_day_profile_slots', [
                        'pk_id' => null,
                        'calendar_id' => $activityCalendarId,
                        'day_profile_id' => $dpCounter,
                        'switch_time' => $slot,
                        'tariff' => $index + 1,
                    ]);
                }
            }

            $wpCounter = 0;
            foreach ($weekProfiles as $weekProfile) {
                $wpCounter++;
                $weekProfileDays = $weekProfile['weekly_day_profile'];

                insert_data('param_week_profile', [
                    'pk_id' => null,
                    'calendar_id' => $activityCalendarId,
                    'week_profile_id' => $wpCounter,
                    'day1_profile_id' => $dayProfileNames[$weekProfileDays[0]],
                    'day2_profile_id' => $dayProfileNames[$weekProfileDays[1]],
                    'day3_profile_id' => $dayProfileNames[$weekProfileDays[2]],
                    'day4_profile_id' => $dayProfileNames[$weekProfileDays[3]],
                    'day5_profile_id' => $dayProfileNames[$weekProfileDays[4]],
                    'day6_profile_id' => $dayProfileNames[$weekProfileDays[5]],
                    'day7_profile_id' => $dayProfileNames[$weekProfileDays[6]],
                ]);

                $weekProfileNames[$weekProfile['name']] = $wpCounter;
            }

            foreach ($seasonProfiles as $seasonProfile) {
                $parsedDate = date_parse_from_format('d-m', $seasonProfile['start_date']);
                insert_data('param_season_profile', [
                    'pk_id' => null,
                    'calendar_id' => $activityCalendarId,
                    'week_profile_id' => $weekProfileNames[$seasonProfile['week_profile_name']],
                    'start_date' => '2023-' . $parsedDate['month'] . '-' . $parsedDate['day'],
                ]);
            }

            if (!empty($holidayProfiles)) {
                foreach ($holidayProfiles as $holidayProfile) {
                    $parsedDate = date_parse_from_format('d-m', $holidayProfile['date']);
                    insert_data('param_special_day_profile', [
                        'pk_id' => null,
                        'calendar_id' => $activityCalendarId,
                        'day_profile_id' => $dayProfileNames[$holidayProfile['day_profile_name']],
                        'special_date' => '2023-' . $parsedDate['month'] . '-' . $parsedDate['day'],
                    ]);
                }
            } else {
                insert_data('param_special_day_profile', [
                    'pk_id' => null,
                    'calendar_id' => $activityCalendarId,
                    'day_profile_id' => $initialDayProfile,
                    'special_date' => '2022-01-01',
                ]);
            }

            DB::connection('mysql2')
                ->table('meter')
                ->where('global_device_id', $globalDeviceId)
                ->update(['activity_calendar_id' => $activityCalendarId]);

            insert_event($meterMsn, $globalDeviceId, 306, 'Time of Use Programmed', $requestDatetime);
            update_on_transaction_success($slug, $globalDeviceId);

            if (config('udil.update_meter_visuals_for_write_services')) {
                $deviceResponse = [
                    'status' => 1,
                    'http_status' => 200,
                    'transactionid' => $transaction,
                    'message' => 'Time of Use will be updated',
                ];

                DB::connection('mysql2')
                    ->table('meter_visuals')
                    ->where('global_device_id', $globalDeviceId)
                    ->update([
                        'tiou_datetime' => $requestDatetime,
                        'tiou_day_profile' => $formParams['day_profile'],
                        'tiou_week_profile' => $formParams['week_profile'],
                        'tiou_season_profile' => $formParams['season_profile'],
                        'tiou_holiday_profile' => $formParams['holiday_profile'] ?? '[]',
                        'tiou_activation_datetime' => $formParams['activation_datetime'],
                        'last_command' => $slug,
                        'last_command_datetime' => $requestDatetime,
                        'last_command_resp' => json_encode($deviceResponse),
                        'last_command_resp_datetime' => $requestDatetime,
                    ]);
            }

            $datar[] = [
                'global_device_id' => $globalDeviceId,
                'msn' => $meterMsn,
                'indv_status' => '1',
                'remarks' => 'Time of Use will be updated',
            ];
            $successfulMeters++;
        }

        $response['status'] = $successfulMeters > 0 ? 1 : 0;
        $response['http_status'] = $successfulMeters > 0 ? 200 : 400;
        $response['message'] = 'Time of Use will be updated for meters having individual status as 1';
        $response['data'] = $datar;

        return response()->json($response, $response['http_status']);
    }
}
