{{--
    resources/views/livewire/partials/review-summary.blade.php

    Shows everything the applicant filled in, grouped by step.
    Each card has an "Edit" button that jumps back to that step (goToStep).
    Used on the last step via: @include('livewire.partials.review-summary')
--}}
@php
    // IDs are stored, but we want to show names
    $municipalityName = $municipality_id ? ($municipalities[$municipality_id] ?? null) : null;
    $barangayName     = $barangay_id ? ($barangays[$barangay_id] ?? null) : null;

    // Selected skills with their names (selected_skills only holds IDs)
    $chosenSkills = $skillCategories->pluck('skills')->flatten()->whereIn('id', $selected_skills);

    // Step number => card. The number is the step that "Edit" jumps to.
    $sections = [
        1 => [
            'title' => 'Personal Information',
            'icon'  => 'fas fa-user',
            'rows'  => [
                'Full Name'      => trim("$first_name $middle_name $last_name"),
                'Date of Birth'  => $birthdate ? \Carbon\Carbon::parse($birthdate)->format('F j, Y') : null,
                'Sex'            => $sex,
                'Civil Status'   => $civil_status,
                'Contact Number' => $contact_number,
                'Email'          => $email,
            ],
        ],
        2 => [
            'title' => 'Address & Location',
            'icon'  => 'fas fa-location-dot',
            'rows'  => [
                'Street / Sitio / Purok' => $address,
                'Municipality'           => $municipalityName,
                'Barangay'               => $barangayName,
            ],
        ],
        3 => [
            'title' => 'Educational Background',
            'icon'  => 'fas fa-graduation-cap',
            'rows'  => [
                'Highest Level'    => $highest_level,
                'Course / Program' => $course_program,
                'School'           => $school_name,
                'Year Graduated'   => $year_graduated,
            ],
        ],
    ];
@endphp

<div class="space-y-3">

    <div>
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#1F4E79;">Review your registration</p>
        <p class="text-xs mt-0.5" style="color:#64748b;">
            Please check that everything is correct. Click <strong>Edit</strong> on any section to change it.
        </p>
    </div>

    {{-- Steps 1–3: simple label/value cards --}}
    @foreach($sections as $stepNumber => $section)
    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
            <span class="text-xs font-bold uppercase tracking-widest" style="color:#1F4E79;">
                <i class="{{ $section['icon'] }} mr-1.5"></i>{{ $section['title'] }}
            </span>
            <button type="button"
                    wire:click="goToStep({{ $stepNumber }})"
                    class="text-xs font-semibold hover:underline" style="color:#1F4E79;">
                <i class="fas fa-pen text-[10px]"></i> Edit
            </button>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 px-4 py-3 text-xs">
            @foreach($section['rows'] as $label => $value)
            <div>
                <dt class="font-semibold" style="color:#64748b;">{{ $label }}</dt>
                <dd style="color:#1e293b;">{{ filled($value) ? $value : '—' }}</dd>
            </div>
            @endforeach
        </dl>
    </div>
    @endforeach

    {{-- Step 4: skills with their proficiency --}}
    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
            <span class="text-xs font-bold uppercase tracking-widest" style="color:#1F4E79;">
                <i class="fas fa-wrench mr-1.5"></i>Skills Profile
                <span class="font-normal normal-case" style="color:#64748b;">({{ $chosenSkills->count() }})</span>
            </span>
            <button type="button"
                    wire:click="goToStep(4)"
                    class="text-xs font-semibold hover:underline" style="color:#1F4E79;">
                <i class="fas fa-pen text-[10px]"></i> Edit
            </button>
        </div>

        <div class="px-4 py-3 flex flex-wrap gap-2">
            @forelse($chosenSkills as $skill)
                <span class="inline-flex items-center gap-1.5 text-xs rounded-full px-3 py-1"
                      style="background:#eff6ff; color:#1F4E79;">
                    <span class="font-medium">{{ $skill->name }}</span>
                    <span class="opacity-60">· {{ $skill_proficiencies[$skill->id] ?? 'Beginner' }}</span>
                </span>
            @empty
                <span class="text-xs" style="color:#94a3b8;">No skills selected.</span>
            @endforelse
        </div>
    </div>

</div>