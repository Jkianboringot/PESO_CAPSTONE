<div class="row justify-content-center">
<div class="col-lg-8">

@if($submitted)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div class="display-4 mb-3" style="color:#70AD47">&#10003;</div>
            <h2 class="fw-bold" style="color:#1F4E79">Registration Successful!</h2>
            <p class="lead">Your Reference ID:</p>
            <code class="fs-4 bg-light px-3 py-2 rounded d-inline-block">{{ $reference_id }}</code>
            <p class="mt-3 text-muted">Please keep this ID for your records. You may present it when visiting the PESO office.</p>
            <a href="{{ route('welcome') }}" class="btn btn-outline-secondary mt-2">Back to Home</a>
        </div>
    </div>
@else
<div class="card border-0 shadow-sm">
    <div class="card-header py-3" style="background:#1F4E79; color:#fff">
        <h5 class="mb-1">Online Skills Registration — Step {{ $step }} of {{ $totalSteps }}</h5>
        <div class="progress mt-2" style="height:6px">
            <div class="progress-bar bg-warning" style="width:{{ ($step / $totalSteps) * 100 }}%"></div>
        </div>
    </div>

    <div class="card-body p-4">

        {{-- Step 1: Personal Information --}}
        @if($step === 1)
        <h6 class="fw-bold mb-3">Personal Information</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Last Name *</label>
                <input wire:model.live="last_name" class="form-control @error('last_name') is-invalid @enderror">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">First Name *</label>
                <input wire:model.live="first_name" class="form-control @error('first_name') is-invalid @enderror">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input wire:model="middle_name" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Birthdate *</label>
                <input type="date" wire:model="birthdate" class="form-control @error('birthdate') is-invalid @enderror">
                @error('birthdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Sex *</label>
                <select wire:model="sex" class="form-select @error('sex') is-invalid @enderror">
                    <option value="">Select</option>
                    <option>Male</option><option>Female</option><option>Prefer not to say</option>
                </select>
                @error('sex')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil Status *</label>
                <select wire:model="civil_status" class="form-select @error('civil_status') is-invalid @enderror">
                    <option value="">Select</option>
                    <option>Single</option><option>Married</option>
                    <option>Widowed</option><option>Separated</option>
                </select>
                @error('civil_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Contact Number *</label>
                <input wire:model.live="contact_number" class="form-control @error('contact_number') is-invalid @enderror" placeholder="09XXXXXXXXX">
                @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Email (optional)</label>
                <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        @endif

        {{-- Step 2: Address & Location --}}
        @if($step === 2)
        <h6 class="fw-bold mb-3">Address & Location</h6>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Street / Sitio Address *</label>
                <input wire:model="address" class="form-control @error('address') is-invalid @enderror">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Municipality *</label>
                <select wire:model.live="municipality_id" class="form-select @error('municipality_id') is-invalid @enderror">
                    <option value="">Select Municipality</option>
                    @foreach($municipalities as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('municipality_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Barangay *</label>
                <select wire:model="barangay_id" class="form-select @error('barangay_id') is-invalid @enderror"
                    {{ empty($barangays) ? 'disabled' : '' }}>
                    <option value="">Select Barangay</option>
                    @foreach($barangays as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('barangay_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        @endif

        {{-- Step 3: Education --}}
        @if($step === 3)
        <h6 class="fw-bold mb-3">Educational Background</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Highest Level Completed *</label>
                <select wire:model="highest_level" class="form-select @error('highest_level') is-invalid @enderror">
                    <option value="">Select</option>
                    @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $level)
                        <option>{{ $level }}</option>
                    @endforeach
                </select>
                @error('highest_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Course / Program</label>
                <input wire:model="course_program" class="form-control" placeholder="e.g. BS Information Systems">
            </div>
            <div class="col-md-6">
                <label class="form-label">School / University</label>
                <input wire:model="school_name" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Year Graduated</label>
                <input type="number" wire:model="year_graduated" class="form-control"
                       min="1950" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}">
            </div>
        </div>
        @endif

        {{-- Step 4: Skills --}}
        @if($step === 4)
        <h6 class="fw-bold mb-3">Workforce Skills Profile</h6>
        <p class="text-muted small">Select all skills that apply. You may select multiple skills across categories.</p>
        @error('selected_skills')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror
        @foreach($skillCategories as $category)
        <div class="mb-4">
            <h6 class="text-secondary small fw-bold text-uppercase border-bottom pb-1">{{ $category->name }}</h6>
            <div class="row g-2 mt-1">
                @foreach($category->skills as $skill)
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               wire:model.live="selected_skills"
                               value="{{ $skill->id }}" id="skill_{{ $skill->id }}">
                        <label class="form-check-label small" for="skill_{{ $skill->id }}">
                            {{ $skill->name }}
                        </label>
                    </div>
                    @if(in_array($skill->id, $selected_skills))
                    <select wire:model="skill_proficiencies.{{ $skill->id }}"
                            class="form-select form-select-sm mt-1">
                        @foreach($proficiencyLevels as $pl)
                            <option>{{ $pl }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endif

        {{-- Step 5: Consent --}}
        @if($step === 5)
        <h6 class="fw-bold mb-3">Data Privacy Consent (RA 10173)</h6>
        <div class="p-3 bg-light rounded mb-3 small">
            The information you provide will be collected, stored, and used by the
            Public Employment Service Office of Catanduanes Province solely for
            workforce planning, employment referral, and compliance reporting to the
            Department of Labor and Employment (DOLE). Your data will be protected
            in accordance with Republic Act No. 10173 (Data Privacy Act of 2012).
            You may request access to or correction of your records at any time.
        </div>
        <div class="form-check @error('consent_given') is-invalid @enderror">
            <input class="form-check-input" type="checkbox" wire:model="consent_given" id="consent">
            <label class="form-check-label" for="consent">
                <strong>I have read and understood the above statement and give my consent
                for the collection and processing of my personal data.</strong>
            </label>
        </div>
        @error('consent_given')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @endif

    </div>

    <div class="card-footer d-flex justify-content-between py-3">
        @if($step > 1)
            <button wire:click="prevStep" class="btn btn-outline-secondary">&larr; Previous</button>
        @else
            <span></span>
        @endif

        @if($step < $totalSteps)
            <button wire:click="nextStep" class="btn text-white fw-semibold" style="background:#1F4E79">
                Next &rarr;
            </button>
        @else
            <button wire:click="submit" class="btn btn-success fw-semibold">
                <span wire:loading wire:target="submit">Submitting...</span>
                <span wire:loading.remove wire:target="submit">Submit Registration</span>
            </button>
        @endif
    </div>
</div>
@endif

</div>
</div>
