<div class="min-h-screen flex flex-col lg:flex-row relative" style="background:linear-gradient(135deg,#f4f6f9 0%,#e3edf7 100%);">

    {{-- A11Y: placeholder contrast (placeholders were too light to meet 4.5:1) --}}
    <style>
        .field-input::placeholder { color: #475569; opacity: 1; }
    </style>

    {{-- soft background blobs (decorative) --}}
    <div aria-hidden="true" class="fixed -top-24 right-0 w-96 h-96 rounded-full pointer-events-none" style="background:#93c5fd; opacity:.30; filter:blur(80px);"></div>
    <div aria-hidden="true" class="fixed -bottom-32 left-1/3 w-96 h-96 rounded-full pointer-events-none" style="background:#bbf7d0; opacity:.30; filter:blur(90px);"></div>

    {{-- ═══════════════════════════════════════════════════
         LEFT PANEL — Branding + Info (stays in view on desktop while the form scrolls)
         A11Y: now an <aside>; headings inside it are styled <p> so the page has one clean heading outline
    ════════════════════════════════════════════════════ --}}
    <aside aria-label="About the PESO Skills Registry" class="hero-pattern lg:w-72 xl:w-80 shrink-0 flex-col justify-between px-6 py-10 text-white hidden lg:flex lg:sticky lg:top-0 lg:h-screen lg:self-start lg:overflow-hidden">

        <div aria-hidden="true" class="absolute -top-16 -right-16 w-64 h-64 rounded-full pointer-events-none" style="background:rgba(255,255,255,.07);"></div>
        <div aria-hidden="true" class="absolute bottom-24 -left-20 w-72 h-72 rounded-full pointer-events-none" style="background:rgba(255,255,255,.05);"></div>

        <div class="relative">
            {{-- Logo / Office --}}
            <div class="flex items-center gap-3 mb-10">
                <div aria-hidden="true" class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                    <i aria-hidden="true" class="fas fa-briefcase text-white text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Catanduanes Province</p>
                    <p class="text-sm font-bold leading-tight">PESO Skills Registry</p>
                </div>
            </div>

            <p class="text-3xl font-extrabold leading-snug mb-4">
                Register your skills.<br>
                <span class="opacity-80">Find better opportunities.</span>
            </p>
            <p class="text-sm opacity-90 leading-relaxed mb-8">
                Join the Public Employment Service Office workforce database.
                Your profile helps connect you with local employers and livelihood programs.
            </p>

            {{-- Benefits --}}
            <ul class="space-y-3">
                @foreach([
                    ['fas fa-shield-halved',  'Data Privacy Protected',     'Your information is secured under RA 10173'],
                    ['fas fa-magnifying-glass','Employment Referrals',       'Get matched with local job openings'],
                    ['fas fa-graduation-cap', 'Livelihood Programs',         'Access DOLE and TESDA training opportunities'],
                    ['fas fa-id-card',        'Official Reference ID',       'Track your registration status anytime'],
                ] as [$icon, $title, $desc])
                <li class="flex items-center gap-3 rounded-2xl bg-white/10 p-3">
                    <div aria-hidden="true" class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
                        <i aria-hidden="true" class="{{ $icon }} text-xs text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">{{ $title }}</p>
                        <p class="text-xs opacity-90 leading-snug">{{ $desc }}</p>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Footer --}}
        <div class="relative mt-8">
            <p class="text-xs opacity-80">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
        </div>
    </aside>

    {{-- ═══════════════════════════════════════════════════
         RIGHT PANEL — Form
         A11Y: <main> landmark + a single <h1>
    ════════════════════════════════════════════════════ --}}
    <main id="main-content" class="flex-1 flex items-start justify-center py-8 px-4 sm:px-8 lg:px-12 relative z-10">
        <div class="w-full max-w-2xl">

            <h1 class="sr-only">PESO Skills Registry — Applicant Registration</h1>

            {{-- Mobile-only header (left panel is hidden below lg) --}}
            <div class="lg:hidden flex items-center gap-3 mb-6">
                <div aria-hidden="true" class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#1F4E79;">
                    <i aria-hidden="true" class="fas fa-briefcase text-white text-sm"></i>
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-bold" style="color:#1e293b;">PESO Skills Registry</p>
                    <p class="text-xs" style="color:#475569;">Catanduanes Province</p>
                </div>
            </div>

            {{-- ── SUCCESS STATE ─────────────────────────────── --}}
            @if($submitted)
            <div class="relative overflow-hidden bg-white rounded-3xl border border-slate-100 p-10 sm:p-12 text-center"
                 role="status"
                 style="box-shadow:0 20px 50px -20px rgba(31,78,121,.25);">
                <div aria-hidden="true" class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
                     style="background:#dcfce7; box-shadow:0 0 0 10px #f0fdf4;">
                    <i aria-hidden="true" class="fas fa-circle-check text-4xl" style="color:#2e7d32;"></i>
                </div>
                <h2 class="text-2xl font-extrabold mb-1" style="color:#1e293b;">Registration Successful!</h2>
                <p class="text-sm mb-6" style="color:#475569;">Your profile has been submitted to the PESO office.</p>

                <p class="text-sm font-medium mb-2" style="color:#475569;">Your Reference ID</p>
                <div class="inline-block px-6 py-3 rounded-2xl text-xl font-mono font-bold tracking-wider mb-6"
                     style="background:#eff6ff; color:#1F4E79; border:1px dashed #93c5fd;">
                    {{ $reference_id }}
                </div>
                <p class="text-sm max-w-sm mx-auto" style="color:#475569;">
                    Keep this ID for your records. Present it when visiting the PESO office for referrals or inquiries.
                </p>

                {{-- How to log in --}}
                <div class="max-w-sm mx-auto mt-6 rounded-2xl p-4 text-left flex items-start gap-3" style="background:#eff6ff; border:1px solid #bfdbfe;">
                    <i aria-hidden="true" class="fas fa-circle-info text-sm mt-0.5 flex-shrink-0" style="color:#1F4E79;"></i>
                    <div class="text-sm leading-relaxed" style="color:#1e40af;">
                        <p class="font-semibold mb-1">How to log in</p>
                        <p>Use your <strong>contact number</strong> or <strong>email</strong> as your username, then enter the password you created.</p>
                    </div>
                </div>

                {{-- Link to applicant login --}}
                <a href="{{ route('applicant.login') }}"
                   class="inline-flex items-center gap-2 mt-6 text-sm font-bold px-6 py-2.5 rounded-xl text-white transition-all hover:-translate-y-0.5 active:scale-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#1F4E79]/40"
                   style="background:linear-gradient(90deg,#1F4E79,#2a6199); box-shadow:0 8px 20px -8px rgba(31,78,121,.6);">
                    <i aria-hidden="true" class="fas fa-right-to-bracket text-xs"></i> Go to Applicant Login
                </a>
            </div>
            @else
@if($step === 1 && ! $returnToReview)
<div class="mb-4 flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-5 py-3 text-sm"
     style="box-shadow:0 10px 30px -18px rgba(31,78,121,.3);">
    <span style="color:#475569;">Already registered?</span>
    <a href="{{ route('applicant.login') }}" class="font-bold underline underline-offset-2 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#1F4E79]/40 rounded" style="color:#1F4E79;">
        <i aria-hidden="true" class="fas fa-right-to-bracket text-xs mr-1"></i> Log in
    </a>
</div>
@endif
            {{-- ── STEP PROGRESS ──────────────────────────────── --}}

            @php
                $stepLabels = ['Personal', 'Address', 'Education', 'Skills', 'Experience', 'Consent'];
                $stepIcons  = ['fas fa-user', 'fas fa-location-dot', 'fas fa-graduation-cap', 'fas fa-wrench', 'fas fa-briefcase', 'fas fa-file-shield'];
            @endphp
            <nav aria-label="Registration progress" class="mb-6 bg-white rounded-2xl border border-slate-100 px-5 pt-5 pb-4" style="box-shadow:0 10px 30px -18px rgba(31,78,121,.3);">
                <div class="flex items-start">
                    @foreach($stepLabels as $i => $label)
                        @php $n = $i + 1; @endphp
                        <div class="flex flex-col items-center" @if($step === $n) aria-current="step" @endif>
                            <div class="relative flex flex-col items-center">
                                {{-- circle --}}
                                <div aria-hidden="true" class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                                    {{ $step > $n ? 'text-white' : ($step === $n ? 'text-white' : 'bg-white border-2 border-slate-300 text-slate-600') }}"
                                     style="{{ $step > $n ? 'background:#1F4E79;' : ($step === $n ? 'background:#1F4E79; box-shadow:0 0 0 4px rgba(31,78,121,0.15);' : '') }}">
                                    @if($step > $n)
                                        <i aria-hidden="true" class="fas fa-check text-xs"></i>
                                    @else
                                        <i aria-hidden="true" class="{{ $stepIcons[$i] }} text-xs"></i>
                                    @endif
                                </div>
                                {{-- label (sr-only on mobile instead of hidden, so screen readers still get it) --}}
                                <p class="text-xs mt-1.5 font-medium sr-only sm:not-sr-only
                                    {{ $step === $n ? 'font-bold' : 'text-slate-600' }}"
                                   style="{{ $step === $n ? 'color:#1F4E79;' : '' }}">
                                    {{ $label }}
                                    <span class="sr-only">{{ $step > $n ? '(completed)' : ($step === $n ? '(current step)' : '(not started)') }}</span>
                                </p>
                            </div>
                        </div>
                        {{-- connector --}}
                        @if($n < $totalSteps)
                            <div aria-hidden="true" class="flex-1 h-0.5 mt-[17px] mx-1.5 rounded transition-all duration-500"
                                 style="{{ $step > $n ? 'background:#1F4E79;' : 'background:#e2e8f0;' }}"></div>
                        @endif
                    @endforeach
                </div>
            </nav>

            {{-- ── FORM CARD ──────────────────────────────────── --}}
            <div class="bg-white rounded-3xl border border-slate-100"
                 style="box-shadow:0 20px 50px -20px rgba(31,78,121,.25);">

                {{-- Card header --}}
                <div class="px-7 pt-6 pb-5 border-b border-slate-100 flex items-center gap-4 rounded-t-3xl"
                     style="background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);">
                    <div aria-hidden="true" class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                         style="background:#e0ecf7; color:#1F4E79;">
                        <i aria-hidden="true" class="{{ $stepIcons[$step - 1] }}"></i>
                    </div>
                    <div class="min-w-0">
                        {{-- PREVIEW: tell the applicant they are in edit mode --}}
                        @if($returnToReview)
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-semibold mb-1.5"
                                  style="background:#fef3c7; color:#92400e;">
                                <i aria-hidden="true" class="fas fa-pen text-[9px]"></i> Editing from review — save to go back
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold mb-1.5"
                                  style="background:#e0ecf7; color:#1F4E79;">
                                Step {{ $step }} of {{ $totalSteps }}
                            </span>
                        @endif
                        <h2 class="text-xl font-extrabold leading-tight" style="color:#1e293b;">
                            {{ ['Personal Information', 'Address & Location', 'Educational Background', 'Skills Profile', 'Experience & Training', 'Review & Consent'][$step - 1] }}
                        </h2>
                        <p class="text-sm mt-0.5" style="color:#475569;">
                            {{ ['Tell us a bit about yourself.', 'Where you live helps us route referrals to your area.', 'Your highest level of schooling.', 'Pick what you can do and how well.', 'Add any jobs, trainings or certificates.', 'Double-check everything, then confirm.'][$step - 1] }}
                        </p>
                    </div>
                </div>

                {{-- Card body --}}
                <div class="px-7 py-6 step-body">

                    {{-- ══ STEP 1: Personal ══════════════════════ --}}
                    @if($step === 1)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div>
                            <label for="last_name" class="block text-sm font-medium mb-1.5" style="color:#334155;">Last Name <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <input id="last_name" wire:model.live="last_name"
                                   autocomplete="family-name" aria-required="true"
                                   @error('last_name') aria-invalid="true" aria-describedby="last_name_error" @enderror
                                   placeholder="e.g. Dela Cruz"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('last_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('last_name')<p id="last_name_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium mb-1.5" style="color:#334155;">First Name <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <input id="first_name" wire:model.live="first_name"
                                   autocomplete="given-name" aria-required="true"
                                   @error('first_name') aria-invalid="true" aria-describedby="first_name_error" @enderror
                                   placeholder="e.g. Juan"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('first_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('first_name')<p id="first_name_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="middle_name" class="block text-sm font-medium mb-1.5" style="color:#334155;">Middle Name <span class="text-slate-600 font-normal">(optional)</span></label>
                            <input id="middle_name" wire:model="middle_name"
                                   autocomplete="additional-name"
                                   @error('middle_name') aria-invalid="true" aria-describedby="middle_name_error" @enderror
                                   placeholder="e.g. Santos"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('middle_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('middle_name')<p id="middle_name_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="birthdate" class="block text-sm font-medium mb-1.5" style="color:#334155;">Date of Birth <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <input id="birthdate" type="date" wire:model="birthdate"
                                   autocomplete="bday" aria-required="true"
                                   @error('birthdate') aria-invalid="true" aria-describedby="birthdate_error" @enderror
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('birthdate') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('birthdate')<p id="birthdate_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="sex" class="block text-sm font-medium mb-1.5" style="color:#334155;">Sex <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <select id="sex" wire:model="sex" aria-required="true"
                                    @error('sex') aria-invalid="true" aria-describedby="sex_error" @enderror
                                    class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('sex') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Prefer not to say</option>
                            </select>
                            @error('sex')<p id="sex_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="civil_status" class="block text-sm font-medium mb-1.5" style="color:#334155;">Civil Status <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <select id="civil_status" wire:model="civil_status" aria-required="true"
                                    @error('civil_status') aria-invalid="true" aria-describedby="civil_status_error" @enderror
                                    class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('civil_status') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <option value="">Select</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                            </select>
                            @error('civil_status')<p id="civil_status_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="contact_number" class="block text-sm font-medium mb-1.5" style="color:#334155;">Contact Number <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm">
                                    <i aria-hidden="true" class="fas fa-phone text-xs"></i>
                                </span>
                                <input id="contact_number" wire:model.live="contact_number"
                                       type="number"
                                       autocomplete="tel" aria-required="true"
                                       @error('contact_number') aria-invalid="true" aria-describedby="contact_number_error" @enderror
                                       placeholder="09XXXXXXXXX"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('contact_number') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('contact_number')<p id="contact_number_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="email" class="block text-sm font-medium mb-1.5" style="color:#334155;">Email Address <span class="text-slate-600 font-normal">(optional)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm">
                                    <i aria-hidden="true" class="fas fa-envelope text-xs"></i>
                                </span>
                                <input id="email" type="email" wire:model="email"
                                       autocomplete="email"
                                       @error('email') aria-invalid="true" aria-describedby="email_error" @enderror
                                       placeholder="juandelacruz@email.com"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('email') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('email')<p id="email_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- group: other --}}
                        <div class="sm:col-span-2 flex items-center gap-3 pt-2">
                            <h3 class="text-sm font-semibold" style="color:#1F4E79;">Other details</h3>
                            <div aria-hidden="true" class="flex-1 h-px bg-slate-200"></div>
                        </div>

                        {{-- PWD — optional, multiple checkboxes (A11Y: fieldset + legend) --}}
                        <fieldset class="sm:col-span-2 min-w-0 border-0 p-0 m-0">
                            <legend class="block text-sm font-medium mb-0.5 p-0" style="color:#334155;">
                                Person with Disability (PWD) <span class="text-slate-600 font-normal">(optional)</span>
                            </legend>
                            <p id="pwd_hint" class="text-xs mb-2.5" style="color:#64748b;">Tick all that apply. Leave unchecked if none.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($disabilityOptions as $d)
                                <label class="flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm cursor-pointer transition-all focus-within:ring-2 focus-within:ring-[#1F4E79]/40
                                    {{ in_array($d, $disabilities) ? 'border-[#1F4E79] bg-[#dbeafe]' : 'border-slate-400 bg-slate-100 hover:border-[#1F4E79] hover:bg-[#e8f1fb]' }}"
                                    style="color:#1e293b;">
                                    <input type="checkbox" wire:model.live="disabilities" value="{{ $d }}"
                                           aria-describedby="pwd_hint"
                                           class="rounded border-slate-300" style="accent-color:#1F4E79;">
                                    <span>{{ $d }}</span>
                                </label>
                                @endforeach
                            </div>
                            @error('disabilities')<p role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @error('disabilities.*')<p role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </fieldset>

                        {{-- group: account --}}
                        <div class="sm:col-span-2 flex items-center gap-3 pt-2">
                            <h3 class="text-sm font-semibold" style="color:#1F4E79;">Create your password</h3>
                            <div aria-hidden="true" class="flex-1 h-px bg-slate-200"></div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium mb-1.5" style="color:#334155;">Password <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500"><i aria-hidden="true" class="fas fa-lock text-xs"></i></span>
                                <input id="password" type="password" wire:model="password" autocomplete="new-password" aria-required="true"
                                       @error('password') aria-invalid="true" aria-describedby="password_hint password_error" @else aria-describedby="password_hint" @enderror
                                       placeholder="At least 8 characters"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition-all @error('password') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <button type="button"
                                        onclick="const i = this.parentElement.querySelector('input'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.querySelector('i').className = 'fas text-xs ' + (show ? 'fa-eye-slash' : 'fa-eye'); this.setAttribute('aria-pressed', show ? 'true' : 'false');"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-800 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1F4E79]/60"
                                        aria-label="Show password" aria-pressed="false">
                                    <i aria-hidden="true" class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                            <p id="password_hint" class="sr-only">Use at least 8 characters.</p>
                            @error('password')<p id="password_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium mb-1.5" style="color:#334155;">Confirm Password <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500"><i aria-hidden="true" class="fas fa-lock text-xs"></i></span>
                                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" aria-required="true"
                                       placeholder="Re-enter password"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition-all border-slate-400 bg-slate-100">
                                <button type="button"
                                        onclick="const i = this.parentElement.querySelector('input'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.querySelector('i').className = 'fas text-xs ' + (show ? 'fa-eye-slash' : 'fa-eye'); this.setAttribute('aria-pressed', show ? 'true' : 'false');"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-800 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1F4E79]/60"
                                        aria-label="Show confirm password" aria-pressed="false">
                                    <i aria-hidden="true" class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 2: Address ════════════════════════ --}}
                    @if($step === 2)
                    <div class="space-y-5">

                        <div>
                            <label for="address" class="block text-sm font-medium mb-1.5" style="color:#334155;">Street / Sitio / Purok Address <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500"><i aria-hidden="true" class="fas fa-map-pin text-xs"></i></span>
                                <input id="address" wire:model="address"
                                       autocomplete="street-address" aria-required="true"
                                       @error('address') aria-invalid="true" aria-describedby="address_error" @enderror
                                       placeholder="Blk 1 Lot 2, Purok Sampaguita"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('address') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('address')<p id="address_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="municipality_id" class="block text-sm font-medium mb-1.5" style="color:#334155;">Municipality <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                                <select id="municipality_id" wire:model.live="municipality_id" aria-required="true"
                                        @error('municipality_id') aria-invalid="true" aria-describedby="municipality_id_error" @enderror
                                        class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('municipality_id') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                    <option value="">Select Municipality</option>
                                    @foreach($municipalities as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('municipality_id')<p id="municipality_id_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="barangay_id" class="block text-sm font-medium mb-1.5" style="color:#334155;">Barangay <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                                <select id="barangay_id" wire:model="barangay_id" aria-required="true"
                                        {{ empty($barangays) ? 'disabled' : '' }}
                                        @error('barangay_id') aria-invalid="true" aria-describedby="barangay_id_error" @enderror
                                        class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all
                                            {{ empty($barangays) ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : '' }}
                                            @error('barangay_id') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                    <option value="">{{ empty($barangays) ? 'Select municipality first' : 'Select Barangay' }}</option>
                                    @foreach($barangays as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('barangay_id')<p id="barangay_id_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- Map hint --}}
                        <div class="rounded-2xl p-4 flex items-start gap-3" style="background:#eff6ff; border:1px solid #bfdbfe;">
                            <i aria-hidden="true" class="fas fa-circle-info text-sm mt-0.5 flex-shrink-0" style="color:#1F4E79;"></i>
                            <p class="text-sm leading-relaxed" style="color:#1e40af;">
                                Make sure to select the correct barangay. This information is used to route employment referrals and livelihood programs to your locality.
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- ══ STEP 3: Education ══════════════════════ --}}
                    @if($step === 3)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        {{-- A11Y: radio group now has a fieldset + legend --}}
                        <fieldset class="sm:col-span-2 min-w-0 border-0 p-0 m-0">
                            <legend class="block text-sm font-medium mb-1.5 p-0" style="color:#334155;">Highest Educational Level <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span></legend>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $level)
                                <label class="relative cursor-pointer">
                                    <input type="radio"
                                           name="highest_level"
                                           wire:model="highest_level"
                                           value="{{ $level }}"
                                           @error('highest_level') aria-invalid="true" aria-describedby="highest_level_error" @enderror
                                           class="peer sr-only">
                                    <div class="px-3 py-3 rounded-xl border text-sm font-medium text-center transition-all
                                                peer-checked:border-[#1F4E79] peer-checked:bg-[#dbeafe] peer-checked:text-[#1F4E79]
                                                peer-focus-visible:ring-2 peer-focus-visible:ring-[#1F4E79]/40
                                                @error('highest_level') border-red-400 @else border-slate-400 bg-slate-100 @enderror
                                                text-slate-700 hover:border-[#1F4E79] hover:bg-[#e8f1fb]">
                                        {{ $level }}
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @error('highest_level')<p id="highest_level_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </fieldset>

                        <div>
                            <label for="course_program" class="block text-sm font-medium mb-1.5" style="color:#334155;">Course / Program <span class="text-slate-600 font-normal">(optional)</span></label>
                            <input id="course_program" wire:model="course_program"
                                   @error('course_program') aria-invalid="true" aria-describedby="course_program_error" @enderror
                                   placeholder="e.g. BS Information Technology"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('course_program') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('course_program')<p id="course_program_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="year_graduated" class="block text-sm font-medium mb-1.5" style="color:#334155;">Year Graduated <span class="text-slate-600 font-normal">(optional)</span></label>
                            <input id="year_graduated" type="number" wire:model="year_graduated"
                                   min="1950" max="{{ date('Y') + 1 }}"
                                   @error('year_graduated') aria-invalid="true" aria-describedby="year_graduated_error" @enderror
                                   placeholder="{{ date('Y') }}"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('year_graduated') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('year_graduated')<p id="year_graduated_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="school_name" class="block text-sm font-medium mb-1.5" style="color:#334155;">School / University <span class="text-slate-600 font-normal">(optional)</span></label>
                            <div class="relative">
                                <span aria-hidden="true" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500"><i aria-hidden="true" class="fas fa-school text-xs"></i></span>
                                <input id="school_name" wire:model="school_name"
                                       @error('school_name') aria-invalid="true" aria-describedby="school_name_error" @enderror
                                       placeholder="e.g. Catanduanes State University"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('school_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('school_name')<p id="school_name_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 4: Skills ═════════════════════════ --}}
                    @if($step === 4)
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <p class="text-sm" style="color:#475569;">Select all skills that apply, then rate your proficiency level.</p>
                            <span aria-live="polite" class="shrink-0">
                                @if(count($selected_skills) > 0)
                                <span class="text-xs font-semibold px-3 py-1 rounded-full" style="background:#e0ecf7; color:#1F4E79;">
                                    {{ count($selected_skills) }} selected
                                </span>
                                @endif
                            </span>
                        </div>

                        {{-- Covers both "pick at least one" and an invalid individual skill id --}}
                        @php
                            $skillsError = $errors->first('selected_skills') ?: $errors->first('selected_skills.*');
                        @endphp
                        @if($skillsError)
                        <div role="alert" class="flex items-center gap-2 px-4 py-3 rounded-xl mb-4 text-sm" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;">
                            <i aria-hidden="true" class="fas fa-triangle-exclamation text-xs"></i> {{ $skillsError }}
                        </div>
                        @endif

                        <div class="space-y-6">
                            @foreach($skillCategories as $category)
                            <div role="group" aria-label="{{ $category->name }} skills">
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="text-xs font-bold px-3 py-1 rounded-full text-white" style="background:#1F4E79;">{{ $category->name }}</span>
                                    <div aria-hidden="true" class="flex-1 h-px" style="background:#cbd5e1;"></div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($category->skills as $skill)
                                    <div class="skill-card rounded-xl border p-3 transition-all
                                        {{ in_array($skill->id, $selected_skills) ? 'border-[#1F4E79] bg-[#dbeafe] shadow-sm ring-1 ring-[#1F4E79]/30' : 'border-slate-400 bg-slate-100 hover:border-[#1F4E79] hover:bg-[#e8f1fb] hover:shadow-md' }}">

                                        <label for="skill_{{ $skill->id }}" class="flex items-start gap-3 cursor-pointer w-full">
                                            <div class="relative mt-0.5 flex-shrink-0">
                                                <input type="checkbox"
                                                       wire:model.live="selected_skills"
                                                       value="{{ $skill->id }}"
                                                       id="skill_{{ $skill->id }}"
                                                       class="sr-only peer">
                                                <div aria-hidden="true" class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all
                                                    peer-focus-visible:ring-2 peer-focus-visible:ring-[#1F4E79]/40
                                                    {{ in_array($skill->id, $selected_skills)
                                                        ? 'border-[#1F4E79] bg-[#1F4E79]'
                                                        : 'border-slate-500 bg-white' }}">
                                                    @if(in_array($skill->id, $selected_skills))
                                                    <i aria-hidden="true" class="fas fa-check text-[10px] text-white"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium leading-snug" style="color:#1e293b;">{{ $skill->name }}</span>
                                        </label>

                                        @if(in_array($skill->id, $selected_skills))
                                        <div class="mt-2 ml-7">
                                            {{-- Whatever is picked here is sent to the backend as skill_proficiencies[skill_id] => level, e.g. 9 => "Advanced", 10 => "Intermediate" --}}
                                            <label for="proficiency_{{ $skill->id }}" class="sr-only">Proficiency level for {{ $skill->name }}</label>
                                            <select id="proficiency_{{ $skill->id }}"
                                                    wire:model="skill_proficiencies.{{ $skill->id }}"
                                                    @error('skill_proficiencies.' . $skill->id) aria-invalid="true" aria-describedby="proficiency_error_{{ $skill->id }}" @enderror
                                                    class="w-full px-3 py-1.5 rounded-lg border bg-white text-xs font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-[#1F4E79]/40
                                                           @error('skill_proficiencies.' . $skill->id) border-red-400 bg-red-50 @else border-[#1F4E79]/60 @enderror"
                                                    style="color:#1F4E79;">
                                                @foreach($proficiencyLevels as $pl)
                                                    <option>{{ $pl }}</option>
                                                @endforeach
                                            </select>
                                            @error('skill_proficiencies.' . $skill->id)
                                                <p id="proficiency_error_{{ $skill->id }}" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Skills that are not in the list above --}}
                        <div class="mt-6">
                            <label for="other_skills" class="block text-sm font-medium mb-0.5" style="color:#334155;">Other Skills <span class="text-slate-600 font-normal">(optional)</span></label>
                            <p id="other_skills_hint" class="text-xs mb-2.5" style="color:#64748b;">Have a skill that is not in the list above? Describe it here.</p>
                            <textarea id="other_skills" wire:model="other_skills" rows="3"
                                      @error('other_skills') aria-invalid="true" aria-describedby="other_skills_hint other_skills_error" @else aria-describedby="other_skills_hint" @enderror
                                      placeholder="e.g. Motorcycle repair, cake decorating"
                                      class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('other_skills') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror"></textarea>
                            @error('other_skills')<p id="other_skills_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @endif

                    {{-- ══ STEP 5: Experience & Training ══════════ --}}
                    @if($step === 5)
                    @php
                        // [field, label, placeholder, input kind, required, grid span]
                        $sections = [
                            'work_experiences' => [
                                'title'  => 'Work Experience / Job History',
                                'item'   => 'job',
                                'add'    => 'Add job',
                                'icon'   => 'fas fa-briefcase',
                                'fields' => [
                                    ['company_name', 'Company / Place of Work', 'e.g. Virac Public Market', 'text', true, 'sm:col-span-2'],
                                    ['position', 'Position', 'e.g. Cashier', 'text', true, ''],
                                    ['duration', 'How Long', 'e.g. 2 years 3 months', 'text', false, ''],
                                    ['description', 'Description', 'What did you do in this job?', 'textarea', false, 'sm:col-span-2'],
                                ],
                            ],
                            'trainings' => [
                                'title'  => 'Trainings',
                                'item'   => 'training',
                                'add'    => 'Add training',
                                'icon'   => 'fas fa-chalkboard-user',
                                'fields' => [
                                    ['title', 'Training Title', 'e.g. Basic Welding', 'text', true, 'sm:col-span-2'],
                                    ['provider', 'Provider', 'e.g. TESDA', 'text', false, ''],
                                    ['year', 'Year', date('Y'), 'number', false, ''],
                                ],
                            ],
                            'seminars' => [
                                'title'  => 'Seminars',
                                'item'   => 'seminar',
                                'add'    => 'Add seminar',
                                'icon'   => 'fas fa-people-group',
                                'fields' => [
                                    ['title', 'Seminar Title', 'e.g. Financial Literacy Seminar', 'text', true, 'sm:col-span-2'],
                                    ['organizer', 'Organizer', 'e.g. DOLE', 'text', false, ''],
                                    ['year', 'Year', date('Y'), 'number', false, ''],
                                ],
                            ],
                            'certifications' => [
                                'title'  => 'Certifications / Licenses',
                                'item'   => 'certification',
                                'add'    => 'Add certification',
                                'icon'   => 'fas fa-award',
                                'fields' => [
                                    ['name', 'Certification Name', 'e.g. NC II Welding', 'text', true, 'sm:col-span-2'],
                                    ['issuing_body', 'Issued By', 'e.g. TESDA', 'text', false, 'sm:col-span-2'],
                                    ['year_issued', 'Year Issued', date('Y'), 'number', false, ''],
                                    ['expiry_year', 'Expiry Year', 'Leave empty if none', 'number', false, ''],
                                ],
                            ],
                        ];
                    @endphp

                    <div class="space-y-8">
                        <p class="text-sm" style="color:#475569;">Everything on this page is optional. Add only what applies to you.</p>

                        @foreach($sections as $type => $sec)
                        <section aria-labelledby="section_{{ $type }}">
                            <div class="flex items-center gap-3 mb-3">
                                <div aria-hidden="true" class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#1F4E79;">
                                    <i aria-hidden="true" class="{{ $sec['icon'] }} text-xs text-white"></i>
                                </div>
                                <h3 id="section_{{ $type }}" class="text-sm font-bold" style="color:#1e293b;">{{ $sec['title'] }}</h3>
                                <div aria-hidden="true" class="flex-1 h-px" style="background:#cbd5e1;"></div>
                                <button type="button" wire:click="addRow('{{ $type }}')"
                                        class="flex items-center gap-1.5 text-xs font-semibold px-3.5 py-1.5 rounded-full text-white transition-all hover:-translate-y-0.5 active:scale-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#1F4E79]/40"
                                        style="background:#1F4E79; box-shadow:0 6px 14px -6px rgba(31,78,121,.6);">
                                    <i aria-hidden="true" class="fas fa-plus text-[10px]"></i> {{ $sec['add'] }}
                                </button>
                            </div>

                            @if(empty($this->{$type}))
                                <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white/70 px-4 py-4 text-center">
                                    <p class="text-sm" style="color:#475569;">None added yet. Use the button above to add one.</p>
                                </div>
                            @endif

                            @foreach($this->{$type} as $i => $row)
                            <div role="group" aria-label="{{ ucfirst($sec['item']) }} {{ $i + 1 }}" class="rounded-2xl border border-slate-300 border-l-4 border-l-[#1F4E79] bg-white shadow-sm p-4 mb-3" wire:key="{{ $type }}-{{ $i }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach($sec['fields'] as [$key, $label, $placeholder, $kind, $required, $span])
                                    @php $fid = $type . '_' . $i . '_' . $key; @endphp
                                    <div class="{{ $span }}">
                                        <label for="{{ $fid }}" class="block text-sm font-medium mb-1.5" style="color:#334155;">
                                            {{ $label }} @if($required)<span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span>@endif
                                        </label>
                                        @if($kind === 'textarea')
                                            <textarea id="{{ $fid }}" wire:model="{{ $type }}.{{ $i }}.{{ $key }}" rows="2"
                                                      @if($required) aria-required="true" @endif
                                                      @error($type . '.' . $i . '.' . $key) aria-invalid="true" aria-describedby="{{ $fid }}_error" @enderror
                                                      placeholder="{{ $placeholder }}"
                                                      class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error($type . '.' . $i . '.' . $key) border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror"></textarea>
                                        @else
                                            <input id="{{ $fid }}" type="{{ $kind }}" wire:model="{{ $type }}.{{ $i }}.{{ $key }}"
                                                   @if($required) aria-required="true" @endif
                                                   @error($type . '.' . $i . '.' . $key) aria-invalid="true" aria-describedby="{{ $fid }}_error" @enderror
                                                   placeholder="{{ $placeholder }}"
                                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error($type . '.' . $i . '.' . $key) border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                        @endif
                                        @error($type . '.' . $i . '.' . $key)<p id="{{ $fid }}_error" role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    @endforeach
                                </div>
                                <div class="flex justify-end mt-3">
                                    <button type="button" wire:click="removeRow('{{ $type }}', {{ $i }})"
                                            aria-label="Remove {{ $sec['item'] }} {{ $i + 1 }}"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1F4E79]/50" style="color:#475569;">
                                        <i aria-hidden="true" class="fas fa-trash-can mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </section>
                        @endforeach
                    </div>
                    @endif

                    {{-- ══ STEP 6: Review + Consent ═══════════════ --}}
                    @if($step === 6)
                    <div class="space-y-5">

                        {{-- PREVIEW: full review with Edit buttons (replaces the old small summary box) --}}
                        @include('livewire.partials.review-summary')

                        {{-- Applicant's declaration (oath) --}}
                        <div class="rounded-2xl p-5 text-sm leading-relaxed" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <div class="flex items-center gap-2 mb-3">
                                <i aria-hidden="true" class="fas fa-file-signature" style="color:#1F4E79;"></i>
                                <h3 class="font-semibold text-sm" style="color:#1F4E79;">Applicant's Declaration</h3>
                            </div>
                            <p>
                                I hereby certify that the information I provided is true and correct. I authorize
                                <strong>PESO Catanduanes Province</strong> to use my information for employment referral,
                                livelihood and training programs, and for reporting to the Department of Labor and Employment (DOLE).
                            </p>
                        </div>

                        {{-- A11Y: was a clickable <div>. Now a real checkbox with the SAME toggle action (wire:click="$toggle('oath_given')") --}}
                        <label for="oath_given" class="flex items-start gap-4 cursor-pointer rounded-xl border p-4 transition-all focus-within:ring-2 focus-within:ring-[#1F4E79]/40"
                               style="{{ $oath_given ? 'border-color:#1F4E79; background:#dbeafe;' : ($errors->has('oath_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}">
                            <input id="oath_given" type="checkbox"
                                   wire:click="$toggle('oath_given')"
                                   @checked($oath_given)
                                   aria-required="true"
                                   @error('oath_given') aria-invalid="true" aria-describedby="oath_given_error" @enderror
                                   class="mt-0.5 h-5 w-5 shrink-0 cursor-pointer" style="accent-color:#1F4E79;">
                            <span class="text-sm leading-relaxed" style="color:#1e293b;">
                                <strong>I certify and authorize</strong> the above declaration.
                            </span>
                        </label>
                        @error('oath_given')
                        <p id="oath_given_error" role="alert" class="text-xs text-red-600 flex items-center gap-1.5 -mt-2">
                            <i aria-hidden="true" class="fas fa-circle-exclamation"></i> {{ $message }}
                        </p>
                        @enderror

                        <div class="rounded-2xl p-5 text-sm leading-relaxed" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <div class="flex items-center gap-2 mb-3">
                                <i aria-hidden="true" class="fas fa-file-shield" style="color:#1F4E79;"></i>
                                <h3 class="font-semibold text-sm" style="color:#1F4E79;">Republic Act No. 10173 — Data Privacy Act of 2012</h3>
                            </div>
                            <p>
                                The information you provide will be collected, stored, and processed by the
                                <strong>Public Employment Service Office (PESO) of Catanduanes Province</strong>
                                solely for the purposes of workforce planning, employment referral, and compliance
                                reporting to the Department of Labor and Employment (DOLE).
                            </p>
                            <p class="mt-3">
                                Your personal data will be protected in accordance with RA 10173. You have the
                                right to access, correct, or request erasure of your data at any time by visiting
                                the PESO office.
                            </p>
                        </div>

                        {{-- Consent checkbox (A11Y: real checkbox, same toggle action) --}}
                        <label for="consent_given" class="flex items-start gap-4 cursor-pointer rounded-xl border p-4 transition-all focus-within:ring-2 focus-within:ring-[#1F4E79]/40"
                               style="{{ $consent_given ? 'border-color:#1F4E79; background:#dbeafe;' : ($errors->has('consent_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}">
                            <input id="consent_given" type="checkbox"
                                   wire:click="$toggle('consent_given')"
                                   @checked($consent_given)
                                   aria-required="true"
                                   @error('consent_given') aria-invalid="true" aria-describedby="consent_given_error" @enderror
                                   class="mt-0.5 h-5 w-5 shrink-0 cursor-pointer" style="accent-color:#1F4E79;">
                            <span class="text-sm leading-relaxed" style="color:#1e293b;">
                                <strong>I have read and understood</strong> the above data privacy statement and I
                                <strong>voluntarily give my consent</strong> for the collection and processing of
                                my personal information by PESO Catanduanes Province.
                            </span>
                        </label>
                        @error('consent_given')
                        <p id="consent_given_error" role="alert" class="text-xs text-red-600 flex items-center gap-1.5 -mt-2">
                            <i aria-hidden="true" class="fas fa-circle-exclamation"></i> {{ $message }}
                        </p>
                        @enderror

                    </div>
                    @endif

                </div>

                {{-- Card footer / navigation (sticky so Continue is always reachable on long steps) --}}
                <div class="sticky bottom-0 z-10 px-7 py-4 border-t border-slate-100 bg-white/95 backdrop-blur rounded-b-3xl flex items-center justify-between">

                    {{-- PREVIEW: Previous is hidden while editing from the review screen --}}
                    @if($step > 1 && ! $returnToReview)
                    <button type="button" wire:click="prevStep"
                            class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border transition-all hover:bg-slate-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#1F4E79]/30"
                            style="border-color:#cbd5e1; color:#475569;">
                        <i aria-hidden="true" class="fas fa-arrow-left text-xs"></i> Previous
                    </button>
                    @else
                    <span></span>
                    @endif

                    @if($step < $totalSteps)
                    <button type="button" wire:click="nextStep"
                            wire:loading.attr="disabled" wire:target="nextStep"
                            class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-xl text-white transition-all hover:-translate-y-0.5 active:scale-95 disabled:opacity-60 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#1F4E79]/40"
                            style="background:linear-gradient(90deg,#1F4E79,#2a6199); box-shadow:0 8px 20px -8px rgba(31,78,121,.6);">
                        {{-- PREVIEW: label changes while editing from review --}}
                        {{ $returnToReview ? 'Save & back to review' : 'Continue' }} <i aria-hidden="true" class="fas fa-arrow-right text-xs"></i>
                    </button>
                    @else
                    {{-- plain form: no card classes, otherwise it nests a second card inside the footer --}}
                    <form wire:submit.prevent="submit">
                        <button type="submit"
                                class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-xl text-white transition-all hover:-translate-y-0.5 active:scale-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-green-700/40"
                                style="background:#2e7d32; box-shadow:0 8px 20px -8px rgba(46,125,50,.6);">
                            <span wire:loading wire:target="submit">
                                <i aria-hidden="true" class="fas fa-spinner fa-spin text-xs"></i> Submitting...
                            </span>
                            <span wire:loading.remove wire:target="submit">
                                <i aria-hidden="true" class="fas fa-paper-plane text-xs"></i> Submit Registration
                            </span>
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            @endif
        </div>
    </main>
</div>