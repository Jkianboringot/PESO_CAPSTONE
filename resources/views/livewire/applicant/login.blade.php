<div class="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden"
     style="background: linear-gradient(135deg, #ecfdf5 0%, #fef3c7 100%);">

    <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full pointer-events-none"
         style="background:#a7f3d0; opacity:.5; filter:blur(60px);"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full pointer-events-none"
         style="background:#fcd34d; opacity:.35; filter:blur(70px);"></div>

    <div class="relative w-full max-w-5xl bg-white rounded-3xl overflow-hidden flex flex-col lg:flex-row"
         style="box-shadow:0 25px 60px -15px rgba(5,150,105,.35);">

        <!-- LEFT: FORM -->
        <div class="w-full lg:w-1/2 px-8 sm:px-12 py-12 flex flex-col justify-center">

            <div class="flex items-center gap-3 mb-8">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center p-2"
                     style="background:#ecfdf5; border:2px solid #a7f3d0;">
                    <img src="{{ asset('images/pesoLogoLogin.png') }}" alt="PESO Connect"
                         class="w-full h-full object-contain">
                </div>
                <div class="leading-tight">
                    <div class="font-bold text-sm" style="color:#064e3b;">PESO Catanduanes</div>
                    <div class="text-xs" style="color:#6b7280;">Skills Registry</div>
                </div>
            </div>

            <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1 text-[11px] font-bold tracking-widest mb-4"
                  style="background:#d1fae5; color:#047857;">
                <i class="fas fa-circle text-[6px]"></i> APPLICANT PORTAL
            </span>

            <h2 class="text-3xl font-extrabold mb-1" style="color:#064e3b;">Hello, jobseeker! 👋</h2>
            <p class="text-sm mb-8" style="color:#6b7280;">
                Sign in to view and update your PESO registration.
            </p>

            <form wire:submit="authenticate">

                <div>
                    <x-input-label for="login" value="Reference ID, contact number or email" />
                    <x-text-input id="login" wire:model="login" class="block mt-1 w-full rounded-xl"
                                  type="text" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('login')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="password" value="Password" />
                    <x-text-input id="password" wire:model="password" class="block mt-1 w-full rounded-xl"
                                  type="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="block mt-4">
                    <label for="remember" class="inline-flex items-center">
                        <input id="remember" wire:model="remember" type="checkbox"
                               class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        <span class="ms-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        class="mt-6 w-full px-4 py-3 rounded-xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                        style="background: linear-gradient(90deg,#059669,#0d9488); box-shadow:0 8px 20px -6px rgba(5,150,105,.6);">
                    <span wire:loading.remove wire:target="authenticate">Sign in <i class="fas fa-arrow-right ms-1 text-xs"></i></span>
                    <span wire:loading wire:target="authenticate">Signing in...</span>
                </button>
            </form>

            <div class="mt-8 rounded-2xl p-4 flex items-center justify-between gap-3"
                 style="background:#fffbeb; border:1px dashed #fcd34d;">
                <div>
                    <div class="text-sm font-bold" style="color:#92400e;">New here?</div>
                    <div class="text-xs" style="color:#b45309;">Create your PESO applicant record.</div>
                </div>
                <a href="{{ url('/job-portal') }}"
                   class="shrink-0 px-4 py-2 rounded-full text-xs font-bold text-white transition hover:opacity-90"
                   style="background:#f59e0b;">
                    <i class="fas fa-user-plus me-1"></i> Register
                </a>
            </div>

            <p class="text-xs mt-6" style="color:#9ca3af;">
                Forgot your password? Contact the PESO office.
                <a href="{{ route('login') }}" class="underline ms-1" style="color:#6b7280;">Staff? Sign in here</a>
            </p>
        </div>

        <!-- RIGHT: BRANDING -->
        <div class="hidden lg:flex w-1/2 flex-col justify-between p-12 relative overflow-hidden text-white"
             style="background: linear-gradient(160deg,#059669 0%,#0f766e 60%,#115e59 100%);">

            <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full" style="background:rgba(255,255,255,.08);"></div>
            <div class="absolute bottom-10 -left-20 w-72 h-72 rounded-full" style="background:rgba(255,255,255,.06);"></div>

            <div class="relative">
                <div class="text-xs font-semibold uppercase tracking-widest" style="color:#a7f3d0;">Catanduanes Province</div>
                <h1 class="text-3xl font-extrabold leading-snug mt-3 mb-3">
                    Your career,<br>
                    <span style="color:#fcd34d;">your records.</span>
                </h1>
                <p class="text-sm leading-relaxed" style="color:#d1fae5;">
                    Use the reference ID you received when you registered to keep your
                    Public Employment Service Office profile up to date.
                </p>
            </div>

            <div class="relative space-y-3">
                <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.12);">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#fcd34d;">
                        <i class="fas fa-shield-halved" style="color:#92400e;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Data Privacy Protected</div>
                        <div class="text-xs" style="color:#a7f3d0;">Secured under RA 10173</div>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.12);">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#fcd34d;">
                        <i class="fas fa-id-card" style="color:#92400e;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Your Registration</div>
                        <div class="text-xs" style="color:#a7f3d0;">Review what you submitted to PESO</div>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl p-4" style="background:rgba(255,255,255,.12);">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#fcd34d;">
                        <i class="fas fa-briefcase" style="color:#92400e;"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Skills &amp; Employment</div>
                        <div class="text-xs" style="color:#a7f3d0;">Keep your work profile current</div>
                    </div>
                </div>
            </div>

            <p class="relative text-xs" style="color:#99f6e4;">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
        </div>
    </div>
</div>