<div>
    <x-slot name="title">Dashboard</x-slot>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-chart-pie mr-2" style="color: #2563eb;"></i>
                Dashboard Overview
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Public Employment Service Office — Catanduanes Province</p>
        </div>
        <div class="text-xs px-3 py-1.5 rounded-lg" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
            <i class="fas fa-clock mr-1"></i>
            Last updated: {{ now()->format('M d, Y h:i A') }}
        </div>
    </div>

    {{-- ===================== STAT CARDS ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        {{-- Card 1: Total Applicants — Navy Blue --}}
        <div class="rounded-xl p-5 text-white relative overflow-hidden"
             style="background: #1F4E79;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Registered Applicants</p>
                <p class="text-4xl font-bold leading-none mb-1">{{ $stats['total_applicants'] ?? '0' }}</p>
                <p class="text-xs opacity-70">All-time registrations</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl">
                <i class="fas fa-users"></i>
            </div>
        </div>

        {{-- Card 2: This Month — Green --}}
        <div class="rounded-xl p-5 text-white relative overflow-hidden"
             style="background: #2e7d32;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Registrations This Month</p>
                <p class="text-4xl font-bold leading-none mb-1">{{ $stats['this_month'] ?? '0' }}</p>
                <p class="text-xs opacity-70">Current month intake</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>

        {{-- Card 3: Pending Duplicates — Teal --}}
        <div class="rounded-xl p-5 text-white relative overflow-hidden"
             style="background: #00796b;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Duplicate Flags</p>
                <p class="text-4xl font-bold leading-none mb-1">{{ $stats['pending_duplicates'] ?? '0' }}</p>
                <p class="text-xs opacity-70">Awaiting review</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl">
                <i class="fas fa-copy"></i>
            </div>
        </div>

        {{-- Card 4: Flagged — Orange --}}
        <div class="rounded-xl p-5 text-white relative overflow-hidden"
             style="background: #e65100;">
            <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Flagged Applicants</p>
                <p class="text-4xl font-bold leading-none mb-1">{{ $stats['flagged'] ?? '0' }}</p>
                <p class="text-xs opacity-70">Requires attention</p>
            </div>
            <div class="absolute right-4 top-4 opacity-10 text-6xl">
                <i class="fas fa-flag"></i>
            </div>
        </div>

    </div>

    {{-- ===================== QUICK LINKS ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-2">

        <a href="{{ route('applicants') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: #eff6ff;">
                    <i class="fas fa-users text-sm" style="color: #1F4E79;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color: #1e293b;">Applicant Management</h6>
                    <p class="text-xs" style="color: #64748b;">Search, filter, edit, and manage all registered applicants.</p>
                </div>
            </div>
        </a>

        <a href="{{ route('analytics') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: #f0fdf4;">
                    <i class="fas fa-chart-bar text-sm" style="color: #2e7d32;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color: #1e293b;">Workforce Analytics</h6>
                    <p class="text-xs" style="color: #64748b;">Interactive charts for skills, education, barangay and trends.</p>
                </div>
            </div>
        </a>

        <a href="{{ route('reports') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: #fff7ed;">
                    <i class="fas fa-file-alt text-sm" style="color: #e65100;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color: #1e293b;">Report Generation</h6>
                    <p class="text-xs" style="color: #64748b;">Export DOLE BLE-compliant CSV/Excel reports with filters.</p>
                </div>
            </div>
        </a>

        <a href="{{ route('duplicates') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: #fefce8;">
                    <i class="fas fa-exclamation-triangle text-sm" style="color: #ca8a04;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color: #1e293b;">Duplicate Review</h6>
                    <p class="text-xs" style="color: #64748b;">Review and resolve flagged duplicate registrations.</p>
                </div>
            </div>
        </a>

        <a href="{{ route('skills-gap') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: #f0fdfa;">
                    <i class="fas fa-search text-sm" style="color: #00796b;"></i>
                </div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5 group-hover:text-blue-700 transition-colors" style="color: #1e293b;">Skills Gap Analysis</h6>
                    <p class="text-xs" style="color: #64748b;">Identify underrepresented PQF skill clusters in Catanduanes.</p>
                </div>
            </div>
        </a>

    </div>

    {{-- Footer Note --}}
    <div class="mt-6 text-center">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>

</div>