# UDIL API Reference

This document describes every API exposed through `routes/api.php`. All endpoints respond with JSON in the common format:

```json
{
  "status": 1,
  "http_status": 200,
  "transactionid": "<echoed header>",
  "message": "Human readable status",
  "data": [ ... ]
}
```

Unless noted otherwise, requests must provide the following headers:

| Header        | Required | Notes |
|---------------|----------|-------|
| `transactionid` | Yes | Unique per request. Used to track write jobs and avoid duplicates. |
| `privatekey`    | Yes | Issued by `/api/authorization_service`. Expires after 30 minutes. |
| `Content-Type`  | Yes | `application/json`. |

`global_device_id` is expected as an array of objects (`[{"global_device_id":"<id>","msn":123}]`). The `ApiRequestValidation` middleware accepts raw IDs (string/JSON) and enriches them with MSN references automatically.

## Authentication

### POST `/api/authorization_service`
- **Headers:** `username`, `password`, `code` (no `privatekey`/`transactionid` required).
- **Purpose:** Issues a `privatekey` valid for 30 minutes.
- **Validation:** Requires the MTI credentials `username=mti`, `password=Mti@786#`, `code=36`.
- **Response:** On success returns the generated `privatekey` and its expiry timestamp; otherwise HTTP 401/400/500.

## Device Provisioning

### POST `/api/device_creation`
- **Body:**
  - `device_identity` (JSON array of `{global_device_id, dsn}`) *(mandatory)*.
  - `communication_interval`, `device_type`, `mdi_reset_date`, `mdi_reset_time`, `sim_number`, `sim_id`, `phase`, `meter_type`, `communication_mode`, `communication_type`, `bidirectional_device`, `initial_communication_time` *(all mandatory).* 
  - `global_device_id` array (enriched by middleware).
- **Validation Highlights:**
  - `sim_number` must be 11 digits; `mdi_reset_date` between 1–28; `communication_interval` 0–1440.
  - MSN prefixes enforce phase/meter-type rules (e.g., `3697` single-phase only).
- **Behavior:** Inserts a new meter (or updates existing), writes wakeup defaults, kickstarts wakeup transaction, and updates meter visuals/logs.

### POST `/api/update_device_metadata`
- **Body:** `communication_mode`, `bidirectional_device`, `communication_type`, `phase`, `meter_type` (1–4), `initial_communication_time`, `communication_interval`, `request_datetime`, `global_device_id` array.
- **Behavior:** Switches meter communication profile (keep-alive vs non), updates DW modes, encryption/auth keys, logbook/association IDs, and schedule plans.

### POST `/api/update_wake_up_sim_number`
- **Body:** `wakeup_number_1`, `wakeup_number_2`, `wakeup_number_3` (11-digit strings), `request_datetime`, `global_device_id` array.
- **Behavior:** Updates `meter.wakeup_no*`, sets wakeup profile/group IDs, logs event 307, and optionally records visuals.

### POST `/api/update_mdi_reset_date`
- **Body:** `mdi_reset_date` (1–28), `mdi_reset_time` (`HH:MM:SS`), `request_datetime`, `global_device_id` array.
- **Behavior:** Marks `write_mdi_reset_date=1` and stores the requested reset schedule; adds visuals entry.

### POST `/api/device_creation` *(see above)* also covers initial provisioning.

## Network & Communication

### POST `/api/update_ip_port`
- **Body:** `primary_ip_address`, `secondary_ip_address` (valid IPv4), `primary_port`, `secondary_port` (numeric), `request_datetime`, `global_device_id` array.
- **Behavior:** Creates an IP profile row, assigns it to the meter, logs event 305, updates UDIL log + meter visuals.

### POST `/api/meter_data_sampling`
- **Body:** `activation_datetime`, `data_type` (`INST`, `BILL`, `LPRO`), `sampling_interval` (1–1440), `sampling_initial_time`, `request_datetime`, `global_device_id` array.
- **Behavior:** Flags the corresponding LP write request (lp, lp2, or lp3) with the requested interval and activation time.

### POST `/api/update_meter_status`
- **Body:** `meter_activation_status` (0 = deactivate, 1 = activate), `request_datetime`, `global_device_id` array.
- **Behavior:** Flips `meter.status`, records visuals snapshot, and responds with “Meter is Activated/De-activated”.

### POST `/api/time_synchronization`
- **Body:** `request_datetime`, `global_device_id` array.
- **Behavior:** Sets `super_immediate_cs=1` and `base_time_cs` to 15 minutes in the past so meters sync immediately; message reminds MDC must have correct timezone.

### POST `/api/activate_meter_optical_port`
- **Body:** `optical_port_on_datetime`, `optical_port_off_datetime`, `global_device_id` array.
- **Behavior:** Enables optical access between the provided timestamps, logs operation, and optionally updates UDIL/meter visuals.

## Load & Relay Control

### POST `/api/aux_relay_operations`
- **Body:** `relay_operate` (0 = OFF, 1 = ON), `request_datetime`, `global_device_id` array.
- **Behavior:** Handles special treatment when load-shedding schedule conflicts, updates keepalive schedule to match legacy Watchdog, optionally reprograms LSCH, updates meter visuals, and logs remarks (“Relay will be Turned ON/OFF Soon”).

### POST `/api/load_shedding_scheduling`
- **Body:** `start_datetime`, `end_datetime`, `load_shedding_slabs` (JSON array of `{action_time, relay_operate}`), `request_datetime`, `global_device_id` array.
- **Validation:** `start_datetime < end_datetime`; each slab needs both fields.
- **Behavior:** Creates `load_shedding_schedule`+detail rows, associates with meter, triggers write flag, logs event 304, updates UDIL + visuals.

### POST `/api/sanctioned_load_control`
- **Body:** `load_limit`, `maximum_retries`, `retry_interval`, `threshold_duration`, `retry_clear_interval`, `request_datetime`, `global_device_id` array.
- **Behavior:** Builds default `contactor_params`, writes pointer to meter, logs event 303, records UDIL/visuals.

### POST `/api/update_time_of_use`
- **Body:** `request_datetime`, `activation_datetime`, `day_profile`, `week_profile`, `season_profile` (JSON), optional `holiday_profile`, `global_device_id` array.
- **Validation:** Uses `validate_tiou_update`; ensures consistent profile names and schedule coverage.
- **Behavior:** Inserts activity calendar, day/week/season/holiday profiles, links to meter, logs event 306, updates visuals with raw JSON for on-demand inspection.

### POST `/api/parameterization_cancellation`
- **Body:** `type` (`SANC`, `LSCH`, `TIOU`), `global_device_id` array. `request_datetime` not required (middleware exception).
- **Behavior:** Depending on `type`, resets contactor params, load-shedding schedule, or TOU calendar to defaults using the same helper data as Watchdog; logs events 324/325/326 accordingly.

## APMS & Sampling

### POST `/api/apms_tripping_events`
- **Body:** `type` (`ovfc`, `uvfc`, `ocfc`, `olfc`, `vufc`, `pffc`, `cufc`, `hapf`), `critical_event_threshold_limit`, `critical_event_log_time` (0–86399 seconds), `tripping_event_threshold_limit`, `tripping_event_log_time`, `enable_tripping` (0/1), `request_datetime`, `global_device_id` array.
- **Behavior:** Persists the requested APMS parameters, maps to `write_<type>` meter column, and returns status per device.

## On-Demand Services

### POST `/api/on_demand_data_read`
- **Body:** `global_device_id` (single target), `start_datetime`, `end_datetime`, `type` (`INST`, `BILL`, `MBIL`, `EVNT`, `LPRO`), `request_datetime` (required by middleware).
- **Behavior:** Creates an on-demand transaction, waits for completion (up to ~5.5 minutes), then returns the most recent rows from the relevant table (`instantaneous_data`, `billing_data`, etc.).

### POST `/api/on_demand_parameter_read`
- **Body:** `global_device_id` (single target), `type` within `AUXR,DVTM,SANC,LSCH,TIOU,IPPO,MDSM,OPPO,WSIM,MSIM,MTST,DMDT,MDI,OVFC,UVFC,OCFC,OLFC,VUFC,PFFC,CUFC,HAPF`.
- **Behavior:** Triggers a parameter read transaction and returns the latest snapshot from `meter_visuals` (with helper lookups for complex types like `MDSM`).

## Transactions & Status

### POST `/api/transaction_status`
- **Headers:** `transactionid` (required); no body fields necessary.
- **Behavior:** Returns the Watchdog-style multi-level transaction status history for the given ID (after remapping MTI codes to slugs).

### POST `/api/transaction_cancel`
- **Body:** `global_device_id` array, `request_datetime` (middleware requirement), plus header `transactionid` of the job being cancelled.
- **Behavior:** For each device and job type, verifies cancel eligibility (`status_level < 3`) then resets the corresponding meter flags (e.g., `set_ip_profiles=0`, `write_load_shedding_schedule=0`, etc.).

### POST `/api/time_synchronization` *(covered above)* also logs transaction status type `WDVTM` for progress tracking.

### POST `/api/transaction_status` *(see above).* 

## Notes & Testing

- **Middleware:** `ApiRequestValidation` enforces header presence, request datetime (except for listed slugs), private key validity, duplicate transaction detection, and automatic MSN enrichment.
- **Helpers:** Input validation and device lookups reside in `app/Helpers/helpers.php` (e.g., `validate_device_creation_params`, `validate_tiou_update`).
- **Feature Tests:** All validation paths are exercised under `tests/Feature/Api/*Test.php` to ensure parity with `Watchdog.php`.
