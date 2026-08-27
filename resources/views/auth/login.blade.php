<x-guest-layout>

    <div class="min-h-screen flex" style="background:#f0f2f5;">

        <!-- ===================== LEFT BRANDING PANEL ===================== -->
        <div class="hidden lg:flex lg:w-2/5 flex-col justify-between px-10 py-12 relative overflow-hidden"
             style="background:#1a2035;">

            <div class="absolute inset-0 opacity-[0.04]"
                 style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 18px 18px;"></div>

            <div class="relative">
                <div class="flex items-center gap-3 mb-10">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg"
                         style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <i class="fas fa-briefcase text-white text-sm"></i>
                    </div>
                    <div class="leading-tight">
                        <div class="text-xs font-semibold uppercase tracking-widest" style="color:#94a3b8;">Catanduanes Province</div>
                        <div class="text-white font-bold text-sm">PESO Skills Registry</div>
                    </div>
                </div>

                <h1 class="text-3xl font-bold text-white leading-snug mb-3">
                    Welcome back.<br>
                    <span style="color:#94a3b8;">Manage the workforce registry.</span>
                </h1>
                <p class="text-sm leading-relaxed" style="color:#94a3b8;">
                    Sign in to review applicants, generate reports, and manage the Public Employment
                    Service Office labor market database.
                </p>
            </div>

            <div class="relative space-y-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.06);">
                        <i class="fas fa-shield-halved text-xs" style="color:#60a5fa;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Data Privacy Protected</div>
                        <div class="text-xs" style="color:#64748b;">Access is logged and secured under RA 10173</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.06);">
                        <i class="fas fa-users text-xs" style="color:#60a5fa;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Applicant Management</div>
                        <div class="text-xs" style="color:#64748b;">Search, verify, and update registrant records</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.06);">
                        <i class="fas fa-chart-bar text-xs" style="color:#60a5fa;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Workforce Analytics</div>
                        <div class="text-xs" style="color:#64748b;">Track skills gaps and employment trends</div>
                    </div>
                </div>
            </div>

            <p class="relative text-xs" style="color:#475569;">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
        </div>

        <!-- ===================== RIGHT FORM PANEL ===================== -->
        <div class="flex-1 flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-sm">

                <!-- Mobile-only logo -->
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg"
                         style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <i class="fas fa-briefcase text-white text-sm"></i>
                    </div>
                    <div class="leading-tight">
                        <div class="font-bold text-sm" style="color:#1e293b;">PESO Catanduanes</div>
                        <div class="text-xs" style="color:#64748b;">Skills Registry</div>
                    </div>
                </div>

                <h2 class="text-xl font-bold mb-1" style="color:#1e293b;">Staff Sign In</h2>
                <p class="text-xs mb-6" style="color:#64748b;">Enter your credentials to access the dashboard.</p>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <x-input-label for="password" :value="__('Password')" />

                        <x-text-input id="password" class="block mt-1 w-full"
                                        type="password"
                                        name="password"
                                        required autocomplete="current-password" />

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="block mt-4">
                        <label for="remember_me" class="inline-flex items-center">
                            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                            <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        @if (Route::has('password.request'))
                            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif

                        <x-primary-button class="ms-3">
                            {{ __('Log in') }}
                        </x-primary-button>
                    </div>
                </form>

                <p class="text-center text-xs mt-8" style="color:#94a3b8;">
                    Having trouble signing in? Contact your PESO system administrator.
                </p>
            </div>
        </div>
    </div>

</x-guest-layout>