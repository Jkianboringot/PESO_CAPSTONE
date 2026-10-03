<x-guest-layout>

    <div class="min-h-screen flex" style="background:#f0f2f5;">

        <!-- ===================== LEFT BRANDING PANEL ===================== -->
        <div class="hidden lg:flex lg:w-2/5 flex-col justify-between px-10 py-12 relative overflow-hidden"
             style="background:#1a2035;">

            <div class="absolute inset-0 opacity-[0.04] pointer-events-none"
                 style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 18px 18px;"></div>

            <div class="relative">
                {{-- Logo (fixed size so it never collapses) --}}
                <div class="inline-flex items-center justify-center rounded-xl p-3 mb-6"
                     style="background:#ffffff; width:96px; height:96px;">
                    <img src="{{ asset('images/logo.png') }}"
                         alt="PESO Connect"
                         style="width:100%; height:100%; object-fit:contain; display:block;">
                </div>

                <div class="leading-tight mb-10">
                    <div class="text-xs font-semibold uppercase tracking-widest" style="color:#94a3b8;">Catanduanes Province</div>
                    <div class="text-white font-bold text-sm">PESO Skills Registry</div>
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
                    <div style="width:48px; height:48px;">
                        <img src="{{ asset('images/pesoLogoLogin.png') }}"
                             alt="PESO Connect"
                             style="width:100%; height:100%; object-fit:contain; display:block;">
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

                {{-- ===================== JOB PORTAL SHORTCUT ===================== --}}
                <div class="mt-8 pt-6" style="border-top:1px solid #e2e8f0;">
                    <p class="text-xs font-semibold uppercase tracking-wider mb-3" style="color:#94a3b8;">
                        Looking to register as an applicant?
                    </p>

                    <a href="{{ url('/job-portal') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                       style="background:#ffffff; color:#1a2035; border:1px solid #cbd5e1;"
                       onmouseover="this.style.background='#f8fafc'"
                       onmouseout="this.style.background='#ffffff'">
                        <i class="fas fa-user-plus text-xs"></i>
                        Go to Job Portal
                        <i class="fas fa-arrow-up-right-from-square text-[10px]" style="color:#94a3b8;"></i>
                    </a>

                    {{-- QR code (hidden on phones, since they can just tap the button) --}}
                    <div class="hidden sm:flex items-center gap-4 mt-4">
                        <a href="{{ url('/job-portal') }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex-shrink-0 rounded-lg p-2"
                           style="background:#ffffff; border:1px solid #e2e8f0;">
                            <img src="{{ route('qr.job-portal') }}"
                                 alt="QR code for the PESO Job Portal"
                                 style="width:96px; height:96px; display:block;">
                        </a>
                        <p class="text-xs leading-relaxed" style="color:#64748b;">
                            Applicants can scan this QR code with their phone to open the registration form.
                        </p>
                    </div>
                </div>

                <p class="text-center text-xs mt-8" style="color:#94a3b8;">
                    Having trouble signing in? Contact your PESO system administrator.
                </p>
            </div>
        </div>
    </div>

</x-guest-layout>