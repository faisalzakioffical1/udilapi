<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'UDIL') }} API Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .scroll-container::-webkit-scrollbar { width: 6px; }
        .scroll-container::-webkit-scrollbar-thumb { background-color: rgba(148,163,184,0.4); border-radius: 9999px; }
        .code-block { font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100">
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-slate-900/70 bg-slate-950/80 backdrop-blur">
            <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Realtime API Console</p>
                    <h1 class="text-2xl font-semibold text-white">{{ config('app.name', 'UDIL') }} Watchdog Playground</h1>
                    <p class="text-sm text-slate-400">Trigger any backend endpoint, edit payload templates, and visualize responses without leaving the browser.</p>
                </div>
                <div class="flex gap-8 text-sm">
                    <div>
                        <p class="text-slate-500 text-xs uppercase">Private key</p>
                        <p id="keyStatus" class="font-semibold text-emerald-400">Not connected</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-xs uppercase">Next transaction</p>
                        <p id="transactionPreview" class="font-semibold text-sky-400">pending…</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-1 flex-col lg:flex-row overflow-hidden">
            <aside class="w-full lg:w-80 xl:w-96 border-b border-slate-900/70 lg:border-b-0 lg:border-r bg-slate-950/70 flex flex-col">
                <div class="p-4">
                    <label for="endpointSearch" class="text-xs uppercase text-slate-400">Endpoints</label>
                    <div class="mt-2 relative">
                        <input id="endpointSearch" type="text" placeholder="Find endpoint…" class="w-full rounded-xl border border-slate-800 bg-slate-900/80 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-400" />
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0-6.15a6.25 6.25 0 1 1-12.5 0 6.25 6.25 0 0 1 12.5 0Z" /></svg>
                        </div>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto scroll-container px-3 pb-6" id="endpointListWrapper">
                    <ul id="endpointList" class="space-y-2"></ul>
                </div>
            </aside>

            <main class="flex-1 overflow-y-auto scroll-container">
                <div class="max-w-5xl mx-auto w-full px-4 py-8 space-y-6">
                    <section class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
                            <div class="flex-1">
                                <label class="text-xs uppercase text-slate-400">API base URL</label>
                                <input id="baseUrlInput" type="text" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" value="{{ url('/api') }}" />
                                <p class="mt-2 text-xs text-slate-500">Change this to point the console to staging or production clusters.</p>
                            </div>
                            <div class="flex-1">
                                <label class="text-xs uppercase text-slate-400">Transaction IDs</label>
                                <div class="mt-2 flex gap-3 items-center">
                                    <button id="autoTxnToggle" type="button" class="rounded-full border border-slate-700 px-3 py-2 text-xs font-semibold text-emerald-300">Auto-generate</button>
                                    <input id="transactionInput" type="text" class="flex-1 rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" placeholder="txn-..." />
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Disable auto mode to replay an existing transaction.</p>
                            </div>
                        </div>
                    </section>

                    <section class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold">Key & header management</h2>
                                <p class="text-sm text-slate-400">Request a private key through the authorization service or paste an existing key.</p>
                            </div>
                            <div class="flex gap-2">
                                <button id="authorizeButton" class="rounded-xl bg-emerald-500/90 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-emerald-400">Request private key</button>
                                <button id="clearKeyButton" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:border-rose-500 hover:text-rose-300">Clear key</button>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-xs uppercase text-slate-400">Username</label>
                                <input id="authUsername" type="text" value="mti" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" />
                            </div>
                            <div>
                                <label class="text-xs uppercase text-slate-400">Password</label>
                                <input id="authPassword" type="password" value="Mti@786#" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" />
                            </div>
                            <div>
                                <label class="text-xs uppercase text-slate-400">Code</label>
                                <input id="authCode" type="text" value="36" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" />
                            </div>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="text-xs uppercase text-slate-400">Manual private key</label>
                                <div class="mt-2 flex gap-2">
                                    <input id="manualKeyInput" type="text" placeholder="Paste key from another client" class="flex-1 rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" />
                                    <button id="copyKeyButton" class="rounded-xl border border-slate-700 px-3 py-2 text-xs text-slate-200 hover:border-sky-400">Copy</button>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs uppercase text-slate-400">Key status</label>
                                <textarea id="keyNotes" rows="2" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" readonly>Key not requested yet.</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <p class="text-xs uppercase text-slate-400">Selected endpoint</p>
                                <h2 id="selectedEndpointTitle" class="text-xl font-semibold text-white">-</h2>
                                <p id="selectedEndpointDescription" class="text-sm text-slate-400"></p>
                                <div id="selectedEndpointBadges" class="flex flex-wrap gap-2 text-xs"></div>
                                <p id="selectedEndpointNotes" class="text-xs text-slate-500"></p>
                            </div>
                            <div class="flex gap-2">
                                <button id="resetPayloadButton" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-100 hover:border-sky-400">Reset template</button>
                                <button id="sendRequestButton" class="rounded-xl bg-sky-500/90 px-5 py-2 text-sm font-semibold text-slate-900 hover:bg-sky-400">Send request</button>
                            </div>
                        </div>
                        <div class="grid gap-5 xl:grid-cols-2">
                            <div>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-200">Payload editor</h3>
                                    <p id="payloadSize" class="text-xs text-slate-500"></p>
                                </div>
                                <textarea id="payloadEditor" class="code-block mt-3 w-full rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" rows="20" spellcheck="false" placeholder="{}"></textarea>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span id="jsonValidationMessage" class="font-semibold text-emerald-400">JSON ready</span>
                                    <span class="text-slate-500">Press Ctrl + Enter to send</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-200">Response viewer</h3>
                                <div class="mt-3 rounded-2xl border border-slate-800 bg-slate-950/70 h-[32rem] flex flex-col">
                                    <div id="responsePlaceholder" class="flex-1 flex items-center justify-center text-slate-500 text-sm">Responses will appear here.</div>
                                    <div id="responseContent" class="hidden flex-1 flex-col overflow-hidden">
                                        <div class="border-b border-slate-800 px-4 py-3">
                                            <p id="responseStatus" class="text-lg font-semibold"></p>
                                            <p id="responseMeta" class="text-xs text-slate-500"></p>
                                        </div>
                                        <div class="flex-1 overflow-y-auto scroll-container divide-y divide-slate-800">
                                            <div class="px-4 py-3">
                                                <p class="text-xs uppercase text-slate-500">Body</p>
                                                <pre id="responseBody" class="code-block mt-2 text-xs whitespace-pre-wrap"></pre>
                                            </div>
                                            <div class="px-4 py-3">
                                                <p class="text-xs uppercase text-slate-500">Headers</p>
                                                <pre id="responseHeaders" class="code-block mt-2 text-xs whitespace-pre-wrap"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <div id="toast" class="fixed bottom-6 right-6 hidden rounded-xl bg-slate-900/90 px-4 py-3 text-sm font-semibold text-white shadow-xl"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const defaultBaseUrl = @json(url('/api'));
            const storageKey = 'udil-api-console';
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');

            const formatDateTime = (offsetMinutes = 0) => {
                const d = new Date(Date.now() + offsetMinutes * 60000);
                return d.toISOString().slice(0, 19).replace('T', ' ');
            };

            const sampleDevice = () => ({ global_device_id: 'GLOB123456789', msn: '369812345678' });
            const sampleDevices = () => [sampleDevice()];
            const sampleIdentity = () => ([{ global_device_id: 'GLOB123456789', dsn: '369812345678' }]);
            const sampleSlabs = () => ([
                { action_time: formatDateTime(30), relay_operate: 1 },
                { action_time: formatDateTime(60), relay_operate: 0 },
            ]);
            const sampleDayProfile = () => ([
                { name: 'DAY', start_time: '00:00:00', tariff: 1 },
                { name: 'PEAK', start_time: '18:00:00', tariff: 2 },
            ]);
            const sampleWeekProfile = () => ({
                monday: 'DAY', tuesday: 'DAY', wednesday: 'DAY', thursday: 'DAY', friday: 'DAY', saturday: 'PEAK', sunday: 'PEAK'
            });
            const sampleSeasonProfile = () => ([
                { name: 'SUMMER', start_date: '2025-04-01', end_date: '2025-09-30', week_profile: 'DAY' }
            ]);

            const apiEndpoints = [
                {
                    id: 'authorization_service',
                    label: 'Authorization Service',
                    method: 'POST',
                    path: '/authorization_service',
                    description: 'Generates a private key valid for 30 minutes. Credentials travel via headers.',
                    requiresKey: false,
                    requiresTransaction: false,
                    bodyRequired: false,
                    notes: 'No request body needed. Fill username/password/code and tap "Request private key".',
                },
                {
                    id: 'device_creation',
                    label: 'Device Creation',
                    method: 'POST',
                    path: '/device_creation',
                    description: 'Provision meters with communication, wakeup, and meter class settings.',
                    stringifyFields: ['device_identity'],
                    template: () => ({
                        request_datetime: formatDateTime(),
                        device_identity: sampleIdentity(),
                        communication_mode: 1,
                        communication_type: 1,
                        communication_interval: 15,
                        bidirectional_device: 0,
                        device_type: 'AMI',
                        mdi_reset_date: 12,
                        mdi_reset_time: '00:30:00',
                        sim_number: '03123456789',
                        sim_id: 'SIM123456789',
                        phase: 3,
                        meter_type: '2',
                        initial_communication_time: '01:00:00',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_wake_up_sim_number',
                    label: 'Wakeup SIM Update',
                    method: 'POST',
                    path: '/update_wake_up_sim_number',
                    description: 'Push new wakeup dialer numbers and profile group to meters.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        wakeup_number_1: '03111111111',
                        wakeup_number_2: '03222222222',
                        wakeup_number_3: '03333333333',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'time_synchronization',
                    label: 'Time Synchronization',
                    method: 'POST',
                    path: '/time_synchronization',
                    description: 'Trigger super-immediate clock sync (WDVTM).',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'aux_relay_operations',
                    label: 'Aux Relay Operations',
                    method: 'POST',
                    path: '/aux_relay_operations',
                    description: 'Toggle contactor state or align with load-shedding schedule.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        relay_operate: 1,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_device_metadata',
                    label: 'Device Metadata Update',
                    method: 'POST',
                    path: '/update_device_metadata',
                    description: 'Switch communication profile, bidirectional flags, and interval.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        communication_mode: 1,
                        bidirectional_device: 0,
                        communication_type: 1,
                        phase: 3,
                        meter_type: '2',
                        initial_communication_time: '00:15:00',
                        communication_interval: 15,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_ip_port',
                    label: 'IP & Port Update',
                    method: 'POST',
                    path: '/update_ip_port',
                    description: 'Assign new primary/secondary MDC endpoints.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        primary_ip_address: '10.0.0.10',
                        secondary_ip_address: '10.0.0.11',
                        primary_port: 1700,
                        secondary_port: 1701,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'sanctioned_load_control',
                    label: 'Sanctioned Load Control',
                    method: 'POST',
                    path: '/sanctioned_load_control',
                    description: 'Program contactor thresholds and retry logic.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        load_limit: 40,
                        maximum_retries: 5,
                        retry_interval: 30,
                        threshold_duration: 10,
                        retry_clear_interval: 120,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'load_shedding_scheduling',
                    label: 'Load Shedding Schedule',
                    method: 'POST',
                    path: '/load_shedding_scheduling',
                    description: 'Create or replace LSCH calendar with relay slabs.',
                    stringifyFields: ['load_shedding_slabs'],
                    template: () => ({
                        request_datetime: formatDateTime(),
                        start_datetime: formatDateTime(15),
                        end_datetime: formatDateTime(240),
                        load_shedding_slabs: sampleSlabs(),
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_time_of_use',
                    label: 'Time Of Use Update',
                    method: 'POST',
                    path: '/update_time_of_use',
                    description: 'Push activity calendar (day/week/season/holiday profiles).',
                    stringifyFields: ['day_profile', 'week_profile', 'season_profile', 'holiday_profile'],
                    template: () => ({
                        request_datetime: formatDateTime(),
                        activation_datetime: formatDateTime(1440),
                        day_profile: sampleDayProfile(),
                        week_profile: sampleWeekProfile(),
                        season_profile: sampleSeasonProfile(),
                        holiday_profile: [{ date: '2025-12-25', name: 'Holiday', day_profile: 'DAY' }],
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_meter_status',
                    label: 'Meter Status Update',
                    method: 'POST',
                    path: '/update_meter_status',
                    description: 'Activate or deactivate devices remotely.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        meter_activation_status: 1,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'meter_data_sampling',
                    label: 'Meter Data Sampling',
                    method: 'POST',
                    path: '/meter_data_sampling',
                    description: 'Request LP/instantaneous sampling for diagnostics.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        activation_datetime: formatDateTime(10),
                        data_type: 'LPRO',
                        sampling_interval: 30,
                        sampling_initial_time: '00:15:00',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'activate_meter_optical_port',
                    label: 'Optical Port Access',
                    method: 'POST',
                    path: '/activate_meter_optical_port',
                    description: 'Schedule optical access window.',
                    template: () => ({
                        optical_port_on_datetime: formatDateTime(5),
                        optical_port_off_datetime: formatDateTime(60),
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'apms_tripping_events',
                    label: 'APMS Tripping',
                    method: 'POST',
                    path: '/apms_tripping_events',
                    description: 'Configure APMS thresholds (over/under voltage, etc.).',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        type: 'OVFC',
                        critical_event_threshold_limit: 250,
                        critical_event_log_time: 30,
                        tripping_event_threshold_limit: 280,
                        tripping_event_log_time: 30,
                        enable_tripping: 1,
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'update_mdi_reset_date',
                    label: 'MDI Reset Date',
                    method: 'POST',
                    path: '/update_mdi_reset_date',
                    description: 'Program scheduled MDI reset (WMDI).',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        mdi_reset_date: 12,
                        mdi_reset_time: '02:30:00',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'on_demand_parameter_read',
                    label: 'On-demand Parameter Read',
                    method: 'POST',
                    path: '/on_demand_parameter_read',
                    description: 'Fetch meter visuals snapshots for the requested parameter group.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        type: 'AUXR',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'parameterization_cancellation',
                    label: 'Parameterization Cancel',
                    method: 'POST',
                    path: '/parameterization_cancellation',
                    description: 'Reset pending writes (SANC, LSCH, TIOU).',
                    template: () => ({
                        type: 'SANC',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'transaction_status',
                    label: 'Transaction Status',
                    method: 'POST',
                    path: '/transaction_status',
                    description: 'Watchdog-style multi-level progress for a transaction ID.',
                    bodyRequired: false,
                    notes: 'Provide the transaction ID in headers or disable auto mode and paste it manually.',
                },
                {
                    id: 'on_demand_data_read',
                    label: 'On-demand Data Read',
                    method: 'POST',
                    path: '/on_demand_data_read',
                    description: 'Trigger INST/BILL/EVNT/LPRO reads and wait for completion.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        start_datetime: formatDateTime(-60),
                        end_datetime: formatDateTime(),
                        type: 'INST',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'on_demand_parameter_read_secondary',
                    label: 'On-demand Parameter (Alt)',
                    method: 'POST',
                    path: '/on_demand_parameter_read',
                    description: 'Shortcut entry for other parameter types (e.g., TIOU, LSCH).',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        type: 'TIOU',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'on_demand_data_read_secondary',
                    label: 'On-demand Data (BILL)',
                    method: 'POST',
                    path: '/on_demand_data_read',
                    description: 'Template focusing on billing window reads.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        start_datetime: formatDateTime(-2880),
                        end_datetime: formatDateTime(-1440),
                        type: 'BILL',
                        global_device_id: sampleDevices(),
                    }),
                },
                {
                    id: 'transaction_cancel',
                    label: 'Transaction Cancel',
                    method: 'POST',
                    path: '/transaction_cancel',
                    description: 'Cancel queued writes for specific meters.',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        global_device_id: sampleDevices(),
                    }),
                },
            ];

            const elements = {
                endpointList: document.getElementById('endpointList'),
                endpointSearch: document.getElementById('endpointSearch'),
                payloadEditor: document.getElementById('payloadEditor'),
                payloadSize: document.getElementById('payloadSize'),
                jsonValidationMessage: document.getElementById('jsonValidationMessage'),
                selectedEndpointTitle: document.getElementById('selectedEndpointTitle'),
                selectedEndpointDescription: document.getElementById('selectedEndpointDescription'),
                selectedEndpointBadges: document.getElementById('selectedEndpointBadges'),
                selectedEndpointNotes: document.getElementById('selectedEndpointNotes'),
                baseUrlInput: document.getElementById('baseUrlInput'),
                sendButton: document.getElementById('sendRequestButton'),
                resetButton: document.getElementById('resetPayloadButton'),
                authorizeButton: document.getElementById('authorizeButton'),
                clearKeyButton: document.getElementById('clearKeyButton'),
                manualKeyInput: document.getElementById('manualKeyInput'),
                copyKeyButton: document.getElementById('copyKeyButton'),
                keyNotes: document.getElementById('keyNotes'),
                autoTxnToggle: document.getElementById('autoTxnToggle'),
                transactionInput: document.getElementById('transactionInput'),
                keyStatus: document.getElementById('keyStatus'),
                transactionPreview: document.getElementById('transactionPreview'),
                responsePlaceholder: document.getElementById('responsePlaceholder'),
                responseContent: document.getElementById('responseContent'),
                responseStatus: document.getElementById('responseStatus'),
                responseMeta: document.getElementById('responseMeta'),
                responseBody: document.getElementById('responseBody'),
                responseHeaders: document.getElementById('responseHeaders'),
                toast: document.getElementById('toast'),
                authUsername: document.getElementById('authUsername'),
                authPassword: document.getElementById('authPassword'),
                authCode: document.getElementById('authCode'),
            };

            const state = {
                selectedEndpoint: null,
                payloadCache: {},
                response: null,
                privateKey: saved.privateKey || '',
                keyNotes: saved.keyNotes || 'Key not requested yet.',
                baseUrl: saved.baseUrl || defaultBaseUrl,
                autoTransaction: saved.autoTransaction !== undefined ? saved.autoTransaction : true,
                transactionId: saved.transactionId || `txn-${Date.now()}`,
                jsonValid: true,
                validationError: '',
                loading: false,
            };

            elements.baseUrlInput.value = state.baseUrl;
            elements.keyNotes.value = state.keyNotes;
            elements.manualKeyInput.value = state.privateKey;
            elements.transactionInput.value = state.transactionId;
            updateKeyDisplay();
            updateTransactionPreview();

            function persist() {
                localStorage.setItem(storageKey, JSON.stringify({
                    privateKey: state.privateKey,
                    keyNotes: state.keyNotes,
                    baseUrl: state.baseUrl,
                    autoTransaction: state.autoTransaction,
                    transactionId: state.transactionId,
                }));
            }

            function toast(message, tone = 'info') {
                const palette = tone === 'error'
                    ? 'bg-rose-600/90 text-white'
                    : tone === 'success'
                        ? 'bg-emerald-500/90 text-slate-900'
                        : 'bg-slate-900/90 text-white';
                elements.toast.textContent = message;
                elements.toast.className = `fixed bottom-6 right-6 rounded-xl px-4 py-3 text-sm font-semibold shadow-xl ${palette}`;
                elements.toast.classList.remove('hidden');
                clearTimeout(elements.toast.timer);
                elements.toast.timer = setTimeout(() => elements.toast.classList.add('hidden'), 4000);
            }

            function renderEndpointList(filter = '') {
                elements.endpointList.innerHTML = '';
                apiEndpoints
                    .filter((ep) => ep.label.toLowerCase().includes(filter.toLowerCase()) || ep.path.includes(filter))
                    .forEach((ep) => {
                        const row = document.createElement('li');
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'w-full rounded-2xl border border-transparent bg-slate-900/60 px-4 py-3 text-left hover:border-sky-500/60';
                        button.innerHTML = `
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span class="font-semibold text-sky-300">${ep.method}</span>
                                <span>${ep.path}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-100">${ep.label}</p>
                            <p class="text-xs text-slate-500">${ep.description}</p>
                        `;
                        if (state.selectedEndpoint?.id === ep.id) {
                            button.classList.add('border-sky-500/80', 'bg-slate-900');
                        }
                        button.addEventListener('click', () => selectEndpoint(ep));
                        row.appendChild(button);
                        elements.endpointList.appendChild(row);
                    });
            }

            function selectEndpoint(endpoint) {
                state.selectedEndpoint = endpoint;
                if (!state.payloadCache[endpoint.id]) {
                    state.payloadCache[endpoint.id] = endpoint.template
                        ? JSON.stringify(endpoint.template(), null, 2)
                        : '';
                }
                elements.payloadEditor.value = state.payloadCache[endpoint.id];
                elements.sendButton.textContent = `Send ${endpoint.method}`;
                updatePayloadMeta();
                updateEndpointMeta();
                renderEndpointList(elements.endpointSearch.value || '');
                togglePayloadEditor();
            }

            function updateEndpointMeta() {
                const ep = state.selectedEndpoint;
                if (!ep) return;
                elements.selectedEndpointTitle.textContent = ep.label;
                elements.selectedEndpointDescription.textContent = `${ep.method} ${ep.path}`;
                elements.selectedEndpointNotes.textContent = ep.notes || '';
                elements.selectedEndpointBadges.innerHTML = '';
                const badges = [];
                if (ep.requiresKey !== false) badges.push({ text: 'Private key required', tone: 'emerald' });
                if (ep.requiresTransaction !== false && ep.id !== 'authorization_service') badges.push({ text: 'Transaction header', tone: 'sky' });
                if (ep.stringifyFields?.length) badges.push({ text: `Auto stringify: ${ep.stringifyFields.join(', ')}`, tone: 'amber' });
                if (ep.bodyRequired === false) badges.push({ text: 'Headers only', tone: 'slate' });
                const toneMap = {
                    emerald: 'bg-emerald-500/20 text-emerald-200 border-emerald-500/40',
                    sky: 'bg-sky-500/20 text-sky-200 border-sky-500/40',
                    amber: 'bg-amber-500/20 text-amber-200 border-amber-500/40',
                    slate: 'bg-slate-700/40 text-slate-200 border-slate-600/60',
                };
                badges.forEach((badge) => {
                    const tag = document.createElement('span');
                    tag.className = `rounded-full border px-3 py-1 text-[11px] font-semibold ${toneMap[badge.tone]}`;
                    tag.textContent = badge.text;
                    elements.selectedEndpointBadges.appendChild(tag);
                });
            }

            function updatePayloadMeta() {
                const text = elements.payloadEditor.value || '';
                elements.payloadSize.textContent = `${text.length} chars`;
                validatePayload();
            }

            function validatePayload() {
                const ep = state.selectedEndpoint;
                if (!ep || ep.bodyRequired === false) {
                    state.jsonValid = true;
                    elements.jsonValidationMessage.textContent = ep?.bodyRequired === false ? 'Payload not required' : 'Ready';
                    elements.jsonValidationMessage.className = 'text-xs font-semibold text-slate-400';
                    return;
                }
                try {
                    if (elements.payloadEditor.value.trim() === '') throw new Error('Payload cannot be empty');
                    JSON.parse(elements.payloadEditor.value);
                    state.jsonValid = true;
                    state.validationError = '';
                    elements.jsonValidationMessage.textContent = 'JSON ready';
                    elements.jsonValidationMessage.className = 'text-xs font-semibold text-emerald-400';
                } catch (error) {
                    state.jsonValid = false;
                    state.validationError = error.message;
                    elements.jsonValidationMessage.textContent = error.message;
                    elements.jsonValidationMessage.className = 'text-xs font-semibold text-rose-400';
                }
            }

            function togglePayloadEditor() {
                const disabled = state.selectedEndpoint?.bodyRequired === false;
                elements.payloadEditor.disabled = !!disabled;
                elements.payloadEditor.classList.toggle('opacity-40', !!disabled);
            }

            function updateKeyDisplay() {
                if (state.privateKey) {
                    elements.keyStatus.textContent = `${state.privateKey.slice(0, 10)}…`;
                    elements.keyStatus.className = 'font-semibold text-emerald-300';
                } else {
                    elements.keyStatus.textContent = 'Not connected';
                    elements.keyStatus.className = 'font-semibold text-emerald-400';
                }
                elements.manualKeyInput.value = state.privateKey;
            }

            function updateTransactionPreview() {
                elements.transactionPreview.textContent = state.transactionId;
                elements.transactionInput.disabled = state.autoTransaction;
                elements.autoTxnToggle.textContent = state.autoTransaction ? 'Auto-generate' : 'Manual mode';
                elements.autoTxnToggle.classList.toggle('bg-emerald-500/90', state.autoTransaction);
                elements.autoTxnToggle.classList.toggle('text-slate-900', state.autoTransaction);
            }

            function buildRequestOptions() {
                const ep = state.selectedEndpoint;
                if (!ep) return null;
                const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };

                if (ep.id === 'authorization_service') {
                    headers.username = elements.authUsername.value.trim();
                    headers.password = elements.authPassword.value;
                    headers.code = elements.authCode.value.trim();
                } else {
                    if (!state.privateKey) {
                        toast('Request a private key first.', 'error');
                        return null;
                    }
                    headers.privatekey = state.privateKey;
                    const txn = state.autoTransaction ? `txn-${Date.now()}` : elements.transactionInput.value.trim();
                    if (!txn) {
                        toast('Transaction ID is required.', 'error');
                        return null;
                    }
                    headers.transactionid = txn;
                    state.transactionId = txn;
                    elements.transactionInput.value = txn;
                }

                if (ep.bodyRequired === false) {
                    return { method: ep.method, headers };
                }

                if (!state.jsonValid) {
                    toast(state.validationError || 'Fix JSON before sending', 'error');
                    return null;
                }

                let payload = {};
                if (elements.payloadEditor.value.trim()) {
                    try {
                        payload = JSON.parse(elements.payloadEditor.value);
                    } catch (error) {
                        toast(error.message, 'error');
                        return null;
                    }
                }

                if (ep.stringifyFields?.length) {
                    ep.stringifyFields.forEach((field) => {
                        if (payload[field] && typeof payload[field] !== 'string') {
                            payload[field] = JSON.stringify(payload[field]);
                        }
                    });
                }

                return {
                    method: ep.method,
                    headers,
                    body: JSON.stringify(payload),
                };
            }

            async function sendRequest() {
                const ep = state.selectedEndpoint;
                if (!ep) return;
                const options = buildRequestOptions();
                if (!options) return;

                state.loading = true;
                elements.sendButton.textContent = 'Sending…';
                elements.sendButton.disabled = true;
                try {
                    const started = performance.now();
                    const response = await fetch(`${state.baseUrl}${ep.path}`, options);
                    const latency = Math.round(performance.now() - started);
                    const raw = await response.text();
                    let body;
                    try { body = JSON.parse(raw); } catch { body = raw; }
                    const headers = {};
                    response.headers.forEach((value, key) => { headers[key] = value; });
                    state.response = { status: response.status, statusText: response.statusText, latency, body, headers };
                    renderResponse();

                    if (ep.id === 'authorization_service' && response.ok && body?.privatekey) {
                        state.privateKey = body.privatekey;
                        state.keyNotes = body.message || 'Private key retrieved.';
                        elements.keyNotes.value = state.keyNotes;
                        updateKeyDisplay();
                        toast('Private key saved. You can now call other APIs.', 'success');
                    }

                    if (state.autoTransaction && ep.id !== 'authorization_service') {
                        state.transactionId = `txn-${Date.now() + Math.round(Math.random() * 1000)}`;
                        elements.transactionInput.value = state.transactionId;
                        elements.transactionPreview.textContent = state.transactionId;
                    }

                    persist();
                } catch (error) {
                    toast(error.message, 'error');
                } finally {
                    state.loading = false;
                    elements.sendButton.textContent = `Send ${state.selectedEndpoint?.method || ''}`;
                    elements.sendButton.disabled = false;
                }
            }

            function renderResponse() {
                if (!state.response) return;
                elements.responsePlaceholder.classList.add('hidden');
                elements.responseContent.classList.remove('hidden');
                const { status, statusText, latency, body, headers } = state.response;
                elements.responseStatus.textContent = `${status} ${statusText}`;
                elements.responseMeta.textContent = `Latency: ${latency} ms`;
                elements.responseBody.textContent = typeof body === 'string' ? body : JSON.stringify(body, null, 2);
                elements.responseHeaders.textContent = JSON.stringify(headers, null, 2);
            }

            elements.endpointSearch.addEventListener('input', (event) => renderEndpointList(event.target.value));
            elements.payloadEditor.addEventListener('input', () => {
                if (!state.selectedEndpoint) return;
                state.payloadCache[state.selectedEndpoint.id] = elements.payloadEditor.value;
                updatePayloadMeta();
            });
            elements.payloadEditor.addEventListener('keydown', (event) => {
                if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                    event.preventDefault();
                    sendRequest();
                }
            });
            elements.baseUrlInput.addEventListener('input', (event) => {
                state.baseUrl = event.target.value.trim() || defaultBaseUrl;
                persist();
            });
            elements.manualKeyInput.addEventListener('input', (event) => {
                state.privateKey = event.target.value.trim();
                updateKeyDisplay();
                persist();
            });
            elements.resetButton.addEventListener('click', () => {
                if (!state.selectedEndpoint?.template) return;
                state.payloadCache[state.selectedEndpoint.id] = JSON.stringify(state.selectedEndpoint.template(), null, 2);
                elements.payloadEditor.value = state.payloadCache[state.selectedEndpoint.id];
                updatePayloadMeta();
            });
            elements.sendButton.addEventListener('click', sendRequest);
            elements.authorizeButton.addEventListener('click', () => {
                const authEndpoint = apiEndpoints.find((ep) => ep.id === 'authorization_service');
                selectEndpoint(authEndpoint);
                sendRequest();
            });
            elements.clearKeyButton.addEventListener('click', () => {
                state.privateKey = '';
                state.keyNotes = 'Key cleared manually.';
                elements.keyNotes.value = state.keyNotes;
                updateKeyDisplay();
                persist();
            });
            elements.copyKeyButton.addEventListener('click', async () => {
                if (!state.privateKey) {
                    toast('No key to copy.', 'error');
                    return;
                }
                await navigator.clipboard.writeText(state.privateKey);
                toast('Private key copied.', 'success');
            });
            elements.autoTxnToggle.addEventListener('click', () => {
                state.autoTransaction = !state.autoTransaction;
                updateTransactionPreview();
                persist();
            });
            elements.transactionInput.addEventListener('input', (event) => {
                state.transactionId = event.target.value.trim();
                elements.transactionPreview.textContent = state.transactionId || 'pending…';
                persist();
            });

            renderEndpointList();
            selectEndpoint(apiEndpoints[0]);
        });
    </script>
</body>
</html>
