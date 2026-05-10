<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name', 'PESO Catanduanes') }}</title>

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
        /* Custom scrollbar for sidebar */
        #sidebar::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }

        /* Sidebar active nav highlight gradient */
        .nav-link-active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.25) 0%, rgba(37, 99, 235, 0.05) 100%);
            border-left: 3px solid #3b82f6;
        }

        /* Sidebar hover */
        .nav-link-item:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        /* Smooth sidebar collapse */
        .sidebar-submenu {
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
    </style>
</head>

<body class="font-sans antialiased" style="font-family: 'Inter', sans-serif; background: #f0f2f5;">

    <div class="flex h-screen overflow-hidden">

        <!-- ===================== SIDEBAR ===================== -->
        <aside id="sidebar" class="flex-shrink-0 w-64 flex flex-col overflow-y-auto"
            style="background: #1a2035; min-height: 100vh;">

            <!-- Logo / System Name -->
            <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg"
                    style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                    <i class="fas fa-briefcase text-white text-sm"></i>
                </div>
                <div class="leading-tight">
                    <div class="text-white font-bold text-sm tracking-wide">PESO</div>
                    <div class="text-xs" style="color: #94a3b8;">Catanduanes LME</div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-0.5">

                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('dashboard') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-chart-pie w-4 text-center {{ request()->routeIs('dashboard') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Applicants -->
                <a href="{{ route('applicants') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('applicants') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-users w-4 text-center {{ request()->routeIs('applicants') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Applicant Management</span>
                </a>

                <!-- Analytics -->
                <a href="{{ route('analytics') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('analytics') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-chart-bar w-4 text-center {{ request()->routeIs('analytics') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Workforce Analytics</span>
                </a>

                <!-- Reports -->
                {{-- <a href="{{ route('reports') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('reports') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-file-alt w-4 text-center {{ request()->routeIs('reports') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Report Generation</span>
            </a> --}}

                <!-- Duplicates -->
                <a href="{{ route('duplicates') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('duplicates') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-copy w-4 text-center {{ request()->routeIs('duplicates') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Duplicate Review</span>
                </a>

                <!-- Skills Gap -->
                <a href="{{ route('skills-gap') }}"
                    class="nav-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                      {{ request()->routeIs('skills-gap') ? 'nav-link-active text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                    <i
                        class="fas fa-search w-4 text-center {{ request()->routeIs('skills-gap') ? 'text-blue-400' : 'text-slate-400' }}"></i>
                    <span>Skills Gap Analysis</span>
                </a>
            @role('admin')
                
                <!-- Divider -->
                <div class="my-3 border-t border-white/10"></div>
                <p class="px-3 text-xs font-semibold uppercase tracking-widest mb-1" style="color: #475569;">
                    Administration</p>

                <!-- User Management (collapsible) -->
                <div x-data="{ open: {{ request()->routeIs('admin.users*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                        class="nav-link-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all
                               {{ request()->routeIs('admin.users*') ? 'text-white font-medium' : 'text-slate-300 hover:text-white' }}">
                        <i class="fas fa-user-cog w-4 text-center text-slate-400"></i>
                        <span class="flex-1 text-left">User Management</span>
                        <i class="fas fa-chevron-down text-xs text-slate-500 transition-transform"
                            :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div class="sidebar-submenu pl-10 space-y-0.5 mt-0.5"
                        :style="open ? 'max-height:200px' : 'max-height:0'">
                        <a href="{{ route('admin.users') }}"
                            class="nav-link-item flex items-center gap-2 px-3 py-2 rounded-lg text-xs transition-all
                              {{ request()->routeIs('admin.users') ? 'text-white font-medium' : 'text-slate-400 hover:text-white' }}">
                            <i class="fas fa-circle-user text-slate-500"></i> Staff Accounts
                        </a>
                    </div>
                </div>
            @endrole()

            </nav>

            <!-- Sidebar Footer -->
            <div class="px-4 py-4 border-t border-white/10">
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
                        PESO Catanduanes — Labor Market Exchange System
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