@php
    $applicant = auth('applicant')->user();
    $fullName  = trim(($applicant->first_name ?? '') . ' ' . ($applicant->last_name ?? '')) ?: 'Applicant';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — ' : '' }}PESO Applicant Portal</title>

    <link rel="icon" type="image/png" href="{{ asset('pesoLogo.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        #sidebar::-webkit-scrollbar { width: 4px; }
        #sidebar::-webkit-scrollbar-track { background: transparent; }
        #sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

        .nav-link-active {
            background: linear-gradient(90deg, rgba(16,185,129,.28) 0%, rgba(16,185,129,.05) 100%);
            border-left: 3px solid #10b981;
        }
        .nav-link-item:hover { background: rgba(255,255,255,.06); }
    </style>
</head>

<body class="font-sans antialiased" style="font-family:'Inter',sans-serif; background:#f0f2f5;">

    <div class="flex h-screen overflow-hidden">

        <!-- ===================== SIDEBAR ===================== -->
        <aside id="sidebar" class="hidden lg:flex flex-shrink-0 w-64 flex-col overflow-y-auto"
               style="background:#1a2035; min-height:100vh;">

            <!-- Logo / System Name -->
            <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg overflow-hidden bg-white">
                    <img src="{{ asset('pesoLogo.ico') }}" alt="PESO CONNECT" class="w-full h-full object-contain p-1">
                </div>
                <div class="leading-tight">
                    <div class="text-white font-bold text-sm tracking-wide">PESO CONNECT</div>
                    <div class="text-xs" style="color:#94a3b8;">Applicant Portal</div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-0.5">

                <!-- Dashboard -->
                <a href="{{ route('applicant.applicant-dashboard') }}"
                   class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                   {{ request()->routeIs('applicant.dashboard') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i class="fas fa-house w-4 text-center {{ request()->routeIs('applicant.dashboard') ? 'text-emerald-400' : 'text-slate-400' }}"></i>
                    <span>Dashboard</span>
                </a>

                <!-- My Registration -->
                @if (Route::has('applicant.profile'))
                <a href="{{ route('applicant.profile') }}"
                   class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                   {{ request()->routeIs('applicant.profile') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i class="fas fa-id-card w-4 text-center {{ request()->routeIs('applicant.profile') ? 'text-emerald-400' : 'text-slate-400' }}"></i>
                    <span>My Registration</span>
                </a>
                @endif

                <!-- Skills -->
                @if (Route::has('applicant.skills'))
                <a href="{{ route('applicant.skills') }}"
                   class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                   {{ request()->routeIs('applicant.skills') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i class="fas fa-screwdriver-wrench w-4 text-center {{ request()->routeIs('applicant.skills') ? 'text-emerald-400' : 'text-slate-400' }}"></i>
                    <span>Skills</span>
                </a>
                @endif

                <!-- Education & Employment -->
                @if (Route::has('applicant.employment'))
                <a href="{{ route('applicant.employment') }}"
                   class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                   {{ request()->routeIs('applicant.employment') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i class="fas fa-briefcase w-4 text-center {{ request()->routeIs('applicant.employment') ? 'text-emerald-400' : 'text-slate-400' }}"></i>
                    <span>Education &amp; Employment</span>
                </a>
                @endif

                <!-- Divider -->
                <div class="my-3 border-t border-white/10"></div>
                <p class="px-3 text-xs font-semibold uppercase tracking-widest mb-1" style="color:#475569;">Account</p>

                <!-- Change password -->
                @if (Route::has('applicant.password'))
                <a href="{{ route('applicant.password') }}"
                   class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                   {{ request()->routeIs('applicant.password') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i class="fas fa-key w-4 text-center {{ request()->routeIs('applicant.password') ? 'text-emerald-400' : 'text-slate-400' }}"></i>
                    <span>Change Password</span>
                </a>
                @endif
            </nav>

            <!-- Sidebar Footer -->
            <div class="px-4 py-4 border-t border-white/10">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                         style="background:#059669;">
                        {{ strtoupper(substr($applicant->first_name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-white truncate">{{ $fullName }}</div>
                        <div class="text-xs truncate" style="color:#64748b;">{{ $applicant->reference_id ?? 'Applicant' }}</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ===================== MAIN AREA ===================== -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- TOPBAR -->
            <header class="flex-shrink-0 flex items-center justify-between px-6 py-0 border-b"
                    style="background:#ffffff; border-color:#e2e8f0; height:56px;">

                <div class="flex items-center gap-3">
                    <button class="text-slate-500 hover:text-slate-700 lg:hidden"
                            onclick="document.getElementById('sidebar').classList.toggle('hidden')">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="text-sm font-medium" style="color:#64748b;">PESO Catanduanes</span>
                </div>

                <div class="hidden md:block text-center">
                    <span class="text-sm font-semibold" style="color:#1e293b;">{{ $title ?? 'Dashboard' }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full"
                          style="background:#d1fae5; color:#047857;">
                        <i class="fas fa-user mr-1"></i> {{ $fullName }}
                    </span>
                    <form method="POST" action="{{ route('applicant.logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-red-50 hover:text-red-600 hover:border-red-200"
                                style="color:#64748b; border-color:#e2e8f0;">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <!-- PAGE CONTENT -->
            <main class="flex-1 overflow-y-auto p-6" style="background:#f0f2f5;">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>