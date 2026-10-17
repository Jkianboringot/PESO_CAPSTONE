<div class="min-h-screen flex flex-col lg:flex-row relative" style="background:linear-gradient(135deg,#f4f6f9 0%,#e3edf7 100%);">

    {{-- soft background blobs (decorative) --}}
    <div class="fixed -top-24 right-0 w-96 h-96 rounded-full pointer-events-none" style="background:#93c5fd; opacity:.30; filter:blur(80px);"></div>
    <div class="fixed -bottom-32 left-1/3 w-96 h-96 rounded-full pointer-events-none" style="background:#bbf7d0; opacity:.30; filter:blur(90px);"></div>

    {{-- ═══════════════════════════════════════════════════
         LEFT PANEL — Branding + Info (stays in view on desktop while the form scrolls)
    ════════════════════════════════════════════════════ --}}
    <div class="hero-pattern lg:w-72 xl:w-80 shrink-0 flex-col justify-between px-6 py-10 text-white hidden lg:flex lg:sticky lg:top-0 lg:h-screen lg:self-start lg:overflow-hidden">

        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full pointer-events-none" style="background:rgba(255,255,255,.07);"></div>
        <div class="absolute bottom-24 -left-20 w-72 h-72 rounded-full pointer-events-none" style="background:rgba(255,255,255,.05);"></div>

        <div class="relative">
            {{-- Logo / Office --}}
            <div class="flex items-center gap-3 mb-10">
                <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-briefcase text-white text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest opacity-60">Catanduanes Province</p>
                    <p class="text-sm font-bold leading-tight">PESO Skills Registry</p>
                </div>
            </div>

            <h2 class="text-3xl font-extrabold leading-snug mb-4">
                Register your skills.<br>
                <span class="opacity-60">Find better opportunities.</span>
            </h2>
            <p class="text-sm opacity-70 leading-relaxed mb-8">
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
                    <div class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
                        <i class="{{ $icon }} text-xs text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">{{ $title }}</p>
                        <p class="text-xs opacity-60 leading-snug">{{ $desc }}</p>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Footer --}}
        <div class="relative mt-8">
            <p class="text-xs opacity-40">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         RIGHT PANEL — Form
    ════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex items-start justify-center py-8 px-4 sm:px-8 lg:px-12 relative z-10">
        <div class="w-full max-w-2xl">

            {{-- Mobile-only header (left panel is hidden below lg) --}}
            <div class="lg:hidden flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#1F4E79;">
                    <i class="fas fa-briefcase text-white text-sm"></i>
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-bold" style="color:#1e293b;">PESO Skills Registry</p>
                    <p class="text-xs" style="color:#64748b;">Catanduanes Province</p>
                </div>
            </div>

            {{-- ── SUCCESS STATE ─────────────────────────────── --}}
            @if($submitted)
            <div class="relative overflow-hidden bg-white rounded-3xl border border-slate-100 p-10 sm:p-12 text-center"
                 style="box-shadow:0 20px 50px -20px rgba(31,78,121,.25);">
                <div class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
                     style="background:#dcfce7; box-shadow:0 0 0 10px #f0fdf4;">
                    <i class="fas fa-circle-check text-4xl" style="color:#2e7d32;"></i>
                </div>
                <h2 class="text-2xl font-extrabold mb-1" style="color:#1e293b;">Registration Successful!</h2>
                <p class="text-sm mb-6" style="color:#64748b;">Your profile has been submitted to the PESO office.</p>

                <p class="text-sm font-medium mb-2" style="color:#64748b;">Your Reference ID</p>
                <div class="inline-block px-6 py-3 rounded-2xl text-xl font-mono font-bold tracking-wider mb-6"
                     style="background:#eff6ff; color:#1F4E79; border:1px dashed #93c5fd;">
                    {{ $reference_id }}
                </div>
                <p class="text-sm max-w-sm mx-auto" style="color:#64748b;">
                    Keep this ID for your records. Present it when visiting the PESO office for referrals or inquiries.
                </p>
            </div>
            @else

            {{-- ── STEP PROGRESS ──────────────────────────────── --}}
            @php
                $stepLabels = ['Personal', 'Address', 'Education', 'Skills', 'Experience', 'Consent'];
                $stepIcons  = ['fas fa-user', 'fas fa-location-dot', 'fas fa-graduation-cap', 'fas fa-wrench', 'fas fa-briefcase', 'fas fa-file-shield'];
            @endphp
            <div class="mb-6 bg-white rounded-2xl border border-slate-100 px-5 pt-5 pb-4" style="box-shadow:0 10px 30px -18px rgba(31,78,121,.3);">
                <div class="flex items-start">
                    @foreach($stepLabels as $i => $label)
                        @php $n = $i + 1; @endphp
                        <div class="flex flex-col items-center">
                            <div class="relative flex flex-col items-center">
                                {{-- circle --}}
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                                    {{ $step > $n ? 'text-white' : ($step === $n ? 'text-white' : 'bg-white border-2 border-slate-200 text-slate-400') }}"
                                     style="{{ $step > $n ? 'background:#1F4E79;' : ($step === $n ? 'background:#1F4E79; box-shadow:0 0 0 4px rgba(31,78,121,0.15);' : '') }}">
                                    @if($step > $n)
                                        <i class="fas fa-check text-xs"></i>
                                    @else
                                        <i class="{{ $stepIcons[$i] }} text-xs"></i>
                                    @endif
                                </div>
                                {{-- label --}}
                                <p class="text-xs mt-1.5 font-medium hidden sm:block
                                    {{ $step === $n ? 'font-bold' : 'text-slate-400' }}"
                                   style="{{ $step === $n ? 'color:#1F4E79;' : '' }}">
                                    {{ $label }}
                                </p>
                            </div>
                        </div>
                        {{-- connector --}}
                        @if($n < $totalSteps)
                            <div class="flex-1 h-0.5 mt-[17px] mx-1.5 rounded transition-all duration-500"
                                 style="{{ $step > $n ? 'background:#1F4E79;' : 'background:#e2e8f0;' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- ── FORM CARD ──────────────────────────────────── --}}
            <div class="bg-white rounded-3xl border border-slate-100"
                 style="box-shadow:0 20px 50px -20px rgba(31,78,121,.25);">

                {{-- Card header --}}
                <div class="px-7 pt-6 pb-5 border-b border-slate-100 flex items-center gap-4 rounded-t-3xl"
                     style="background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                         style="background:#e0ecf7; color:#1F4E79;">
                        <i class="{{ $stepIcons[$step - 1] }}"></i>
                    </div>
                    <div class="min-w-0">
                        {{-- PREVIEW: tell the applicant they are in edit mode --}}
                        @if($returnToReview)
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-semibold mb-1.5"
                                  style="background:#fef3c7; color:#92400e;">
                                <i class="fas fa-pen text-[9px]"></i> Editing from review — save to go back
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold mb-1.5"
                                  style="background:#e0ecf7; color:#1F4E79;">
                                Step {{ $step }} of {{ $totalSteps }}
                            </span>
                        @endif
                        <h3 class="text-xl font-extrabold leading-tight" style="color:#1e293b;">
                            {{ ['Personal Information', 'Address & Location', 'Educational Background', 'Skills Profile', 'Experience & Training', 'Review & Consent'][$step - 1] }}
                        </h3>
                        <p class="text-sm mt-0.5" style="color:#64748b;">
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
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Last Name <span class="text-red-500">*</span></label>
                            <input wire:model.live="last_name"
                                   placeholder="e.g. Dela Cruz"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('last_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('last_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">First Name <span class="text-red-500">*</span></label>
                            <input wire:model.live="first_name"
                                   placeholder="e.g. Juan"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('first_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('first_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Middle Name <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input wire:model="middle_name"
                                   placeholder="e.g. Santos"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('middle_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('middle_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" wire:model="birthdate"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('birthdate') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('birthdate')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Sex <span class="text-red-500">*</span></label>
                            <select wire:model="sex"
                                    class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('sex') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Prefer not to say</option>
                            </select>
                            @error('sex')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Civil Status <span class="text-red-500">*</span></label>
                            <select wire:model="civil_status"
                                    class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('civil_status') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <option value="">Select</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                            </select>
                            @error('civil_status')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Contact Number <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                    <i class="fas fa-phone text-xs"></i>
                                </span>
                                <input wire:model.live="contact_number"
                                       type="number"
                                       placeholder="09XXXXXXXXX"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('contact_number') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('contact_number')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Email Address <span class="text-slate-400 font-normal">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                    <i class="fas fa-envelope text-xs"></i>
                                </span>
                                <input type="email" wire:model="email"
                                       placeholder="juandelacruz@email.com"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('email') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- group: other --}}
                        <div class="sm:col-span-2 flex items-center gap-3 pt-2">
                            <span class="text-sm font-semibold" style="color:#1F4E79;">Other details</span>
                            <div class="flex-1 h-px bg-slate-200"></div>
                        </div>

                        {{-- NEW: PWD — optional, multiple checkboxes --}}
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-0.5" style="color:#334155;">
                                Person with Disability (PWD) <span class="text-slate-400 font-normal">(optional)</span>
                            </label>
                            <p class="text-xs mb-2.5" style="color:#94a3b8;">Tick all that apply. Leave unchecked if none.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($disabilityOptions as $d)
                                <label class="flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm cursor-pointer transition-all
                                    {{ in_array($d, $disabilities) ? 'border-[#1F4E79] bg-[#dbeafe]' : 'border-slate-400 bg-slate-100 hover:border-[#1F4E79] hover:bg-[#e8f1fb]' }}"
                                    style="color:#1e293b;">
                                    <input type="checkbox" wire:model.live="disabilities" value="{{ $d }}"
                                           class="rounded border-slate-300" style="accent-color:#1F4E79;">
                                    <span>{{ $d }}</span>
                                </label>
                                @endforeach
                            </div>
                            @error('disabilities')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            @error('disabilities.*')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- group: account --}}
                        <div class="sm:col-span-2 flex items-center gap-3 pt-2">
                            <span class="text-sm font-semibold" style="color:#1F4E79;">Create your password</span>
                            <div class="flex-1 h-px bg-slate-200"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-lock text-xs"></i></span>
                                <input type="password" wire:model="password" autocomplete="new-password"
                                       placeholder="At least 8 characters"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition-all @error('password') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                <button type="button"
                                        onclick="const i = this.parentElement.querySelector('input'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.querySelector('i').className = 'fas text-xs ' + (show ? 'fa-eye-slash' : 'fa-eye');"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                        aria-label="Show or hide password">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                            @error('password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-lock text-xs"></i></span>
                                <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                                       placeholder="Re-enter password"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-10 py-2.5 rounded-xl border text-sm transition-all border-slate-400 bg-slate-100">
                                <button type="button"
                                        onclick="const i = this.parentElement.querySelector('input'); const show = i.type === 'password'; i.type = show ? 'text' : 'password'; this.querySelector('i').className = 'fas text-xs ' + (show ? 'fa-eye-slash' : 'fa-eye');"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                        aria-label="Show or hide password">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 2: Address ════════════════════════ --}}
                    @if($step === 2)
                    <div class="space-y-5">

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Street / Sitio / Purok Address <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-map-pin text-xs"></i></span>
                                <input wire:model="address"
                                       placeholder="Blk 1 Lot 2, Purok Sampaguita"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('address') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('address')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Municipality <span class="text-red-500">*</span></label>
                                <select wire:model.live="municipality_id"
                                        class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('municipality_id') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                    <option value="">Select Municipality</option>
                                    @foreach($municipalities as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('municipality_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Barangay <span class="text-red-500">*</span></label>
                                <select wire:model="barangay_id"
                                        {{ empty($barangays) ? 'disabled' : '' }}
                                        class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all
                                            {{ empty($barangays) ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : '' }}
                                            @error('barangay_id') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                    <option value="">{{ empty($barangays) ? 'Select municipality first' : 'Select Barangay' }}</option>
                                    @foreach($barangays as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('barangay_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- Map hint --}}
                        <div class="rounded-2xl p-4 flex items-start gap-3" style="background:#eff6ff; border:1px solid #bfdbfe;">
                            <i class="fas fa-circle-info text-sm mt-0.5 flex-shrink-0" style="color:#1F4E79;"></i>
                            <p class="text-sm leading-relaxed" style="color:#1e40af;">
                                Make sure to select the correct barangay. This information is used to route employment referrals and livelihood programs to your locality.
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- ══ STEP 3: Education ══════════════════════ --}}
                    @if($step === 3)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Highest Educational Level <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $level)
                                <label class="relative cursor-pointer">
                                    <input type="radio"
                                           name="highest_level"
                                           wire:model="highest_level"
                                           value="{{ $level }}"
                                           class="peer sr-only">
                                    <div class="px-3 py-3 rounded-xl border text-sm font-medium text-center transition-all
                                                peer-checked:border-[#1F4E79] peer-checked:bg-[#dbeafe] peer-checked:text-[#1F4E79]
                                                peer-focus-visible:ring-2 peer-focus-visible:ring-[#1F4E79]/40
                                                @error('highest_level') border-red-400 @else border-slate-400 bg-slate-100 @enderror
                                                text-slate-600 hover:border-[#1F4E79] hover:bg-[#e8f1fb]">
                                        {{ $level }}
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @error('highest_level')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Course / Program <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input wire:model="course_program"
                                   placeholder="e.g. BS Information Technology"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('course_program') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('course_program')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">Year Graduated <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input type="number" wire:model="year_graduated"
                                   min="1950" max="{{ date('Y') + 1 }}"
                                   placeholder="{{ date('Y') }}"
                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('year_graduated') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            @error('year_graduated')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1.5" style="color:#334155;">School / University <span class="text-slate-400 font-normal">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-school text-xs"></i></span>
                                <input wire:model="school_name"
                                       placeholder="e.g. Catanduanes State University"
                                       class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm transition-all @error('school_name') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                            </div>
                            @error('school_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 4: Skills ═════════════════════════ --}}
                    @if($step === 4)
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <p class="text-sm" style="color:#64748b;">Select all skills that apply, then rate your proficiency level.</p>
                            @if(count($selected_skills) > 0)
                            <span class="shrink-0 text-xs font-semibold px-3 py-1 rounded-full" style="background:#e0ecf7; color:#1F4E79;">
                                {{ count($selected_skills) }} selected
                            </span>
                            @endif
                        </div>

                        {{-- Covers both "pick at least one" and an invalid individual skill id --}}
                        @php
                            $skillsError = $errors->first('selected_skills') ?: $errors->first('selected_skills.*');
                        @endphp
                        @if($skillsError)
                        <div class="flex items-center gap-2 px-4 py-3 rounded-xl mb-4 text-sm" style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626;">
                            <i class="fas fa-triangle-exclamation text-xs"></i> {{ $skillsError }}
                        </div>
                        @endif

                        <div class="space-y-6">
                            @foreach($skillCategories as $category)
                            <div>
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="text-xs font-bold px-3 py-1 rounded-full text-white" style="background:#1F4E79;">{{ $category->name }}</span>
                                    <div class="flex-1 h-px" style="background:#cbd5e1;"></div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($category->skills as $skill)
                                    <div class="skill-card rounded-xl border p-3 transition-all
                                        {{ in_array($skill->id, $selected_skills) ? 'border-[#1F4E79] bg-[#dbeafe] shadow-sm ring-1 ring-[#1F4E79]/30' : 'border-slate-400 bg-slate-100 hover:border-[#1F4E79] hover:bg-[#e8f1fb] hover:shadow-md' }}">

                                        <label class="flex items-start gap-3 cursor-pointer w-full">
                                            <div class="relative mt-0.5 flex-shrink-0">
                                                <input type="checkbox"
                                                       wire:model.live="selected_skills"
                                                       value="{{ $skill->id }}"
                                                       id="skill_{{ $skill->id }}"
                                                       class="sr-only peer">
                                                <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all
                                                    peer-focus-visible:ring-2 peer-focus-visible:ring-[#1F4E79]/40
                                                    {{ in_array($skill->id, $selected_skills)
                                                        ? 'border-[#1F4E79] bg-[#1F4E79]'
                                                        : 'border-slate-500 bg-white' }}">
                                                    @if(in_array($skill->id, $selected_skills))
                                                    <i class="fas fa-check text-[10px] text-white"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium leading-snug" style="color:#1e293b;">{{ $skill->name }}</span>
                                        </label>

                                        @if(in_array($skill->id, $selected_skills))
                                        <div class="mt-2 ml-7">
                                            {{-- PREVIEW: was {{ 1 }} (every skill wrote to key 1). Now uses the skill id so the review shows the right level. --}}
                                            {{-- Whatever is picked here is sent to the backend as skill_proficiencies[skill_id] => level, e.g. 9 => "Advanced", 10 => "Intermediate" --}}
                                            <select wire:model="skill_proficiencies.{{ $skill->id }}"
                                                    class="w-full px-3 py-1.5 rounded-lg border bg-white text-xs font-semibold transition-all
                                                           @error('skill_proficiencies.' . $skill->id) border-red-400 bg-red-50 @else border-[#1F4E79]/60 @enderror"
                                                    style="color:#1F4E79;">
                                                @foreach($proficiencyLevels as $pl)
                                                    <option>{{ $pl }}</option>
                                                @endforeach
                                            </select>
                                            @error('skill_proficiencies.' . $skill->id)
                                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
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
                            <label class="block text-sm font-medium mb-0.5" style="color:#334155;">Other Skills <span class="text-slate-400 font-normal">(optional)</span></label>
                            <p class="text-xs mb-2.5" style="color:#94a3b8;">Have a skill that is not in the list above? Describe it here.</p>
                            <textarea wire:model="other_skills" rows="3"
                                      placeholder="e.g. Motorcycle repair, cake decorating"
                                      class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error('other_skills') border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror"></textarea>
                            @error('other_skills')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
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
                        <p class="text-sm" style="color:#64748b;">Everything on this page is optional. Add only what applies to you.</p>

                        @foreach($sections as $type => $sec)
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#1F4E79;">
                                    <i class="{{ $sec['icon'] }} text-xs text-white"></i>
                                </div>
                                <span class="text-sm font-bold" style="color:#1e293b;">{{ $sec['title'] }}</span>
                                <div class="flex-1 h-px" style="background:#cbd5e1;"></div>
                                <button type="button" wire:click="addRow('{{ $type }}')"
                                        class="flex items-center gap-1.5 text-xs font-semibold px-3.5 py-1.5 rounded-full text-white transition-all hover:-translate-y-0.5 active:scale-95"
                                        style="background:#1F4E79; box-shadow:0 6px 14px -6px rgba(31,78,121,.6);">
                                    <i class="fas fa-plus text-[10px]"></i> {{ $sec['add'] }}
                                </button>
                            </div>

                            @if(empty($this->{$type}))
                                <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white/70 px-4 py-4 text-center">
                                    <p class="text-sm" style="color:#64748b;">None added yet. Use the button above to add one.</p>
                                </div>
                            @endif

                            @foreach($this->{$type} as $i => $row)
                            <div class="rounded-2xl border border-slate-300 border-l-4 border-l-[#1F4E79] bg-white shadow-sm p-4 mb-3" wire:key="{{ $type }}-{{ $i }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach($sec['fields'] as [$key, $label, $placeholder, $kind, $required, $span])
                                    <div class="{{ $span }}">
                                        <label class="block text-sm font-medium mb-1.5" style="color:#334155;">
                                            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
                                        </label>
                                        @if($kind === 'textarea')
                                            <textarea wire:model="{{ $type }}.{{ $i }}.{{ $key }}" rows="2"
                                                      placeholder="{{ $placeholder }}"
                                                      class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error($type . '.' . $i . '.' . $key) border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror"></textarea>
                                        @else
                                            <input type="{{ $kind }}" wire:model="{{ $type }}.{{ $i }}.{{ $key }}"
                                                   placeholder="{{ $placeholder }}"
                                                   class="field-input focus:outline-none focus:ring-4 focus:ring-[#1F4E79]/10 focus:border-[#1F4E79] focus:bg-white w-full px-4 py-2.5 rounded-xl border text-sm transition-all @error($type . '.' . $i . '.' . $key) border-red-400 bg-red-50 @else border-slate-400 bg-slate-100 @enderror">
                                        @endif
                                        @error($type . '.' . $i . '.' . $key)<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    @endforeach
                                </div>
                                <div class="flex justify-end mt-3">
                                    <button type="button" wire:click="removeRow('{{ $type }}', {{ $i }})"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors hover:bg-red-50 hover:text-red-600" style="color:#64748b;">
                                        <i class="fas fa-trash-can mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
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
                                <i class="fas fa-file-signature" style="color:#1F4E79;"></i>
                                <span class="font-semibold text-sm" style="color:#1F4E79;">Applicant's Declaration</span>
                            </div>
                            <p>
                                I hereby certify that the information I provided is true and correct. I authorize
                                <strong>PESO Catanduanes Province</strong> to use my information for employment referral,
                                livelihood and training programs, and for reporting to the Department of Labor and Employment (DOLE).
                            </p>
                        </div>

                        <div class="flex items-start gap-4 cursor-pointer rounded-xl border p-4 transition-all"
                             style="{{ $oath_given ? 'border-color:#1F4E79; background:#dbeafe;' : ($errors->has('oath_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}"
                             wire:click="$toggle('oath_given')">
                            <div class="flex-shrink-0 mt-0.5 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                                 style="{{ $oath_given ? 'background:#1F4E79; border-color:#1F4E79;' : ($errors->has('oath_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}">
                                @if($oath_given)
                                    <i class="fas fa-check" style="font-size:10px; color:#ffffff;"></i>
                                @endif
                            </div>
                            <span class="text-sm leading-relaxed" style="color:#1e293b;">
                                <strong>I certify and authorize</strong> the above declaration.
                            </span>
                        </div>
                        @error('oath_given')
                        <p class="text-xs text-red-500 flex items-center gap-1.5 -mt-2">
                            <i class="fas fa-circle-exclamation"></i> {{ $message }}
                        </p>
                        @enderror

                        <div class="rounded-2xl p-5 text-sm leading-relaxed" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-file-shield" style="color:#1F4E79;"></i>
                                <span class="font-semibold text-sm" style="color:#1F4E79;">Republic Act No. 10173 — Data Privacy Act of 2012</span>
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

                        {{-- Consent checkbox --}}
                        <div class="flex items-start gap-4 cursor-pointer rounded-xl border p-4 transition-all"
                             style="{{ $consent_given ? 'border-color:#1F4E79; background:#dbeafe;' : ($errors->has('consent_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}"
                             wire:click="$toggle('consent_given')">
                            <div class="flex-shrink-0 mt-0.5 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                                 style="{{ $consent_given ? 'background:#1F4E79; border-color:#1F4E79;' : ($errors->has('consent_given') ? 'border-color:#f87171;' : 'border-color:#94a3b8; background:#f1f5f9;') }}">
                                @if($consent_given)
                                    <i class="fas fa-check" style="font-size:10px; color:#ffffff;"></i>
                                @endif
                            </div>
                            <span class="text-sm leading-relaxed" style="color:#1e293b;">
                                <strong>I have read and understood</strong> the above data privacy statement and I
                                <strong>voluntarily give my consent</strong> for the collection and processing of
                                my personal information by PESO Catanduanes Province.
                            </span>
                        </div>
                        @error('consent_given')
                        <p class="text-xs text-red-500 flex items-center gap-1.5 -mt-2">
                            <i class="fas fa-circle-exclamation"></i> {{ $message }}
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
                            class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border transition-all hover:bg-slate-50"
                            style="border-color:#e2e8f0; color:#64748b;">
                        <i class="fas fa-arrow-left text-xs"></i> Previous
                    </button>
                    @else
                    <span></span>
                    @endif

                    @if($step < $totalSteps)
                    <button type="button" wire:click="nextStep"
                            wire:loading.attr="disabled" wire:target="nextStep"
                            class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-xl text-white transition-all hover:-translate-y-0.5 active:scale-95 disabled:opacity-60"
                            style="background:linear-gradient(90deg,#1F4E79,#2a6199); box-shadow:0 8px 20px -8px rgba(31,78,121,.6);">
                        {{-- PREVIEW: label changes while editing from review --}}
                        {{ $returnToReview ? 'Save & back to review' : 'Continue' }} <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    @else
                    {{-- plain form: no card classes, otherwise it nests a second card inside the footer --}}
                    <form wire:submit.prevent="submit">
                        <button type="submit"
                                class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-xl text-white transition-all hover:-translate-y-0.5 active:scale-95"
                                style="background:#2e7d32; box-shadow:0 8px 20px -8px rgba(46,125,50,.6);">
                            <span wire:loading wire:target="submit">
                                <i class="fas fa-spinner fa-spin text-xs"></i> Submitting...
                            </span>
                            <span wire:loading.remove wire:target="submit">
                                <i class="fas fa-paper-plane text-xs"></i> Submit Registration
                            </span>
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            @endif
        </div>
    </div>
</div>