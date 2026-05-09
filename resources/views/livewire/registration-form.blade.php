<div class="min-h-screen flex flex-col lg:flex-row">

    {{-- ═══════════════════════════════════════════════════
         LEFT PANEL — Branding + Info
    ════════════════════════════════════════════════════ --}}
    <div class="hero-pattern lg:w-2/5 xl:w-1/3 flex flex-col justify-between px-10 py-12 text-white hidden lg:flex">

        {{-- Logo / Office --}}
        <div>
            <div class="flex items-center gap-3 mb-10">
                <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-briefcase text-white text-lg"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest opacity-60">Catanduanes Province</p>
                    <p class="text-sm font-bold leading-tight">PESO Skills Registry</p>
                </div>
            </div>

            <h2 class="text-3xl font-bold leading-snug mb-4">
                Register your skills.<br>
                <span class="opacity-60">Find better opportunities.</span>
            </h2>
            <p class="text-sm opacity-70 leading-relaxed mb-10">
                Join the Public Employment Service Office workforce database. 
                Your profile helps connect you with local employers and livelihood programs.
            </p>

            {{-- Benefits --}}
            <ul class="space-y-4">
                @foreach([
                    ['fas fa-shield-halved',  'Data Privacy Protected',     'Your information is secured under RA 10173'],
                    ['fas fa-magnifying-glass','Employment Referrals',       'Get matched with local job openings'],
                    ['fas fa-graduation-cap', 'Livelihood Programs',         'Access DOLE and TESDA training opportunities'],
                    ['fas fa-id-card',        'Official Reference ID',       'Track your registration status anytime'],
                ] as [$icon, $title, $desc])
                <li class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <i class="{{ $icon }} text-xs text-white/80"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">{{ $title }}</p>
                        <p class="text-xs opacity-55 leading-snug">{{ $desc }}</p>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Footer --}}
        <p class="text-xs opacity-40 mt-10">© {{ date('Y') }} PESO Catanduanes. All rights reserved.</p>
    </div>

    {{-- ═══════════════════════════════════════════════════
         RIGHT PANEL — Form
    ════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex items-start justify-center lg:overflow-y-auto py-10 px-4 sm:px-8">
        <div class="w-full max-w-2xl">

            {{-- ── SUCCESS STATE ─────────────────────────────── --}}
            @if($submitted)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
                <div class="w-20 h-20 rounded-full mx-auto mb-6 flex items-center justify-center"
                     style="background:#f0fdf4;">
                    <i class="fas fa-circle-check text-4xl" style="color:#2e7d32;"></i>
                </div>
                <h2 class="text-2xl font-bold mb-1" style="color:#1e293b;">Registration Successful!</h2>
                <p class="text-sm mb-5" style="color:#64748b;">Your profile has been submitted to the PESO office.</p>
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#94a3b8;">Your Reference ID</p>
                <div class="inline-block px-6 py-3 rounded-xl text-xl font-mono font-bold tracking-wider mb-6"
                     style="background:#eff6ff; color:#1F4E79;">
                    {{ $reference_id }}
                </div>
                <p class="text-xs max-w-xs mx-auto mb-8" style="color:#94a3b8;">
                    Keep this ID for your records. Present it when visiting the PESO office for referrals or inquiries.
                </p>
                
            </div>
            @else

            {{-- ── STEP PROGRESS ──────────────────────────────── --}}
            @php
                $stepLabels = ['Personal', 'Address', 'Education', 'Skills', 'Consent'];
                $stepIcons  = ['fas fa-user', 'fas fa-location-dot', 'fas fa-graduation-cap', 'fas fa-wrench', 'fas fa-file-shield'];
            @endphp
            <div class="mb-8">
                <div class="flex items-start">
                    @foreach($stepLabels as $i => $label)
                        @php $n = $i + 1; @endphp
                        <div class="flex flex-col items-center flex-1">
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
                            <div class="flex-1 h-0.5 mt-4 mx-1 rounded transition-all duration-500"
                                 style="{{ $step > $n ? 'background:#1F4E79;' : 'background:#e2e8f0;' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- ── FORM CARD ──────────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100">

                {{-- Card header --}}
                <div class="px-7 pt-7 pb-5 border-b border-slate-50">
                    <h3 class="text-lg font-bold" style="color:#1e293b;">
                        {{ ['Personal Information', 'Address & Location', 'Educational Background', 'Skills Profile', 'Data Privacy Consent'][$step - 1] }}
                    </h3>
                    <p class="text-xs mt-0.5" style="color:#94a3b8;">Step {{ $step }} of {{ $totalSteps }}</p>
                </div>

                {{-- Card body --}}
                <div class="px-7 py-6 step-body">

                    {{-- ══ STEP 1: Personal ══════════════════════ --}}
                    @if($step === 1)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Last Name <span class="text-red-500">*</span></label>
                            <input wire:model.live="last_name"
                                   placeholder="e.g. Dela Cruz"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('last_name') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            @error('last_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">First Name <span class="text-red-500">*</span></label>
                            <input wire:model.live="first_name"
                                   placeholder="e.g. Juan"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('first_name') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            @error('first_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Middle Name <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                            <input wire:model="middle_name"
                                   placeholder="e.g. Santos"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" wire:model="birthdate"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('birthdate') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            @error('birthdate')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Sex <span class="text-red-500">*</span></label>
                            <select wire:model="sex"
                                    class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('sex') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Prefer not to say</option>
                            </select>
                            @error('sex')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Civil Status <span class="text-red-500">*</span></label>
                            <select wire:model="civil_status"
                                    class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('civil_status') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                                <option value="">Select</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                            </select>
                            @error('civil_status')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Contact Number <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                    <i class="fas fa-phone text-xs"></i>
                                </span>
                                <input wire:model.live="contact_number"
                                       placeholder="09XXXXXXXXX"
                                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm transition-all @error('contact_number') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            </div>
                            @error('contact_number')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Email Address <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                    <i class="fas fa-envelope text-xs"></i>
                                </span>
                                <input type="email" wire:model="email"
                                       placeholder="juandelacruz@email.com"
                                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm transition-all @error('email') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            </div>
                            @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 2: Address ════════════════════════ --}}
                    @if($step === 2)
                    <div class="space-y-5">

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Street / Sitio / Purok Address <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-slate-400"><i class="fas fa-map-pin text-xs"></i></span>
                                <input wire:model="address"
                                       placeholder="Blk 1 Lot 2, Purok Sampaguita"
                                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-lg border text-sm transition-all @error('address') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                            </div>
                            @error('address')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Municipality <span class="text-red-500">*</span></label>
                                <select wire:model.live="municipality_id"
                                        class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all @error('municipality_id') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                                    <option value="">Select Municipality</option>
                                    @foreach($municipalities as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('municipality_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Barangay <span class="text-red-500">*</span></label>
                                <select wire:model="barangay_id"
                                        {{ empty($barangays) ? 'disabled' : '' }}
                                        class="field-input w-full px-4 py-2.5 rounded-lg border text-sm transition-all
                                            {{ empty($barangays) ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : '' }}
                                            @error('barangay_id') border-red-400 bg-red-50 @else border-slate-200 @enderror">
                                    <option value="">{{ empty($barangays) ? 'Select municipality first' : 'Select Barangay' }}</option>
                                    @foreach($barangays as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('barangay_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- Map hint --}}
                        <div class="rounded-xl p-4 flex items-start gap-3" style="background:#eff6ff; border:1px solid #bfdbfe;">
                            <i class="fas fa-circle-info text-sm mt-0.5 flex-shrink-0" style="color:#1F4E79;"></i>
                            <p class="text-xs leading-relaxed" style="color:#1e40af;">
                                Make sure to select the correct barangay. This information is used to route employment referrals and livelihood programs to your locality.
                            </p>
                        </div>
                    </div>
                    @endif

                    {{-- ══ STEP 3: Education ══════════════════════ --}}
                    @if($step === 3)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Highest Educational Level <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $level)
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model="highest_level" value="{{ $level }}" class="peer sr-only">
                                    <div class="px-3 py-2.5 rounded-lg border text-xs font-medium text-center transition-all
                                                peer-checked:border-[#1F4E79] peer-checked:bg-[#eff6ff] peer-checked:text-[#1F4E79]
                                                border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-50">
                                        {{ $level }}
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @error('highest_level')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Course / Program <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                            <input wire:model="course_program"
                                   placeholder="e.g. BS Information Technology"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">Year Graduated <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                            <input type="number" wire:model="year_graduated"
                                   min="1950" max="{{ date('Y') + 1 }}"
                                   placeholder="{{ date('Y') }}"
                                   class="field-input w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm transition-all">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#64748b;">School / University <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-school text-xs"></i></span>
                                <input wire:model="school_name"
                                       placeholder="e.g. Catanduanes State University"
                                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm transition-all">
                            </div>
                        </div>

                    </div>
                    @endif

                    {{-- ══ STEP 4: Skills ═════════════════════════ --}}
                    @if($step === 4)
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-xs" style="color:#64748b;">Select all skills that apply, then rate your proficiency level.</p>
                            @if(count($selected_skills) > 0)
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:#eff6ff; color:#1F4E79;">
                                {{ count($selected_skills) }} selected
                            </span>
                            @endif
                        </div>

                        @error('selected_skills')
                        <div class="flex items-center gap-2 px-4 py-3 rounded-lg mb-4 text-sm" style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626;">
                            <i class="fas fa-triangle-exclamation text-xs"></i> {{ $message }}
                        </div>
                        @enderror

                        <div class="space-y-6">
                            @foreach($skillCategories as $category)
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-xs font-bold uppercase tracking-widest" style="color:#94a3b8;">{{ $category->name }}</span>
                                    <div class="flex-1 h-px" style="background:#f1f5f9;"></div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($category->skills as $skill)
                                    <div class="skill-card rounded-xl border p-3 transition-all
                                        {{ in_array($skill->id, $selected_skills) ? 'border-[#1F4E79] bg-[#eff6ff]' : 'border-slate-200 bg-white hover:border-slate-300' }}">

                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <div class="relative mt-0.5 flex-shrink-0">
                                                <input type="checkbox"
                                                       wire:model.live="selected_skills"
                                                       value="{{ $skill->id }}"
                                                       id="skill_{{ $skill->id }}"
                                                       class="sr-only peer">
                                                <div class="w-4 h-4 rounded border-2 flex items-center justify-center transition-all
                                                    {{ in_array($skill->id, $selected_skills)
                                                        ? 'border-[#1F4E79] bg-[#1F4E79]'
                                                        : 'border-slate-300 bg-white' }}">
                                                    @if(in_array($skill->id, $selected_skills))
                                                    <i class="fas fa-check text-[9px] text-white"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium leading-snug" style="color:#1e293b;">{{ $skill->name }}</span>
                                        </label>

                                        @if(in_array($skill->id, $selected_skills))
                                        <div class="mt-2 ml-7">
                                            <select wire:model="skill_proficiencies.{{ 1 }}"
                                                    class="w-full px-3 py-1.5 rounded-lg border border-[#1F4E79]/30 text-xs font-medium transition-all"
                                                    style="color:#1F4E79; background:#fff;">

                                                    <!-- value of select so anyseelcted one is teh value, i forgot how select work fuck thats why i dont understand
                                                     pretty much anything we select in option below will be send in backend as the 
                                                     [skill_proficiencies.skill_id then skill level]
                                                     //   9 => "Advanced"
                                                    //   10 => "Intermediate"
                                                    //   20 => "Advanced" -->
                                                @foreach($proficiencyLevels as $pl)
                                                    <option>{{ $pl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- ══ STEP 5: Consent ════════════════════════ --}}
                    @if($step === 5)
                    <div class="space-y-5">

                        <div class="rounded-xl p-5 text-sm leading-relaxed" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fas fa-file-shield" style="color:#1F4E79;"></i>
                                <span class="font-bold text-xs uppercase tracking-widest" style="color:#1F4E79;">Republic Act No. 10173 — Data Privacy Act of 2012</span>
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

                        {{-- Summary --}}
                        <div class="rounded-xl p-5" style="background:#eff6ff; border:1px solid #bfdbfe;">
                            <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#1F4E79;">Registration Summary</p>
                            <div class="grid grid-cols-2 gap-y-1.5 text-xs" style="color:#1e293b;">
                                <span class="font-semibold" style="color:#64748b;">Full Name</span>
                                <span>{{ trim("$first_name $middle_name $last_name") ?: '—' }}</span>
                                <span class="font-semibold" style="color:#64748b;">Education</span>
                                <span>{{ $highest_level ?: '—' }}</span>
                                <span class="font-semibold" style="color:#64748b;">Skills Selected</span>
                                <span>{{ count($selected_skills) }} skill(s)</span>
                            </div>
                        </div>

                        {{-- Consent checkbox --}}
                       <div class="flex items-start gap-4 cursor-pointer" wire:click="$toggle('consent_given')">
    <div class="flex-shrink-0 mt-0.5 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
         style="{{ $consent_given ? 'background:#1F4E79; border-color:#1F4E79;' : 'border-color:#cbd5e1;' }}">
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

                {{-- Card footer / navigation --}}
                <div class="px-7 py-5 border-t border-slate-50 flex items-center justify-between">

                    @if($step > 1)
                    <button wire:click="prevStep"
                            class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-lg border transition-all"
                            style="border-color:#e2e8f0; color:#64748b;">
                        <i class="fas fa-arrow-left text-xs"></i> Previous
                    </button>
                    @else
                    <span></span>
                    @endif

                    @if($step < $totalSteps)
                    <button wire:click="nextStep"
                            class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-lg text-white transition-all hover:opacity-90 active:scale-95"
                            style="background:#1F4E79;">
                        Continue <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    @else
          <form wire:submit.prevent="submit" class="bg-white rounded-2xl shadow-sm border border-slate-100">

                    <button 
                            class="flex items-center gap-2 text-sm font-bold px-6 py-2.5 rounded-lg text-white transition-all hover:opacity-90 active:scale-95"
                            style="background:#2e7d32;">
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