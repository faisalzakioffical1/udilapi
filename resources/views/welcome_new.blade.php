<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'UDIL') }} API Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                const saved = JSON.parse(localStorage.getItem('udil-api-console') || '{}');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = saved.theme || (prefersDark ? 'dark' : 'light');
                document.documentElement.classList.add(theme === 'dark' ? 'dark' : 'light');
            } catch (error) {
                console.warn('Theme bootstrap failed', error);
            }
        })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .scroll-container::-webkit-scrollbar { width: 6px; }
        .scroll-container::-webkit-scrollbar-thumb { background-color: rgba(148,163,184,0.4); border-radius: 9999px; }
        .code-block { font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', monospace; }
        .sidebar-scroll { max-height: calc(100vh - 14rem); }
        @media (min-width: 1024px) {
            .sidebar-scroll { max-height: calc(100vh - 8rem); }
        }
        .dark { color-scheme: dark; }
        .light { color-scheme: light; }
        .light body { background-color: #f8fafc; color: #0f172a; }
        .light .bg-slate-950 { background-color: #f8fafc !important; }
        .light .bg-slate-950\/90 { background-color: rgba(248,250,252,0.95) !important; }
        .light .bg-slate-950\/80,
        .light .bg-slate-950\/70,
        .light .bg-slate-950\/60,
        .light .bg-slate-900\/90,
        .light .bg-slate-900\/80,
        .light .bg-slate-900\/70 { background-color: rgba(255,255,255,0.92) !important; }
        .light .bg-slate-900\/60 { background-color: rgba(15,23,42,0.08) !important; }
        .light .border-slate-900\/70,
        .light .border-slate-800,
        .light .border-slate-700,
        .light .border-slate-700\/60,
        .light .border-slate-600 { border-color: rgba(148,163,184,0.45) !important; }
        .light .text-white,
        .light .text-slate-100,
        .light .text-slate-200 { color: #0f172a !important; }
        .light .text-slate-300 { color: #1e293b !important; }
        .light .text-slate-400 { color: #475569 !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100">
    <div class="min-h-screen flex flex-col">
        <header class="fixed inset-x-0 top-0 z-40 border-b border-slate-900/10 bg-gradient-to-r from-slate-50 via-white to-slate-50 text-slate-900 transition-colors dark:border-slate-900/70 dark:from-slate-950 dark:via-slate-900/90 dark:to-slate-950 dark:text-white backdrop-blur">
            <div class="max-w-4xl mx-auto px-4 py-2 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500 dark:text-slate-400">
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span>UDIL Console</span>
                    </div>
                    <span class="hidden sm:inline-flex rounded-full border border-slate-200/60 bg-white/80 px-2.5 py-1 text-[10px] font-semibold text-slate-600 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-200">v3.2</span>
                </div>
                <button id="headerToggle" type="button" class="flex items-center gap-2 rounded-full border border-slate-300/70 bg-white/80 px-4 py-2 text-[11px] font-semibold text-slate-900 shadow-sm transition hover:border-sky-400/60 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 dark:border-slate-700/70 dark:bg-slate-900/80 dark:text-slate-100" aria-expanded="false">
                    <svg id="headerToggleIcon" class="h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                    <span id="headerToggleText">Show header</span>
                </button>
            </div>
            <div id="headerPanel" class="max-w-4xl mx-auto px-4 pb-4 hidden">
                <div class="rounded-3xl border border-slate-200/70 bg-white/90 p-6 shadow-2xl shadow-slate-200/40 dark:border-slate-800/80 dark:bg-slate-900/80 dark:shadow-black/40 space-y-6">
                    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white dark:bg-slate-800">
                                <img src="https://www.mtilimited.com/cdn/shop/files/MTI_Logo_3D_1280x.png?v=1640719099" alt="MTI Limited" class="h-10 w-auto object-contain" loading="lazy">
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.30em] text-slate-500 dark:text-slate-400">Powered by</p>
                                <a href="https://www.mtilimited.com/" target="_blank" rel="noopener" class="text-lg font-semibold text-slate-900 hover:text-sky-500 dark:text-white">MTI Limited</a>
                            </div>
                        </div>
                        <div class="space-y-2 text-left md:text-right flex-1">
                            <div class="flex flex-wrap gap-2 justify-start md:justify-end">
                                <span class="rounded-full border border-sky-500/40 bg-sky-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-200">UDIL API Testing Tool</span>
                                <span class="rounded-full border border-amber-400/60 bg-amber-400/15 px-3 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-200">Multivendor</span>
                                <span class="rounded-full border border-emerald-400/70 bg-emerald-400/15 px-3 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-200">PK AMI certified</span>
                            </div>
                            <h1 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-white">Universal Data Integration Layer</h1>
                            {{-- <p class="text-sm text-slate-600 dark:text-slate-300">Craft payloads, replay watchdog flows, and validate meter deployments with confidence across Mode-I/Mode-II MDC clusters.</p> --}}
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/70 p-4 shadow-sm transition hover:border-emerald-400/60 hover:shadow-lg dark:border-slate-800/70 dark:bg-slate-900/70">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0-4-4" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8" /></svg>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-[0.25em] text-slate-500 dark:text-slate-400">Private key</p>
                                <p id="keyStatus" class="text-lg font-semibold text-slate-900 dark:text-white">Not connected</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Request via Authorization Service</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white/70 p-4 shadow-sm transition hover:border-sky-400/60 hover:shadow-lg dark:border-slate-800/70 dark:bg-slate-900/70">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-500/15 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v7l3 3" /><circle cx="12" cy="12" r="9" /></svg>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-[0.25em] text-slate-500 dark:text-slate-400">Next transaction</p>
                                <p id="transactionPreview" class="text-lg font-semibold text-slate-900 dark:text-white">pending…</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Auto refresh after payload</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-500 dark:text-slate-400">Controls</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button id="openSettingsModal" type="button" class="flex items-center gap-2 rounded-full border border-slate-300/70 bg-gradient-to-r from-slate-100 via-white to-slate-100 px-5 py-2 text-xs font-semibold text-slate-900 shadow-sm transition hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 dark:border-slate-700/70 dark:from-slate-900 dark:via-slate-900/80 dark:to-slate-950 dark:text-slate-100">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" /></svg>
                                <span>API settings</span>
                            </button>
                            <button id="themeToggle" type="button" class="flex items-center gap-3 rounded-full border border-slate-300/70 bg-white/80 px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm transition hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-500/40 dark:border-slate-700/70 dark:bg-slate-900/80 dark:text-slate-100">
                                <span class="flex items-center gap-2 text-[10px] uppercase tracking-[0.35em] text-slate-500 dark:text-slate-400">
                                    <svg id="themeToggleIcon" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95l-1.414 1.414" /></svg>
                                    Theme
                                </span>
                                <span id="themeToggleLabel" class="text-sm">Dark</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-1 flex-col lg:flex-row pt-32 lg:pt-44">
            <aside class="w-full border-b border-slate-900/70 bg-slate-950/70 flex flex-col lg:fixed lg:left-0 lg:top-36 lg:bottom-6 lg:w-80 xl:w-96 lg:border-b-0 lg:border-r lg:bg-slate-950/80 lg:shadow-2xl lg:rounded-r-3xl lg:overflow-hidden">
                <div class="p-4">
                    <label for="endpointSearch" class="text-xs uppercase text-slate-400">Endpoints</label>
                    <div class="mt-2 relative">
                        <input id="endpointSearch" type="text" placeholder="Find endpoint…" class="w-full rounded-xl border border-slate-800 bg-slate-900/80 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-400" />
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0-6.15a6.25 6.25 0 1 1-12.5 0 6.25 6.25 0 0 1 12.5 0Z" /></svg>
                        </div>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto scroll-container px-3 pb-6 sidebar-scroll" id="endpointListWrapper">
                    <ul id="endpointList" class="space-y-2"></ul>
                </div>
            </aside>

            <main class="flex-1 scroll-container lg:ml-80 xl:ml-96">
                <div class="max-w-4xl mx-auto w-full px-3 py-7 space-y-6">
                    <section class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 text-sm text-slate-400">
                        <p class="font-semibold text-slate-100">API base URL & key management moved</p>
                        <p class="mt-1">Use the <span class="text-sky-400 font-semibold">API settings</span> button in the header to edit base URLs, transaction settings, and headers.</p>
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
                            <div class="flex flex-wrap gap-2">
                                <button id="openGuideButton" class="rounded-xl border border-violet-500/50 bg-violet-500/5 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:border-violet-400 hover:bg-violet-500/15 dark:text-violet-200">API guide</button>
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
                                        <div id="responseStatusBar" class="border-b border-slate-800 px-4 py-3">
                                            <p id="responseStatus" class="text-lg font-semibold"></p>
                                            <p id="responseMeta" class="text-xs text-slate-500"></p>
                                        </div>
                                        <div class="flex-1 overflow-y-auto scroll-container">
                                            <div class="px-4 py-3">
                                                <p class="text-xs uppercase text-slate-500">Body</p>
                                                <pre id="responseBody" class="code-block mt-2 text-xs whitespace-pre-wrap overflow-auto"></pre>
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

    <div id="settingsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm transition-opacity opacity-0">
        <div id="settingsModalDialog" class="w-full max-w-4xl rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-900 px-5 py-4">
                <div>
                    <p class="text-xs uppercase text-slate-500">Console preferences</p>
                    <h2 class="text-xl font-semibold text-white">API base URL & header management</h2>
                </div>
                <button id="closeSettingsModal" type="button" class="rounded-full border border-slate-700 text-slate-300 hover:text-white px-3 py-1">✕</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-5 space-y-6">
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
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs uppercase text-slate-400">Default Global Device ID</label>
                            <input id="defaultGlobalDeviceId" type="text" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" value="GLOB123456789" />
                        </div>
                        <div>
                            <label class="text-xs uppercase text-slate-400">Default MSN / DSN</label>
                            <input id="defaultMsn" type="text" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" value="369812345678" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">These values feed the <code>global_device_id</code> arrays and the <code>device_identity</code> payloads. Update once and reuse across endpoints.</p>
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
                            <div class="mt-2 relative">
                                <input id="authPassword" type="password" value="Mti@786#" class="w-full rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-2 pr-10 text-sm text-slate-100 focus:border-sky-400 focus:ring-sky-400" />
                                <button id="togglePasswordVisibility" type="button" class="absolute inset-y-0 right-2 flex items-center rounded-lg px-2 text-slate-500 transition hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/40" aria-label="Show password" aria-pressed="false">
                                    <svg id="passwordEyeOpen" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg id="passwordEyeClosed" class="h-4 w-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.477 10.477a3 3 0 0 0 4.243 4.243" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 6.633C4.467 8.108 3 10 3 12c0 0 3.75 6.75 9.75 6.75 1.43 0 2.76-.33 3.96-.87" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.12 17.12C19.37 15.56 20.75 13.5 20.75 12c0 0-3.75-6.75-9.75-6.75-1.05 0-2.06.18-3 .51" />
                                    </svg>
                                </button>
                            </div>
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
            </div>
        </div>
    </div>

    <div id="guideModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur transition-opacity opacity-0">
        <div id="guideModalDialog" class="w-full max-w-5xl rounded-3xl border border-slate-800 bg-slate-950 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
            <div class="flex flex-col gap-2 border-b border-slate-900 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">UDIL field guide</p>
                    <h2 id="guideTitle" class="text-2xl font-semibold text-white">API guide</h2>
                    <p id="guideSubtitle" class="text-sm text-slate-400">Select an endpoint to view validation details.</p>
                </div>
                <button id="closeGuideModal" type="button" class="self-end rounded-full border border-slate-700 px-3 py-1 text-slate-300 hover:text-white">✕</button>
            </div>
            <div class="max-h-[75vh] overflow-y-auto px-6 py-6 space-y-6">
                <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <h3 class="text-sm font-semibold text-slate-200">Validation checklist</h3>
                    <ul id="guideValidations" class="mt-3 space-y-3 text-sm text-slate-300"></ul>
                </section>
                <section class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <h3 class="text-sm font-semibold text-slate-200">Release reference</h3>
                        <p class="mt-2 text-xs uppercase tracking-[0.35em] text-slate-500">Document</p>
                        <p id="guidePdfDoc" class="text-sm font-semibold text-slate-100">UDIL_Release_3.2.0-20221104.pdf</p>
                        <p class="mt-3 text-xs uppercase tracking-[0.35em] text-slate-500">Pages</p>
                        <p id="guidePdfPages" class="text-sm text-slate-200">-</p>
                        <a id="guidePdfLink" href="{{ asset('UDIL_Release_3.2.0-20221104.pdf') }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-sky-500/40 px-3 py-2 text-xs font-semibold text-sky-300 hover:border-sky-400 hover:text-sky-200">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                            <span>Open PDF viewer</span>
                        </a>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-200">AMI tables</h3>
                            <span id="guideTableCount" class="text-xs uppercase tracking-[0.35em] text-slate-500">-</span>
                        </div>
                        <ul id="guideTables" class="mt-3 space-y-2 text-sm text-slate-300"></ul>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-200">MySQL verification query</h3>
                        <button id="copyGuideSql" type="button" class="text-xs font-semibold text-sky-400 hover:text-sky-300">Copy</button>
                    </div>
                    <pre id="guideSqlQuery" class="code-block mt-3 whitespace-pre-wrap text-xs text-slate-200"></pre>
                </section>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-6 right-6 hidden rounded-xl bg-slate-900/90 px-4 py-3 text-sm font-semibold text-white shadow-xl"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const defaultBaseUrl = @json(url('/api'));
            const storageKey = 'udil-api-console';
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const defaultTheme = saved.theme || (prefersDark ? 'dark' : 'light');
            const deviceDefaultFallbacks = {
                global_device_id: 'GLOB123456789',
                msn: '369812345678',
            };
            let deviceDefaults = {
                global_device_id: saved.deviceDefaults?.global_device_id || deviceDefaultFallbacks.global_device_id,
                msn: saved.deviceDefaults?.msn || deviceDefaultFallbacks.msn,
            };

            const formatDateTime = (offsetMinutes = 0) => {
                const d = new Date(Date.now() + offsetMinutes * 60000);
                return d.toISOString().slice(0, 19).replace('T', ' ');
            };

            const sampleDevice = () => ({ global_device_id: deviceDefaults.global_device_id, msn: deviceDefaults.msn });
            const sampleDevices = () => [sampleDevice()];
            const sampleIdentity = () => ([{ global_device_id: deviceDefaults.global_device_id, dsn: deviceDefaults.msn }]);
            const singleDeviceToken = () => JSON.stringify(deviceDefaults.global_device_id);
            const sampleSlabs = () => ([
                { action_time: '06:00:00', relay_operate: 1 },
                { action_time: '18:00:00', relay_operate: 0 },
            ]);
            const sampleDayProfile = () => ([
                { name: 'DAY', tariff_slabs: ['00:00:00', '18:00:00'] },
                { name: 'PEAK', tariff_slabs: ['06:00:00', '22:00:00'] },
            ]);
            const sampleWeekProfile = () => ([
                { name: 'WEEKDAY', weekly_day_profile: ['DAY', 'DAY', 'DAY', 'DAY', 'DAY', 'PEAK', 'PEAK'] },
                { name: 'WEEKEND', weekly_day_profile: ['PEAK', 'PEAK', 'PEAK', 'PEAK', 'PEAK', 'PEAK', 'PEAK'] },
            ]);
            const sampleSeasonProfile = () => ([
                { name: 'SUMMER', start_date: '01-04', week_profile_name: 'WEEKDAY' },
                { name: 'WINTER', start_date: '01-10', week_profile_name: 'WEEKEND' },
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
                        communication_mode: '1',
                        communication_type: '1',
                        communication_interval: '15',
                        bidirectional_device: '0',
                        device_type: '1',
                        mdi_reset_date: '12',
                        mdi_reset_time: '00:30:00',
                        sim_number: '03123456789',
                        sim_id: '891112223334455',
                        phase: '3',
                        meter_type: '2',
                        initial_communication_time: '01:00:00',
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
                        holiday_profile: [{ name: 'Public Holiday', date: '14-08', day_profile_name: 'PEAK' }],
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
                        request_datetime: formatDateTime(),
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
                    notes: 'Encode global_device_id as a JSON string (wrap the ID in quotes) per UDIL contract.',
                    template: () => ({
                        type: 'AUXR',
                        global_device_id: singleDeviceToken(),
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
                    notes: 'Provide a single device by JSON-encoding the ID (e.g., "\"GLOB123\"").',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        start_datetime: formatDateTime(-60),
                        end_datetime: formatDateTime(),
                        type: 'INST',
                        global_device_id: singleDeviceToken(),
                    }),
                },
                {
                    id: 'on_demand_parameter_read_secondary',
                    label: 'On-demand Parameter (Alt)',
                    method: 'POST',
                    path: '/on_demand_parameter_read',
                    description: 'Shortcut entry for other parameter types (e.g., TIOU, LSCH).',
                    notes: 'Encode global_device_id as a JSON string (wrap the ID in quotes) per UDIL contract.',
                    template: () => ({
                        type: 'TIOU',
                        global_device_id: singleDeviceToken(),
                    }),
                },
                {
                    id: 'on_demand_data_read_secondary',
                    label: 'On-demand Data (BILL)',
                    method: 'POST',
                    path: '/on_demand_data_read',
                    description: 'Template focusing on billing window reads.',
                    notes: 'Provide a single device by JSON-encoding the ID (e.g., "\"GLOB123\"").',
                    template: () => ({
                        request_datetime: formatDateTime(),
                        start_datetime: formatDateTime(-2880),
                        end_datetime: formatDateTime(-1440),
                        type: 'BILL',
                        global_device_id: singleDeviceToken(),
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

            const guideDocument = 'UDIL_Release_3.2.0-20221104.pdf';
            const guideDocumentUrl = @json(asset('UDIL_Release_3.2.0-20221104.pdf'));
            const apiGuides = {
                authorization_service: {
                    summary: 'Issues a UDIL private key that stays valid for 30 minutes; no JSON body is required.',
                    pdfPages: 'p. 37',
                    validations: [
                        { title: 'Header contract', detail: 'username, password, and code headers are mandatory—missing any of them returns HTTP 400 before DB access.' },
                        { title: 'Exact credential match', detail: 'Only the mti / Mti@786# / 36 tuple is accepted; other values trigger the 401 branch in AuthorizationController.' },
                        { title: 'TTL guarantee', detail: 'Controller persists key_time = now()+30m in udil_auth, honoring the 30-minute lifetime from UDIL §3.2.1.' },
                    ],
                    tables: [
                        { name: 'udil_auth', purpose: 'Stores issued private keys with their expiry timestamp.' },
                    ],
                    sql: `SELECT key, key_time
FROM udil_auth
WHERE key = :privateKey;`,
                },
                device_creation: {
                    summary: 'Creates or refreshes meter rows, communication cadence, and visuals per UDIL §3.3.6.',
                    pdfPages: 'pp. 42-44',
                    validations: [
                        { title: 'device_identity payload', detail: 'Must be JSON array; each entry requires dsn and global_device_id or the controller aborts with "Either DSN or Global Device ID index is missing".' },
                        { title: 'Comm interval vs type', detail: 'Mode-II (keep-alive) forces communication_interval = 0, while Mode-I must be > 0—validated before any DB writes.' },
                        { title: 'MSN prefix rules', detail: '3697 units must stay single phase, 3698 are three‑phase whole current, and 3699 only allow CTO/CTPT meter_type; violations return indv_status = 0.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Insert WDvCR entries for each device creation transaction.' },
                        { name: 'meter', purpose: 'Holds canonical communication, wakeup, and meter identity fields.' },
                        { name: 'meter_visuals', purpose: 'Stores last-programmed metadata snapshot surfaced to the UI.' },
                    ],
                    sql: `SELECT global_device_id, msn, communication_type, communication_interval, meter_type, phase
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                update_wake_up_sim_number: {
                    summary: 'Pushes three wakeup MSISDNs to each meter and flips the wakeup profile flag.',
                    pdfPages: 'p. 47',
                    validations: [
                        { title: 'Three-number requirement', detail: 'wakeup_number_1/2/3 must be present; controller trims symbols and rejects if any is missing.' },
                        { title: '11-digit check', detail: 'normalizeSim + is_valid_sim_number enforce 11 digits (03XXXXXXXXX style) before persisting.' },
                        { title: 'Device existence', detail: 'Each global_device_id entry must include a non-zero msn; otherwise the response carries indv_status=0 with meter_not_exists.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Tracks WSIM command progress.' },
                        { name: 'meter', purpose: 'Stores wakeup_no1/2/3, set_wakeup_profile_id, number_profile_group_id.' },
                        { name: 'meter_visuals', purpose: 'Surfaced fields for wsim_wakeup_number_* and timestamps.' },
                    ],
                    sql: `SELECT wakeup_no1, wakeup_no2, wakeup_no3, number_profile_group_id
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                time_synchronization: {
                    summary: 'Aligns meter RTC with MDC time by writing super_immediate_cs and base_time_cs.',
                    pdfPages: 'p. 38',
                    validations: [
                        { title: 'request_datetime hygiene', detail: 'Field is mandatory and must pass is_date_valid(Y-m-d H:i:s) before processing.' },
                        { title: 'Device list required', detail: 'global_device_id array must be non-empty; otherwise HTTP 400 with "Global Device ID is required".' },
                        { title: 'Command payload', detail: 'Each accepted device receives max_cs_difference=999999999 and base_time_cs = now + 15 minutes, matching §3.3.2.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Stores WDVTM state transitions for each meter.' },
                        { name: 'meter', purpose: 'Persists base_time_cs/super_immediate_cs used by firmware to sync.' },
                    ],
                    sql: `SELECT max_cs_difference, super_immediate_cs, base_time_cs
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                aux_relay_operations: {
                    summary: 'Disconnects/reconnects relays and coordinates with load shedding state as in §3.3.1.',
                    pdfPages: 'p. 38',
                    validations: [
                        { title: 'Device envelope', detail: 'global_device_id array with msn is required; empty payload short-circuits with HTTP 400.' },
                        { title: 'request_datetime format', detail: 'Must be supplied in Y-m-d H:i:s; invalid strings return "request_datetime must be in Y-m-d H:i:s format".' },
                        { title: 'relay_operate bounds', detail: 'relay_operate has to be 0 or 1; controller uses filterInteger(0,1) to guard invalid values.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WAUXR rows per device for tracking.' },
                        { name: 'meter', purpose: 'apply_new_contactor_state/new_contactor_state flags plus keepalive scheduling changes.' },
                        { name: 'meter_visuals', purpose: 'auxr_status/auxr_datetime displayed back to operators when enabled.' },
                        { name: 'load_shedding_detail', purpose: 'Consulted to determine current slab before toggling (special treatment type 20).' },
                    ],
                    sql: `SELECT apply_new_contactor_state, new_contactor_state, load_shedding_schedule_id
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                update_device_metadata: {
                    summary: 'Switches communication mode/type, interval, phase, and meter profile per §3.3.12.',
                    pdfPages: 'pp. 48-49',
                    validations: [
                        { title: 'Required fields', detail: 'communication_mode/type, bidirectional_device, phase, meter_type, initial_communication_time, communication_interval, request_datetime all must be present or the service replies with "Mandatory fields are missing".' },
                        { title: 'Interval logic', detail: 'Keep-alive devices (communication_type=2) force interval=0, whereas non keep-alive must use interval > 0; enforced before DB work.' },
                        { title: 'Time validation', detail: 'initial_communication_time uses is_time_valid(HH:mm:ss) and request_datetime uses is_date_valid to match the UDIL format.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WDMDT entries for each metadata push.' },
                        { name: 'meter', purpose: 'Holds class/type scheduling, dw_* ids, key material, and keepalive intervals.' },
                    ],
                    sql: `SELECT class, type, communication_mode, communication_type, communication_interval, energy_param_id
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                update_ip_port: {
                    summary: 'Uploads primary/secondary MDC endpoints according to §3.3.7 and logs the change.',
                    pdfPages: 'p. 44',
                    validations: [
                        { title: 'IP + port presence', detail: 'primary/secondary IP and port fields are each required; missing ones short-circuit the controller with an explanatory error message.' },
                        { title: 'IPv4 validation', detail: 'filter_var(..., FILTER_VALIDATE_IP) ensures syntactically valid IPv4 values before writing to ip_profile.' },
                        { title: 'request_datetime required', detail: 'Must exist and validate via is_date_valid; spec requires HH:mm:ss precision.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WIPPO entries per meter for tracking.' },
                        { name: 'ip_profile', purpose: 'Stores the freshly inserted IP pair and ports.' },
                        { name: 'meter', purpose: 'References set_ip_profiles column so the wrapper can push settings.' },
                        { name: 'udil_log', purpose: 'Persists JSON summary in update_ip_port when config flag is on.' },
                        { name: 'meter_visuals', purpose: 'Displays the last programmed IP/port pair to UI consumers.' },
                    ],
                    sql: `SELECT m.global_device_id, p.ip_1, p.ip_2, p.w_tcp_port_1, p.w_tcp_port_2
FROM meter m
JOIN ip_profile p ON p.ip_profile_id = m.set_ip_profiles
WHERE m.global_device_id = :deviceId;`,
                },
                sanctioned_load_control: {
                    summary: 'Sets sanctioned load limit, retry logic, and creates a contactor profile as described in §3.3.3.',
                    pdfPages: 'p. 39',
                    validations: [
                        { title: 'Mandatory numeric fields', detail: 'load_limit, maximum_retries, retry_interval, threshold_duration, retry_clear_interval must all exist and be numeric or the controller replies with "Only Numeric Fields are allowed".' },
                        { title: 'request_datetime enforcement', detail: 'request_datetime must be supplied in Y-m-d H:i:s; failures return HTTP 400.' },
                        { title: 'Device validation', detail: 'Empty global_device_id arrays or msn=0 entries are rejected with meter_not_exists per UDIL requirement to return remarks.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WSANC traces for each device.' },
                        { name: 'contactor_params', purpose: 'Holds the generated retry_count, retry_auto_interval_in_sec, and load limits.' },
                        { name: 'meter', purpose: 'References the new contactor_param_id and write_contactor_param flag.' },
                        { name: 'udil_log', purpose: 'Optional JSON mirror stored in sanc_load_control column.' },
                        { name: 'meter_visuals', purpose: 'Displays last sanctioned load settings when configured.' },
                    ],
                    sql: `SELECT m.global_device_id, cp.retry_count, cp.retry_auto_interval_in_sec AS retry_interval_sec, cp.limit_over_load_total_kW_t1
FROM meter m
JOIN contactor_params cp ON cp.contactor_param_id = m.contactor_param_id
WHERE m.global_device_id = :deviceId
ORDER BY cp.contactor_param_id DESC
LIMIT 1;`,
                },
                load_shedding_scheduling: {
                    summary: 'Programs activation/expiry window plus relay slabs exactly as §3.3.4 specifies.',
                    pdfPages: 'p. 40',
                    validations: [
                        { title: 'start/end ordering', detail: 'Both datetimes are required, validated via is_date_valid, and end_datetime must be greater than start_datetime.' },
                        { title: 'Slab schema', detail: 'load_shedding_slabs must JSON-decode into items containing action_time and relay_operate fields (validate_load_shedding_slabs enforces this).' },
                        { title: 'request_datetime presence', detail: 'Controller insists on request_datetime (Y-m-d H:i:s) before queueing the write job.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WLSCH tracking rows per device.' },
                        { name: 'load_shedding_schedule', purpose: 'Stores header row with activation/expiry dates.' },
                        { name: 'load_shedding_detail', purpose: 'Keeps each slab’s action_time/relay_operate combination.' },
                        { name: 'meter', purpose: 'References load_shedding_schedule_id and write_load_shedding_schedule.' },
                        { name: 'udil_log', purpose: 'Optionally mirrors the request payload into lsch column.' },
                        { name: 'meter_visuals', purpose: 'Shows start/end/slab JSON in UI when enabled.' },
                    ],
                    sql: `SELECT s.schedule_id, s.activation_date, s.expiry_date, d.action_time, d.relay_operate
FROM load_shedding_schedule s
JOIN load_shedding_detail d ON d.schedule_id = s.schedule_id
WHERE s.schedule_id = (SELECT load_shedding_schedule_id FROM meter WHERE global_device_id = :deviceId)
ORDER BY d.action_time;`,
                },
                update_time_of_use: {
                    summary: 'Builds a DLMS activity calendar (day/week/season/holiday) and links it to the meter (§3.3.5).',
                    pdfPages: 'p. 41',
                    validations: [
                        { title: 'request_datetime + activation', detail: 'Both timestamps are mandatory and validated to UDIL’s Y-m-d H:i:s format.' },
                        { title: 'Profile validation helper', detail: 'validate_tiou_update() enforces correct JSON structure for day_profile, week_profile, season_profile, and optional holiday_profile.' },
                        { title: 'Device list required', detail: 'All requests must include at least one global_device_id; empty lists return HTTP 400.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WTIOU tracking per device.' },
                        { name: 'param_activity_calendar', purpose: 'Holds activation_date and ties the child profiles together.' },
                        { name: 'param_day_profile', purpose: 'One row per named day profile.' },
                        { name: 'param_day_profile_slots', purpose: 'Stores tariff transition times for each day profile.' },
                        { name: 'param_week_profile', purpose: 'Maps seven days to day profiles.' },
                        { name: 'param_season_profile', purpose: 'Defines season start dates pointing to week profiles.' },
                        { name: 'param_special_day_profile', purpose: 'Captures optional holiday overrides.' },
                        { name: 'meter', purpose: 'References activity_calendar_id for downstream writers.' },
                        { name: 'meter_visuals', purpose: 'Persists tiou_* JSON so operators can read it back.' },
                    ],
                    sql: `SELECT m.global_device_id, m.activity_calendar_id, ac.activation_date
FROM meter m
JOIN param_activity_calendar ac ON ac.pk_id = m.activity_calendar_id
WHERE m.global_device_id = :deviceId;`,
                },
                update_meter_status: {
                    summary: 'Toggles meter activation bit remotely, matching §3.3.11.',
                    pdfPages: 'p. 47-48',
                    validations: [
                        { title: 'Status flag bounds', detail: 'meter_activation_status must exist and pass filter_integer(0,1); invalid values produce "Meter Activation Status is not valid".' },
                        { title: 'request_datetime guard', detail: 'Field must exist and validate via is_date_valid before update_meter() is called.' },
                        { title: 'Device validation', detail: 'global_device_id array cannot be empty and each entry must carry a non-zero msn.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WMTST entries allow  monitoring.' },
                        { name: 'meter', purpose: 'update_meter() flips the status column (0/1).' },
                        { name: 'meter_visuals', purpose: 'mtst_meter_activation_status + mtst_datetime stored for on-demand reads.' },
                    ],
                    sql: `SELECT status AS meter_activation_status
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                meter_data_sampling: {
                    summary: 'Writes LP/instantaneous sampling intervals per §3.3.8.',
                    pdfPages: 'p. 45',
                    validations: [
                        { title: 'Mandatory quartet', detail: 'activation_datetime, data_type, sampling_interval, and sampling_initial_time must exist or the controller returns "Mandatory Input Fields are missing".' },
                        { title: 'Type whitelist', detail: 'data_type is uppercased and must be INST, BILL, or LPRO; anything else yields HTTP 400.' },
                        { title: 'Interval bounds', detail: 'sampling_interval must satisfy filterInteger(1,1440); out-of-range values trigger the 400 with interval guidance.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WMDSM entries per device.' },
                        { name: 'meter', purpose: 'Persist lp*, lp2*, lp3* interval requests used by the wrapper.' },
                    ],
                    sql: `SELECT lp_write_interval, lp_interval_activation_datetime, lp2_write_interval, lp3_write_interval
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                activate_meter_optical_port: {
                    summary: 'Schedules optical_port_on/off datetimes and enables access per §3.3.9.',
                    pdfPages: 'p. 46',
                    validations: [
                        { title: 'Window fields required', detail: 'optical_port_on_datetime and optical_port_off_datetime must both be present or the controller stops with a dedicated error message.' },
                        { title: 'Device list', detail: 'global_device_id array is mandatory; meters with msn=0 yield meter_not_exists remarks.' },
                        { title: 'Transaction tracking', detail: 'Each accepted device inserts a WOPPO transaction_status row so cancellation/reporting works.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Captures WOPPO lifecycle per device.' },
                        { name: 'meter', purpose: 'Stores update_optical_port_access plus the on/off datetimes.' },
                        { name: 'udil_log', purpose: 'Optional JSON snapshot retained when update_udil_log_for_write_services=true.' },
                        { name: 'meter_visuals', purpose: 'Holds opco/oppo timestamps for UI display.' },
                    ],
                    sql: `SELECT update_optical_port_access, optical_port_start_time, optical_port_end_time
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                apms_tripping_events: {
                    summary: 'Writes APMS threshold bundles (OVFC/UVFC/OCFC/OLFC/VUFC/PFFC/CUFC/HAPF) exactly as §3.3.14 describes.',
                    pdfPages: 'pp. 50-51',
                    validations: [
                        { title: 'Type whitelist', detail: 'type is lowercased and must be in {ovfc, uvfc, ocfc, olfc, vufc, pffc, cufc, hapf}; others produce an explicit 400.' },
                        { title: 'Log time bounds', detail: 'critical_event_log_time and tripping_event_log_time must pass filterInteger(0, 86399) before conversion to HH:MM:SS.' },
                        { title: 'Enable flag', detail: 'enable_tripping must be 0 or 1; invalid values raise "Field enable_tripping can only have values 0-1".' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Each device inserts type-specific Wxxxx entries (e.g., WOVFC).' },
                        { name: 'param_apms_tripping_events', purpose: 'Stores the critical/tripping thresholds and durations with an auto id.' },
                        { name: 'meter', purpose: 'Holds write_<type> foreign key pointing to the inserted APMS row.' },
                    ],
                    sql: `SELECT write_ovfc, write_uvfc, write_ocfc, write_olfc, write_vufc, write_pffc, write_cufc, write_hapf
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                update_mdi_reset_date: {
                    summary: 'Configures the monthly MDI reset day/time (UDIL §3.3.13).',
                    pdfPages: 'pp. 49-50',
                    validations: [
                        { title: 'Date + time guards', detail: 'mdi_reset_date must satisfy filterInteger(1,28) and mdi_reset_time must pass is_time_valid before any DB updates happen.' },
                        { title: 'Device envelope', detail: 'global_device_id array is required; entries with msn=0 surface meter_not_exists remarks.' },
                        { title: 'Transaction linkage', detail: 'Every accepted meter inserts WMDI rows into transaction_status, aligning with §3.4.3.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'WMDI command tracking.' },
                        { name: 'meter', purpose: 'Persists mdi_reset_date/time and write_mdi_reset_date.' },
                        { name: 'meter_visuals', purpose: 'Optional mirror for mdi_reset_date/time (00:00:00 per current implementation).'},
                    ],
                    sql: `SELECT mdi_reset_date, mdi_reset_time, write_mdi_reset_date
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                on_demand_parameter_read: {
                    summary: 'On-demand pull for AUXR/DVTM/SANC/LSCH/TIOU/IPPO/MDSM/etc. as cataloged in §3.4.2.',
                    pdfPages: 'pp. 53-54',
                    validations: [
                        { title: 'Type whitelist', detail: 'type is uppercased and must be one of AUXR, DVTM, SANC, LSCH, TIOU, IPPO, MDSM, OPPO, WSIM, MSIM, MTST, DMDT, MDI, OVFC, UVFC, OCFC, OLFC, VUFC, PFFC, CUFC, HAPF.' },
                        { title: 'Device identity', detail: 'global_device_id array with msn values is required; missing or zero MSN returns meter_not_exists remarks.' },
                        { title: 'Transaction header', detail: 'transactionid header is mandatory; missing header yields HTTP 400 before scheduling reads.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Indirectly populated via setOnDemandReadTransactionStatus for progress reporting.' },
                        { name: 'meter_visuals', purpose: 'Primary source for AUXR/SANC/TIOU/IPPO/etc. snapshots.' },
                        { name: 'contactor_params', purpose: 'Consulted for SANC retry clear interval enrichment.' },
                        { name: 'meter', purpose: 'Read for LP intervals and metadata when building compound responses (e.g., MDSM).' },
                    ],
                    sql: `SELECT auxr_status, auxr_datetime, sanc_load_limit, tiou_day_profile, ippo_primary_ip_address
FROM meter_visuals
WHERE global_device_id = :deviceId;`,
                },
                parameterization_cancellation: {
                    summary: 'Resets programmed SANC/LSCH/TIOU parameters to factory defaults as per §3.3.15.',
                    pdfPages: 'pp. 51-52',
                    validations: [
                        { title: 'Type gating', detail: 'type must be one of the UDIL list, but the current implementation only processes SANC, LSCH, or TIOU—others return "is NOT Implemented".' },
                        { title: 'Transaction header', detail: 'Requires transactionid in the header; missing value yields HTTP 400 immediately.' },
                        { title: 'Cancelable state', detail: 'Meters with msn=0 or without matching queued jobs get indv_status=0 with explanatory remarks.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Used to determine status_level and to flag request_cancelled when needed.' },
                        { name: 'meter', purpose: 'Resets load_shedding_schedule_id, write_load_shedding_schedule, activity_calendar_id, or contactor params.' },
                        { name: 'load_shedding_schedule', purpose: 'Referenced when clearing LSCH to default id 105.' },
                        { name: 'param_activity_calendar', purpose: 'Fallback calendar (udil_parameter_cancel_default) for TIOU resets.' },
                        { name: 'contactor_params', purpose: 'New default profile inserted when cancelling SANC.' },
                    ],
                    sql: `SELECT load_shedding_schedule_id, write_load_shedding_schedule, activity_calendar_id, write_contactor_param
FROM meter
WHERE global_device_id = :deviceId;`,
                },
                transaction_status: {
                    summary: 'Returns  levels (0–5) for a given transaction, aligning with §3.4.3.',
                    pdfPages: 'p. 55',
                    validations: [
                        { title: 'transactionid header', detail: 'Services must pass transactionid via headers; empty value returns HTTP 400.' },
                        { title: 'Headers-only call', detail: 'No JSON body is needed—controller simply proxies get_modified_transaction_status.' },
                        { title: 'Status levels', detail: 'Consumers should expect status_level 0–5 plus indv_status/request_cancelled exactly as documented in the spec table.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Authoritative store for stage timestamps, indv_status, and cancel flags.' },
                    ],
                    sql: `SELECT type, global_device_id, status_level, indv_status, request_cancelled
FROM transaction_status
WHERE transaction_id = :transactionId
ORDER BY global_device_id;`,
                },
                on_demand_data_read: {
                    summary: 'Performs synchronous INST/BILL/MBIL/LPRO/EVNT pulls via MDC as outlined in §3.4.1.',
                    pdfPages: 'pp. 52-53',
                    validations: [
                        { title: 'Window validation', detail: 'start_datetime and end_datetime are mandatory, must pass is_date_valid, and start must be < end.' },
                        { title: 'Type whitelist', detail: 'type is uppercased and must match INST, BILL, MBIL, EVNT, or LPRO; invalid types return 400.' },
                        { title: 'transactionid header', detail: 'Required—missing header returns HTTP 400 with "Transaction ID is required".' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Underlying wakeup helper records progress per device.' },
                        { name: 'instantaneous_data', purpose: 'Source table when type=INST.' },
                        { name: 'billing_data', purpose: 'Used for BILL requests.' },
                        { name: 'monthly_billing_data', purpose: 'Used for MBIL requests.' },
                        { name: 'load_profile_data', purpose: 'Used for LPRO requests.' },
                        { name: 'events', purpose: 'Used for EVNT requests.' },
                    ],
                    sql: `SELECT db_datetime, kwh_delivered
FROM load_profile_data
WHERE global_device_id = :deviceId
  AND db_datetime BETWEEN :start AND :end
ORDER BY db_datetime DESC
LIMIT 5;`,
                },
                on_demand_parameter_read_secondary: {
                    summary: 'Same API as on_demand_parameter_read but exposed with alternate defaults (e.g., pre-filling TIOU).',
                    pdfPages: 'pp. 53-54',
                    validations: [
                        { title: 'Type + device parity', detail: 'All rules from the primary parameter read apply—type must be in the UDIL list and device ids require msn values.' },
                        { title: 'transactionid header', detail: 'Shared controller path, so the header is still mandatory for synchronous polling.' },
                        { title: 'Concurrency awareness', detail: 'Wrapper enforces the same 5.5 minute timeout; repeated requests before completion will reuse the same transaction row.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Tracks wakeup job progress even when using the secondary preset.' },
                        { name: 'meter_visuals', purpose: 'Primary storage for returned configuration snapshots.' },
                    ],
                    sql: `SELECT tiou_day_profile, tiou_week_profile, sanc_load_limit, auxr_status
FROM meter_visuals
WHERE global_device_id = :deviceId;`,
                },
                on_demand_data_read_secondary: {
                    summary: 'Preset that targets BILL reads (D-2 window) but uses the same controller as the primary handler.',
                    pdfPages: 'pp. 52-53',
                    validations: [
                        { title: '24-hour slice', detail: 'UI template pre-fills a one-day range; backend still enforces start < end and valid timestamps.' },
                        { title: 'Type enforcement', detail: 'type remains limited to INST/BILL/MBIL/EVNT/LPRO; secondary template simply pins BILL.' },
                        { title: 'transactionid header', detail: 'Same requirement as the primary variant—needed for readOndemandStatusLevel.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Synchronous status rows for the BILL job.' },
                        { name: 'billing_data', purpose: 'Holds the actual billing snapshot returned to the caller.' },
                    ],
                    sql: `SELECT doc_datetime, kwh_import, kwh_export
FROM billing_data
WHERE global_device_id = :deviceId
  AND doc_datetime BETWEEN :start AND :end;`,
                },
                transaction_cancel: {
                    summary: 'Cancels queued write transactions when status_level is still < 3 as mandated in §3.4.4.',
                    pdfPages: 'p. 56',
                    validations: [
                        { title: 'Header + payload', detail: 'transactionid header and a non-empty global_device_id array are both required.' },
                        { title: 'Cancelable states only', detail: 'Controller only toggles request_cancelled for rows whose status_level is null or < 3; others return "Command sent to Meter so Transaction cannot be cancelled".' },
                        { title: 'Per-type clean-up', detail: 'Each recognized type resets its pending flags (e.g., set_ip_profiles=0 for WIPPO), so callers should expect idempotent clean-up.' },
                    ],
                    tables: [
                        { name: 'transaction_status', purpose: 'Holds status_level, request_cancelled, and request_cancel_reason for every device/type pair.' },
                        { name: 'meter', purpose: 'Flags such as apply_new_contactor_state, set_ip_profiles, activity_calendar_id, etc., are toggled back when cancellation succeeds.' },
                    ],
                    sql: `SELECT type, status_level, request_cancelled, request_cancel_reason
FROM transaction_status
WHERE transaction_id = :transactionId
  AND global_device_id = :deviceId;`,
                },
            };

            apiEndpoints.forEach((endpoint) => {
                endpoint.guide = apiGuides[endpoint.id] || null;
            });

            const elements = {
                headerToggle: document.getElementById('headerToggle'),
                headerToggleIcon: document.getElementById('headerToggleIcon'),
                headerToggleText: document.getElementById('headerToggleText'),
                headerPanel: document.getElementById('headerPanel'),
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
                defaultGlobalIdInput: document.getElementById('defaultGlobalDeviceId'),
                defaultMsnInput: document.getElementById('defaultMsn'),
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
                responseStatusBar: document.getElementById('responseStatusBar'),
                toast: document.getElementById('toast'),
                authUsername: document.getElementById('authUsername'),
                authPassword: document.getElementById('authPassword'),
                passwordToggle: document.getElementById('togglePasswordVisibility'),
                passwordEyeOpen: document.getElementById('passwordEyeOpen'),
                passwordEyeClosed: document.getElementById('passwordEyeClosed'),
                authCode: document.getElementById('authCode'),
                themeToggle: document.getElementById('themeToggle'),
                themeToggleLabel: document.getElementById('themeToggleLabel'),
                themeToggleIcon: document.getElementById('themeToggleIcon'),
                openSettingsButton: document.getElementById('openSettingsModal'),
                closeSettingsButton: document.getElementById('closeSettingsModal'),
                settingsModal: document.getElementById('settingsModal'),
                openGuideButton: document.getElementById('openGuideButton'),
                guideModal: document.getElementById('guideModal'),
                closeGuideButton: document.getElementById('closeGuideModal'),
                guideTitle: document.getElementById('guideTitle'),
                guideSubtitle: document.getElementById('guideSubtitle'),
                guideValidations: document.getElementById('guideValidations'),
                guidePdfDoc: document.getElementById('guidePdfDoc'),
                guidePdfPages: document.getElementById('guidePdfPages'),
                guidePdfLink: document.getElementById('guidePdfLink'),
                guideTables: document.getElementById('guideTables'),
                guideTableCount: document.getElementById('guideTableCount'),
                guideSqlQuery: document.getElementById('guideSqlQuery'),
                copyGuideSql: document.getElementById('copyGuideSql'),
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
                theme: defaultTheme,
                deviceDefaults: { ...deviceDefaults },
                headerExpanded: saved.headerExpanded !== undefined ? !!saved.headerExpanded : false,
                jsonValid: true,
                validationError: '',
                loading: false,
            };

            function persist() {
                localStorage.setItem(storageKey, JSON.stringify({
                    privateKey: state.privateKey,
                    keyNotes: state.keyNotes,
                    baseUrl: state.baseUrl,
                    autoTransaction: state.autoTransaction,
                    transactionId: state.transactionId,
                    theme: state.theme,
                    deviceDefaults: state.deviceDefaults,
                    headerExpanded: state.headerExpanded,
                }));
            }

            function applyTheme(mode, options = {}) {
                state.theme = mode === 'dark' ? 'dark' : 'light';
                document.documentElement.classList.toggle('dark', state.theme === 'dark');
                document.documentElement.classList.toggle('light', state.theme === 'light');
                document.documentElement.style.colorScheme = state.theme;
                if (elements.themeToggleLabel) {
                    elements.themeToggleLabel.textContent = state.theme === 'dark' ? 'Dark' : 'Light';
                }
                if (elements.themeToggleIcon) {
                    elements.themeToggleIcon.innerHTML = state.theme === 'dark'
                        ? '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />'
                        : '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95l-1.414 1.414" />';
                }
                if (state.selectedEndpoint) {
                    updateEndpointMeta();
                }
                if (state.response) {
                    renderResponse();
                }
                if (!options.silent) {
                    persist();
                }
            }

            applyTheme(state.theme, { silent: true });
            deviceDefaults = { ...state.deviceDefaults };
            elements.baseUrlInput.value = state.baseUrl;
            elements.keyNotes.value = state.keyNotes;
            elements.manualKeyInput.value = state.privateKey;
            elements.transactionInput.value = state.transactionId;
            syncDeviceDefaultsInputs();
            updateKeyDisplay();
            updateTransactionPreview();
            updateHeaderPanelVisibility({ silent: true });
            setPasswordVisibility(false, { silent: true });

            if (elements.headerToggle) {
                elements.headerToggle.addEventListener('click', () => {
                    state.headerExpanded = !state.headerExpanded;
                    updateHeaderPanelVisibility();
                });
            }

            if (elements.passwordToggle && elements.authPassword) {
                elements.passwordToggle.addEventListener('click', () => {
                    const visible = elements.authPassword.type === 'text';
                    setPasswordVisibility(!visible);
                });
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

            const badgeTonePalette = {
                dark: {
                    emerald: 'bg-emerald-500/20 text-emerald-200 border-emerald-500/40',
                    sky: 'bg-sky-500/20 text-sky-200 border-sky-500/40',
                    amber: 'bg-amber-500/20 text-amber-200 border-amber-500/40',
                    slate: 'bg-slate-700/40 text-slate-200 border-slate-600/60',
                },
                light: {
                    emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    sky: 'bg-sky-100 text-sky-700 border-sky-200',
                    amber: 'bg-amber-100 text-amber-700 border-amber-200',
                    slate: 'bg-slate-200 text-slate-800 border-slate-300',
                },
            };

            function badgeToneClasses(tone) {
                const palette = badgeTonePalette[state.theme === 'dark' ? 'dark' : 'light'];
                return palette[tone] || badgeTonePalette.dark[tone];
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
                badges.forEach((badge) => {
                    const tag = document.createElement('span');
                    tag.className = `rounded-full border px-3 py-1 text-[11px] font-semibold ${badgeToneClasses(badge.tone)}`;
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

            function updateHeaderPanelVisibility(options = {}) {
                const expanded = !!state.headerExpanded;
                if (elements.headerPanel) {
                    elements.headerPanel.classList.toggle('hidden', !expanded);
                }
                if (elements.headerToggle) {
                    elements.headerToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }
                if (elements.headerToggleIcon) {
                    elements.headerToggleIcon.classList.toggle('rotate-180', expanded);
                }
                if (elements.headerToggleText) {
                    elements.headerToggleText.textContent = expanded ? 'Hide header' : 'Show header';
                }
                if (!options.silent) {
                    persist();
                }
            }

            function setPasswordVisibility(visible, options = {}) {
                if (!elements.authPassword) return;
                elements.authPassword.type = visible ? 'text' : 'password';
                if (elements.passwordToggle) {
                    elements.passwordToggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
                    elements.passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                }
                if (elements.passwordEyeOpen && elements.passwordEyeClosed) {
                    elements.passwordEyeOpen.classList.toggle('hidden', visible);
                    elements.passwordEyeClosed.classList.toggle('hidden', !visible);
                }
            }

            function updateBodyScrollLock() {
                const modals = [elements.settingsModal, elements.guideModal];
                const shouldLock = modals.some((modal) => modal && !modal.classList.contains('hidden'));
                document.body.classList.toggle('overflow-hidden', shouldLock);
            }

            function renderGuideContent(endpoint) {
                if (!elements.guideTitle) return;
                const guide = endpoint?.guide;
                elements.guideTitle.textContent = endpoint ? `${endpoint.label} guide` : 'API guide';
                elements.guideSubtitle.textContent = guide?.summary || 'No guide metadata available yet.';
                elements.guidePdfDoc.textContent = guide?.pdfDoc || guideDocument;
                elements.guidePdfPages.textContent = guide?.pdfPages || '—';
                if (elements.guidePdfLink) {
                    const pdfUrl = guide?.pdfUrl || guideDocumentUrl;
                    elements.guidePdfLink.href = pdfUrl;
                    elements.guidePdfLink.classList.toggle('pointer-events-none', !pdfUrl);
                    elements.guidePdfLink.classList.toggle('opacity-50', !pdfUrl);
                }

                elements.guideValidations.innerHTML = '';
                if (guide?.validations?.length) {
                    guide.validations.forEach((rule) => {
                        const item = document.createElement('li');
                        item.className = 'rounded-xl border border-slate-800 bg-slate-950/60 p-4';
                        item.innerHTML = `<p class="text-xs uppercase tracking-[0.35em] text-slate-500">${rule.title}</p><p class="mt-1 text-sm text-slate-200">${rule.detail}</p>`;
                        elements.guideValidations.appendChild(item);
                    });
                } else {
                    const placeholder = document.createElement('li');
                    placeholder.className = 'text-sm text-slate-400';
                    placeholder.textContent = 'No validation checklist documented yet.';
                    elements.guideValidations.appendChild(placeholder);
                }

                const tables = guide?.tables || [];
                elements.guideTables.innerHTML = '';
                if (tables.length) {
                    tables.forEach((table) => {
                        const row = document.createElement('li');
                        row.className = 'rounded-xl border border-slate-800 bg-slate-950/60 p-3';
                        row.innerHTML = `<p class="text-sm font-semibold text-slate-100">${table.name}</p><p class="text-xs text-slate-400">${table.purpose}</p>`;
                        elements.guideTables.appendChild(row);
                    });
                    const tableCountLabel = tables.length === 1 ? 'table' : 'tables';
                    elements.guideTableCount.textContent = `Using ${tables.length} ${tableCountLabel}`;
                } else {
                    elements.guideTableCount.textContent = 'No tables documented';
                    const placeholder = document.createElement('li');
                    placeholder.className = 'text-sm text-slate-400';
                    placeholder.textContent = 'No table dependencies provided.';
                    elements.guideTables.appendChild(placeholder);
                }

                elements.guideSqlQuery.textContent = guide?.sql?.trim() || 'No SQL verification query provided for this endpoint yet.';
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

            function syncDeviceDefaultsInputs() {
                elements.defaultGlobalIdInput.value = state.deviceDefaults.global_device_id;
                elements.defaultMsnInput.value = state.deviceDefaults.msn;
            }

            function getResponseTone(status, theme = 'dark') {
                const isDark = theme === 'dark';
                const pick = (dark, light) => (isDark ? dark : light);

                if (status >= 200 && status < 300) {
                    return {
                        bar: pick('bg-emerald-500/20 border-emerald-500/40', 'bg-emerald-100 border-emerald-300'),
                        status: pick('text-emerald-100', 'text-emerald-800'),
                        meta: pick('text-emerald-200', 'text-emerald-600')
                    };
                }
                if (status >= 400 && status < 500) {
                    return {
                        bar: pick('bg-amber-500/20 border-amber-500/40', 'bg-amber-100 border-amber-300'),
                        status: pick('text-amber-100', 'text-amber-800'),
                        meta: pick('text-amber-200', 'text-amber-600')
                    };
                }
                if (status >= 500) {
                    return {
                        bar: pick('bg-rose-500/20 border-rose-500/40', 'bg-rose-100 border-rose-300'),
                        status: pick('text-rose-100', 'text-rose-800'),
                        meta: pick('text-rose-200', 'text-rose-600')
                    };
                }
                return {
                    bar: pick('bg-slate-900/50 border-slate-800', 'bg-slate-200 border-slate-300'),
                    status: pick('text-slate-100', 'text-slate-900'),
                    meta: pick('text-slate-400', 'text-slate-600')
                };
            }

            function setDeviceDefault(field, rawValue) {
                const value = rawValue.trim();
                const label = field === 'global_device_id' ? 'Global Device ID' : 'MSN / DSN';
                if (!value) {
                    toast(`${label} cannot be empty.`, 'error');
                    syncDeviceDefaultsInputs();
                    return;
                }
                if (state.deviceDefaults[field] === value) {
                    return;
                }
                state.deviceDefaults[field] = value;
                deviceDefaults = { ...state.deviceDefaults };
                persist();
                toast('Device defaults updated. Use "Reset template" to pull the new values.', 'success');
            }

            function showSettingsModal() {
                if (!elements.settingsModal) return;
                elements.settingsModal.classList.remove('hidden');
                elements.settingsModal.classList.remove('opacity-0');
                requestAnimationFrame(() => elements.settingsModal.classList.add('opacity-100'));
                updateBodyScrollLock();
            }

            function hideSettingsModal() {
                if (!elements.settingsModal) return;
                elements.settingsModal.classList.remove('opacity-100');
                elements.settingsModal.classList.add('opacity-0');
                setTimeout(() => {
                    elements.settingsModal.classList.add('hidden');
                    updateBodyScrollLock();
                }, 150);
            }

            function showGuideModal() {
                if (!elements.guideModal) return;
                elements.guideModal.classList.remove('hidden');
                elements.guideModal.classList.remove('opacity-0');
                requestAnimationFrame(() => elements.guideModal.classList.add('opacity-100'));
                updateBodyScrollLock();
            }

            function hideGuideModal() {
                if (!elements.guideModal) return;
                elements.guideModal.classList.remove('opacity-100');
                elements.guideModal.classList.add('opacity-0');
                setTimeout(() => {
                    elements.guideModal.classList.add('hidden');
                    updateBodyScrollLock();
                }, 150);
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
                const { status, statusText, latency, body } = state.response;
                const tone = getResponseTone(status, state.theme);
                elements.responseStatusBar.className = `border-b px-4 py-3 ${tone.bar}`;
                elements.responseStatus.className = `text-lg font-semibold ${tone.status}`;
                elements.responseMeta.className = `text-xs ${tone.meta}`;
                elements.responseStatus.textContent = `${status} ${statusText}`;
                elements.responseMeta.textContent = `Latency: ${latency} ms`;
                elements.responseBody.textContent = typeof body === 'string' ? body : JSON.stringify(body, null, 2);
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
            elements.defaultGlobalIdInput.addEventListener('change', (event) => {
                setDeviceDefault('global_device_id', event.target.value);
            });
            elements.defaultMsnInput.addEventListener('change', (event) => {
                setDeviceDefault('msn', event.target.value);
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
            elements.themeToggle?.addEventListener('click', () => {
                const nextTheme = state.theme === 'dark' ? 'light' : 'dark';
                applyTheme(nextTheme);
            });
            elements.openSettingsButton?.addEventListener('click', showSettingsModal);
            elements.closeSettingsButton?.addEventListener('click', hideSettingsModal);
            elements.settingsModal?.addEventListener('click', (event) => {
                if (event.target === elements.settingsModal) {
                    hideSettingsModal();
                }
            });
            elements.openGuideButton?.addEventListener('click', () => {
                if (!state.selectedEndpoint) {
                    toast('Select an endpoint first.', 'error');
                    return;
                }
                renderGuideContent(state.selectedEndpoint);
                showGuideModal();
            });
            elements.closeGuideButton?.addEventListener('click', hideGuideModal);
            elements.guideModal?.addEventListener('click', (event) => {
                if (event.target === elements.guideModal) {
                    hideGuideModal();
                }
            });
            elements.copyGuideSql?.addEventListener('click', async () => {
                const text = elements.guideSqlQuery?.textContent?.trim();
                if (!text) {
                    toast('No SQL snippet to copy.', 'error');
                    return;
                }
                try {
                    await navigator.clipboard.writeText(text);
                    toast('Guide SQL copied.', 'success');
                } catch (error) {
                    toast(error.message, 'error');
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                if (elements.guideModal && !elements.guideModal.classList.contains('hidden')) {
                    hideGuideModal();
                    return;
                }
                if (elements.settingsModal && !elements.settingsModal.classList.contains('hidden')) {
                    hideSettingsModal();
                }
            });

            renderEndpointList();
            selectEndpoint(apiEndpoints[0]);
        });
    </script>
</body>
</html>
