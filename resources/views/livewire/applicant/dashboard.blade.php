<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · PESO Connect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; background:#f0f2f5; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-gray-900 antialiased">

<!-- Stand-in for your app layout (sidebar/topbar come from the layout in Laravel) -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-chart-pie mr-2" style="color:#2563eb;"></i>
            Dashboard Overview
        </h1>
    </div>

    <!-- ===================== STAT CARDS (fake numbers) ===================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#1F4E79;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Registered Applicants</p>
                <p class="text-4xl font-bold leading-none mb-1">1,248</p>
                <p class="text-xs opacity-70">All-time registrations</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl"><i class="fas fa-users"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#2e7d32;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Registrations This Month</p>
                <p class="text-4xl font-bold leading-none mb-1">87</p>
                <p class="text-xs opacity-70">Current month intake</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl"><i class="fas fa-calendar-check"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#00796b;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Duplicate Flags</p>
                <p class="text-4xl font-bold leading-none mb-1">14</p>
                <p class="text-xs opacity-70">Awaiting review</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl"><i class="fas fa-copy"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#e65100;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Flagged Applicants</p>
                <p class="text-4xl font-bold leading-none mb-1">9</p>
                <p class="text-xs opacity-70">Requires attention</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl"><i class="fas fa-flag"></i></div>
        </div>
    </div>

    <!-- ===================== QUICK LINKS ===================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-2" x-data="{ showQr: false }">

        <a href="#" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#eff6ff;">
                    <i class="fas fa-users text-sm" style="color:#1F4E79;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color:#1e293b;">Applicant Management</h6>
                    <p class="text-xs" style="color:#64748b;">Search, filter, edit, and manage all registered applicants.</p>
                </div>
            </div>
        </a>

        <a href="#" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#f0fdf4;">
                    <i class="fas fa-chart-bar text-sm" style="color:#2e7d32;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color:#1e293b;">Workforce Analytics</h6>
                    <p class="text-xs" style="color:#64748b;">Interactive charts for skills, education, barangay and trends.</p>
                </div>
            </div>
        </a>

        <a href="#" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#fefce8;">
                    <i class="fas fa-exclamation-triangle text-sm" style="color:#ca8a04;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color:#1e293b;">Duplicate Review</h6>
                    <p class="text-xs" style="color:#64748b;">Review and resolve flagged duplicate registrations.</p>
                </div>
            </div>
        </a>

        <a href="#" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#f0fdfa;">
                    <i class="fas fa-search text-sm" style="color:#00796b;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color:#1e293b;">Skills Gap Analysis</h6>
                    <p class="text-xs" style="color:#64748b;">Identify underrepresented PQF skill clusters in Catanduanes.</p>
                </div>
            </div>
        </a>

        <!-- QR Code button -->
        <button type="button" @click="showQr = true"
                class="group text-left bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#f5f3ff;">
                    <i class="fas fa-qrcode text-sm" style="color:#6d28d9;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color:#1e293b;">Job Portal QR Code</h6>
                    <p class="text-xs" style="color:#64748b;">Show or download the QR applicants scan to register.</p>
                </div>
            </div>
        </button>

        <!-- ===================== QR MODAL ===================== -->
        <div x-show="showQr" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.5);"
             @click.self="showQr = false" @keydown.escape.window="showQr = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden">

                <div class="flex items-center justify-between px-6 py-4" style="background:#1a2035;">
                    <h5 class="text-sm font-bold text-white">
                        <i class="fas fa-qrcode mr-2 opacity-70"></i> Job Portal QR Code
                    </h5>
                    <button @click="showQr = false" class="text-white opacity-60 hover:opacity-100 transition-opacity">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6 text-center">
                    <!-- Placeholder QR (replaces route('qr.job-portal') image) -->
                    <svg viewBox="0 0 21 21" class="mx-auto w-56 h-56 rounded-lg border p-2" style="border-color:#e2e8f0;" shape-rendering="crispEdges">
                        <rect width="21" height="21" fill="#fff"/>
                        <g fill="#111">
                            <path d="M0 0h7v7H0z M1 1v5h5V1z"/><rect x="2" y="2" width="3" height="3"/>
                            <path d="M14 0h7v7h-7z M15 1v5h5V1z"/><rect x="16" y="2" width="3" height="3"/>
                            <path d="M0 14h7v7H0z M1 15v5h5v-5z"/><rect x="2" y="16" width="3" height="3"/>
                            <rect x="8" y="1" width="1" height="1"/><rect x="10" y="0" width="2" height="1"/>
                            <rect x="9" y="3" width="3" height="1"/><rect x="8" y="5" width="1" height="2"/>
                            <rect x="11" y="6" width="2" height="1"/><rect x="8" y="8" width="2" height="1"/>
                            <rect x="12" y="8" width="1" height="2"/><rect x="14" y="9" width="3" height="1"/>
                            <rect x="18" y="8" width="2" height="2"/><rect x="0" y="8" width="3" height="1"/>
                            <rect x="4" y="9" width="2" height="1"/><rect x="1" y="11" width="2" height="1"/>
                            <rect x="5" y="11" width="3" height="1"/><rect x="9" y="10" width="2" height="2"/>
                            <rect x="13" y="12" width="2" height="1"/><rect x="16" y="11" width="1" height="3"/>
                            <rect x="19" y="12" width="2" height="1"/><rect x="9" y="14" width="2" height="1"/>
                            <rect x="12" y="15" width="3" height="1"/><rect x="17" y="15" width="2" height="2"/>
                            <rect x="9" y="17" width="1" height="3"/><rect x="11" y="18" width="3" height="1"/>
                            <rect x="15" y="19" width="2" height="1"/><rect x="19" y="18" width="2" height="2"/>
                        </g>
                    </svg>
                    <p class="text-xs mt-4" style="color:#64748b;">Scan to open the applicant registration form.</p>
                    <a href="#" onclick="return false"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg text-white mt-4 transition-opacity hover:opacity-90"
                       style="background:#1a2035;">
                        <i class="fas fa-download"></i> Download QR
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>