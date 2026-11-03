<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name', 'PESO Catanduanes') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('pesoLogo.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Hide Alpine elements until Alpine is ready (stops submenu flash) */
        [x-cloak] { display: none !important; }

        /* Custom scrollbar for sidebar */
        #sidebar { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.15) transparent; }
        #sidebar::-webkit-scrollbar { width: 4px; }
        #sidebar::-webkit-scrollbar-track { background: transparent; }
        #sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }

        /* Nav link: same box on every state, so nothing shifts or jumps.
           Active marker is an inset shadow (no extra border width). */
        .nav-link-item {
            transition: background-color .15s ease, color .15s ease;
        }
        .nav-link-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }
        .nav-link-active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.25) 0%, rgba(37, 99, 235, 0.05) 100%);
            box-shadow: inset 3px 0 0 #3b82f6;
        }
    </style>
</head>

<body class="font-sans antialiased" style="font-family: 'Inter', sans-serif; background: #f0f2f5;">

    @php
        $mainNav = [
            ['route' => 'dashboard',  'icon' => 'fa-chart-pie',  'label' => 'Dashboard'],
            ['route' => 'applicants', 'icon' => 'fa-users',      'label' => 'Applicant Management'],
            ['route' => 'analytics',  'icon' => 'fa-chart-bar',  'label' => 'Workforce Analytics'],
            ['route' => 'reports',    'icon' => 'fa-file-alt',   'label' => 'Report Generation'],
            ['route' => 'duplicates', 'icon' => 'fa-copy',       'label' => 'Duplicate Review'],
            ['route' => 'skills-gap', 'icon' => 'fa-search',     'label' => 'Skills Gap Analysis'],
        ];

        $adminNav = [
            ['route' => 'admin.audit-logs',       'icon' => 'fa-file-alt',        'label' => 'Audit logs'],
            ['route' => 'admin.geographical',     'icon' => 'fa-map-marked-alt',  'label' => 'Geographical Management'],
            ['route' => 'admin.municipalities',   'icon' => 'fa-city',            'label' => 'Municipality Management'],
            ['route' => 'admin.skill-categories', 'icon' => 'fa-layer-group',     'label' => 'Skill Categories'],
            ['route' => 'admin.skills',           'icon' => 'fa-tools',           'label' => 'Skills'],
        ];
    @endphp

    <div class="flex h-screen overflow-hidden">

        <!-- ===================== SIDEBAR ===================== -->
        <aside id="sidebar" class="flex-shrink-0 w-64 flex flex-col overflow-y-auto h-full"
            style="background: #1a2035;">

            <!-- Logo / System Name -->
            <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10 flex-shrink-0">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg overflow-hidden bg-white">
                    <img src="{{ asset('pesoLogo.ico') }}" alt="PESO CONNECT" class="w-full h-full object-contain p-1">
                </div>
                <div class="leading-tight">
                    <div class="text-white font-bold text-sm tracking-wide">PESO CONNECT</div>
                    <div class="text-xs" style="color: #94a3b8;">Catanduanes </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-0.5">

                @foreach ($mainNav as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                        class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm
                          {{ $active ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                        <i class="fas {{ $item['icon'] }} w-4 text-center {{ $active ? 'text-blue-400' : 'text-slate-400' }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach

                @role('admin')

                <!-- Divider -->
                <div class="my-3 border-t border-white/10"></div>
                <p class="px-3 text-xs font-semibold uppercase tracking-widest mb-1" style="color: #475569;">
                    Administration</p>

                <!-- User Management (collapsible) -->
                <div x-data="{ open: {{ request()->routeIs('admin.users*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                        class="nav-link-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm
                               {{ request()->routeIs('admin.users*') ? 'text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                        <i class="fas fa-user-cog w-4 text-center text-slate-400"></i>
                        <span class="flex-1 text-left">User Management</span>
                        <i class="fas fa-chevron-down text-xs text-slate-500 transition-transform duration-150"
                            :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak class="pl-10 space-y-0.5 mt-0.5">
                        <a href="{{ route('admin.users') }}"
                            class="nav-link-item flex items-center gap-2 px-3 py-2 rounded-lg text-xs
                              {{ request()->routeIs('admin.users') ? 'text-white font-medium' : 'text-slate-400 hover:text-white' }}">
                            <i class="fas fa-circle-user text-slate-500"></i> Staff Accounts
                        </a>
                    </div>
                </div>

                @foreach ($adminNav as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                        class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm
                          {{ $active ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                        <i class="fas {{ $item['icon'] }} w-4 text-center {{ $active ? 'text-blue-400' : 'text-slate-400' }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach

                @endrole

            </nav>

            <!-- Sidebar Footer -->
            <div class="px-4 py-4 border-t border-white/10 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                        style="background: #2563eb;">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium text-white truncate">{{ auth()->user()->name ?? 'Admin' }}</div>
                        <div class="text-xs" style="color:#64748b;">{{ auth()->user()->role?->name ?? 'Administrator' }}
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Keep sidebar scroll position between page loads (this was the "bounce to top") --}}
        <script>
            (function () {
                var s = document.getElementById('sidebar');
                if (!s) return;
                var y = sessionStorage.getItem('sidebarScroll');
                if (y !== null) s.scrollTop = parseInt(y, 10);
                s.addEventListener('scroll', function () {
                    sessionStorage.setItem('sidebarScroll', s.scrollTop);
                }, { passive: true });
            })();
        </script>

        <!-- ===================== MAIN AREA ===================== -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- TOPBAR -->
            <header class="flex-shrink-0 flex items-center justify-between px-6 py-0 border-b"
                style="background: #ffffff; border-color: #e2e8f0; height: 56px;">

                <!-- Left: Mobile hamburger + breadcrumb -->
                <div class="flex items-center gap-3">
                    <button class="text-slate-500 hover:text-slate-700 lg:hidden"
                        onclick="document.getElementById('sidebar').classList.toggle('hidden')">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="text-sm font-medium" style="color: #64748b;">
                        PESO Catanduanes
                    </span>
                </div>

                <!-- Center: System subtitle -->
                <div class="hidden md:block text-center">
                    <span class="text-sm font-semibold" style="color: #1e293b;">
                        {{ $title ?? 'Dashboard' }}
                    </span>
                </div>

                <!-- Right: Admin info + Logout -->
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full"
                        style="background: #f1f5f9; color: #475569;">
                        <i class="fas fa-user-shield text-blue-500 mr-1"></i>
                        {{ auth()->user()->name ?? 'Admin User' }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-red-50 hover:text-red-600 hover:border-red-200"
                            style="color: #64748b; border-color: #e2e8f0;">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <!-- PAGE CONTENT -->
            <main class="flex-1 overflow-y-auto p-6" style="background: #f0f2f5;">
                {{ $slot }}
            </main>

        </div>
    </div>

    @livewireScripts
</body>

</html>