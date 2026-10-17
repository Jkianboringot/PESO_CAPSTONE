<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-house mr-2" style="color:#059669;"></i>
            Welcome, {{ $applicant->first_name }}!
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Review and keep your PESO records up to date.</p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#1F4E79;">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Reference ID</p>
            <p class="text-lg font-bold leading-tight break-all">{{ $applicant->reference_id }}</p>
            <p class="text-xs opacity-70 mt-1">Use this to sign in</p>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-id-badge"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#2e7d32;">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Registered On</p>
            <p class="text-2xl font-bold leading-tight">{{ optional($applicant->created_at)->format('M d, Y') ?? '—' }}</p>
            <p class="text-xs opacity-70 mt-1">Date of registration</p>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-calendar-check"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#00796b;">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">My Skills</p>
            <p class="text-4xl font-bold leading-none">{{ $skillCount }}</p>
            <p class="text-xs opacity-70 mt-1">Skills on your record</p>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-screwdriver-wrench"></i></div>
        </div>

        <div class="rounded-xl p-5 text-white relative overflow-hidden" style="background:#e65100;">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Education</p>
            <p class="text-lg font-bold leading-tight">{{ $education->highest_level ?? 'Not set' }}</p>
            <p class="text-xs opacity-70 mt-1">Highest level reached</p>
            <div class="absolute right-4 top-4 opacity-10 text-5xl"><i class="fas fa-graduation-cap"></i></div>
        </div>
    </div>

    {{-- Completeness --}}
    <div class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
        <div class="flex items-center justify-between mb-2">
            <h6 class="text-sm font-bold" style="color:#1e293b;">Profile completeness</h6>
            <span class="text-sm font-bold" style="color:#059669;">{{ $completion }}%</span>
        </div>
        <div class="w-full h-2.5 rounded-full" style="background:#e2e8f0;">
            <div class="h-2.5 rounded-full" style="width:{{ $completion }}%; background:#10b981;"></div>
        </div>
        <p class="text-xs mt-2" style="color:#64748b;">Add your contact details, education and at least one skill to reach 100%.</p>
    </div>

    {{-- Quick links --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('applicant.profile') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-emerald-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#ecfdf5;"><i class="fas fa-id-card text-sm" style="color:#059669;"></i></div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5" style="color:#1e293b;">My Registration</h6>
                    <p class="text-xs" style="color:#64748b;">View your details and update your contact info.</p>
                </div>
            </div>
        </a>
        <a href="{{ route('applicant.skills') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-emerald-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#f0fdfa;"><i class="fas fa-screwdriver-wrench text-sm" style="color:#00796b;"></i></div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5" style="color:#1e293b;">Skills</h6>
                    <p class="text-xs" style="color:#64748b;">Add or remove the skills on your record.</p>
                </div>
            </div>
        </a>
        <a href="{{ route('applicant.employment') }}" class="group block bg-white rounded-xl p-4 border border-slate-100 hover:border-emerald-200 hover:shadow-md transition-all">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#fff7ed;"><i class="fas fa-briefcase text-sm" style="color:#e65100;"></i></div>
                <div>
                    <h6 class="text-sm font-bold mb-0.5" style="color:#1e293b;">Education &amp; Employment</h6>
                    <p class="text-xs" style="color:#64748b;">Keep your education details current.</p>
                </div>
            </div>
        </a>
    </div>
</div>