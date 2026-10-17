<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('pesoLogo.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Font Awesome (icons used in the panel) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">

    <div class="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden"
         style="background: linear-gradient(135deg, #f0f2f5 0%, #dbeafe 100%);">

        <!-- Background blobs -->
        <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full pointer-events-none"
             style="background:#93c5fd; opacity:.45; filter:blur(60px);"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full pointer-events-none"
             style="background:#1a2035; opacity:.25; filter:blur(70px);"></div>

        <div class="relative w-full max-w-5xl bg-white rounded-3xl overflow-hidden flex flex-col lg:flex-row"
             style="box-shadow:0 25px 60px -15px rgba(26,32,53,.4);">

            <!-- ===================== LEFT: FORM ===================== -->
            <div class="w-full lg:w-1/2 px-8 sm:px-12 py-12 flex flex-col justify-center">

                <div class="flex items-center gap-3 mb-8">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center p-2"
                         style="background:#f0f2f5; border:2px solid #cbd5e1;">
                        <img src="{{ asset('pesoLogoLogin.png') }}" alt="PESO Connect"
                             class="w-full h-full object-contain">
                    </div>
                    <div class="leading-tight">
                        <div class="font-bold text-sm" style="color:#1a2035;">PESO Catanduanes</div>
                        <div class="text-xs" style="color:#64748b;">Skills Registry</div>
                    </div>
                </div>

                <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1 text-[11px] font-bold tracking-widest mb-4"
                      style="background:#dbeafe; color:#1e40af;">
                    <i class="fas fa-circle text-[6px]"></i> STAFF PORTAL
                </span>

                <h2 class="text-3xl font-extrabold mb-1" style="color:#1a2035;">Welcome back!</h2>
                <p class="text-sm mb-8" style="color:#64748b;">
                    Enter your credentials to access the dashboard.
                </p>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full rounded-xl" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <x-input-label for="password" :value="__('Password')" />

                        <x-text-input id="password" class="block mt-1 w-full rounded-xl"
                                        type="password"
                                        name="password"
                                        required autocomplete="current-password" />

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me + Forgot -->
                    <div class="flex items-center justify-between mt-4">
                        <label for="remember_me" class="inline-flex items-center">
                            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                            <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="mt-6 w-full px-4 py-3 rounded-xl text-sm font-bold text-white transition hover:-translate-y-0.5"
                            style="background: linear-gradient(90deg,#1a2035,#2d3a5f); box-shadow:0 8px 20px -6px rgba(26,32,53,.6);">
                        {{ __('Log in') }} <i class="fas fa-arrow-right ms-1 text-xs"></i>
                    </button>
                </form>

                <div class="mt-8 rounded-2xl p-4 flex items-center gap-3"
                     style="background:#f1f5f9; border:1px dashed #94a3b8;">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#60a5fa;">
                        <i class="fas fa-headset" style="color:#1a2035;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold" style="color:#1a2035;">Trouble signing in?</div>
                        <div class="text-xs" style="color:#64748b;">Contact your PESO system administrator.</div>
                    </div>
                </div>
            </div>

            <!-- ===================== RIGHT: BRANDING ===================== -->
            <div class="hidden lg:flex w-1/2 flex-col justify-between p-12 relative overflow-hidden text-white"
                 style="background: linear-gradient(160deg,#1a2035 0%,#232c4a 60%,#2d3a5f 100%);">

                <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full" style="background:rgba(255,255,255,.06);"></div>
                <div class="absolute bottom-10 -left-20 w-72 h-72 rounded-full" style="background:rgba(255,255,255,.04);"></div>
                <div class="absolute inset-0 opacity-[0.04] pointer-events-none"
                     style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 18px 18px;"></div>

                <div class="relative">
                    <div class="text-xs font-semibold uppercase tracking-widest" style="color:#94a3b8;">Catanduanes Province</div>
                    <h1 class="text-3xl font-extrabold leading-snug mt-3 mb-3">
                        Manage the<br>
                        <span style="color:#60a5fa;">workforce registry.</span>
                    </h1>
                    <p class="text-sm leading-relaxed" style="color:#cbd5e1;">
                        Sign in to review applicants, generate reports, and manage the Public Employment
                        Service Office labor market database.
                    </p>
                </div>

                <div class="relative space-y-3">
                    <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.08);">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#60a5fa;">
                            <i class="fas fa-shield-halved" style="color:#1a2035;"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold">Data Privacy Protected</div>
                            <div class="text-xs" style="color:#94a3b8;">Access is logged and secured under RA 10173</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.08);">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#60a5fa;">
                            <i class="fas fa-users" style="color:#1a2035;"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold">Applicant Management</div>
                            <div class="text-xs" style="color:#94a3b8;">Search, verify, and update registrant records</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.08);">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#60a5fa;">
                            <i class="fas fa-chart-bar" style="color:#1a2035;"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold">Workforce Analytics</div>
                            <div class="text-xs" style="color:#94a3b8;">Track skills gaps and employment trends</div>
                        </div>
                    </div>
                </div>

                <p class="relative text-xs" style="color:#64748b;">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
            </div>
        </div>
    </div>

</body>
</html>