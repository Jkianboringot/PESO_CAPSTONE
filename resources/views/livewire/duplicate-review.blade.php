{{-- resources/views/livewire/duplicates.blade.php --}}
<div>
    <x-slot name="title">Duplicate Review</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-copy mr-2" style="color: #ca8a04;"></i>
                Duplicate Review Queue
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Review and resolve flagged duplicate registrations</p>
        </div>
    </div>

    @if(!$activeFlag)
    {{-- Queue Table --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
        <div class="px-5 py-3" style="background: #1a2035;">
            <span class="text-sm font-semibold text-white">
                <i class="fas fa-flag mr-2 opacity-70"></i>Pending Flags
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">New Applicant</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Existing Applicant</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Match Criteria</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Score</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Flagged</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: #f1f5f9;">
                    @forelse($flags as $flag)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-medium" style="color: #1e293b;">{{ $flag->newApplicant?->full_name ?? '—' }}</div>
                            <code class="text-xs px-1 py-0.5 rounded" style="background: #f1f5f9; color: #64748b;">{{ $flag->newApplicant?->reference_id }}</code>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium" style="color: #1e293b;">{{ $flag->existingApplicant?->full_name ?? '—' }}</div>
                            <code class="text-xs px-1 py-0.5 rounded" style="background: #f1f5f9; color: #64748b;">{{ $flag->existingApplicant?->reference_id }}</code>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if($flag->matched_phonetic)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background: #fef9c3; color: #a16207;">Name</span>
                                @endif
                                @if($flag->matched_birthdate)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background: #e0f2fe; color: #0369a1;">Birthdate</span>
                                @endif
                                @if($flag->matched_contact)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background: #f1f5f9; color: #475569;">Contact</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background: #fee2e2; color: #dc2626;">{{ $flag->match_score }}/3</span>
                        </td>
                        <td class="px-4 py-3" style="color: #475569;">{{ $flag->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <button wire:click="openFlag({{ ($flag->id) }})"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200"
                                    style="color: #2563eb; border-color: #bfdbfe;">
                                Review
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <i class="fas fa-check-circle text-3xl mb-3 block" style="color: #86efac;"></i>
                            <p class="text-sm font-medium" style="color: #374151;">No pending duplicate flags.</p>
                            <p class="text-xs" style="color: #94a3b8;">All clear! The queue is empty.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($flags->hasPages())
        <div class="px-5 py-3 border-t" style="border-color: #e2e8f0; background: #f8fafc;">
            {{ $flags->links() }}
        </div>
        @endif
    </div>

    @else
    {{-- Side-by-side comparison view 
    <div class="mb-4">
        <button wire:click="$set('activeFlag', null)"
                class="text-xs font-medium px-4 py-2 rounded-lg border transition-colors hover:bg-slate-50"
                style="color: #64748b; border-color: #d1d5db;">
            <i class="fas fa-arrow-left mr-1.5"></i> Back to Queue
        </button>
    </div>
    --}}

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
        @foreach([['NEW (Incoming)', $activeFlag->newApplicant, '#fee2e2', '#dc2626'], ['EXISTING (On Record)', $activeFlag->existingApplicant, '#eff6ff', '#2563eb']] as [$label, $applicant, $headerBg, $headerColor])
        <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
            <div class="px-4 py-3 text-sm font-bold" style="background: {{ $headerBg }}; color: {{ $headerColor }};">
                {{ $label }}
            </div>
            <div class="p-4">
                <table class="w-full text-xs">
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold w-28" style="color: #64748b;">Reference ID</th><td class="py-2"><code class="px-1.5 py-0.5 rounded font-mono" style="background: #f1f5f9; color: #1F4E79;">{{ $applicant?->reference_id }}</code></td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Name</th><td class="py-2 font-medium" style="color: #1e293b;">{{ $applicant?->full_name }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Birthdate</th><td class="py-2" style="color: #475569;">{{ $applicant?->birthdate?->format('M d, Y') }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Sex</th><td class="py-2" style="color: #475569;">{{ $applicant?->sex }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Contact</th><td class="py-2" style="color: #475569;">{{ $applicant?->contact_number }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Email</th><td class="py-2" style="color: #475569;">{{ $applicant?->email ?? '—' }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Barangay</th><td class="py-2" style="color: #475569;">{{ $applicant?->barangay?->name }}</td></tr>
                    <tr class="border-b" style="border-color: #f1f5f9;"><th class="py-2 pr-3 text-left font-semibold" style="color: #64748b;">Education</th><td class="py-2" style="color: #475569;">{{ $applicant?->education?->highest_level ?? '—' }}</td></tr>
                    <tr><th class="py-2 pr-3 text-left font-semibold align-top" style="color: #64748b;">Skills</th><td class="py-2">
                        <div class="flex flex-wrap gap-1">
                            @foreach($applicant?->skills ?? [] as $s)
                                <span class="px-2 py-0.5 rounded text-xs" style="background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0;">{{ $s->name }}</span>
                            @endforeach
                        </div>
                    </td></tr>
                </table>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Resolution Panel --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
        <div class="px-5 py-3" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <span class="text-sm font-bold" style="color: #1e293b;">Resolve This Flag</span>
        </div>
        <div class="p-5">
            <div class="mb-4">
                <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Resolution Notes (optional)</label>
                <textarea wire:model="resolutionNotes"
                          class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                          style="border-color: #d1d5db;"
                          rows="2"
                          placeholder="Add any notes about your decision..."></textarea>
            </div>
            <div class="flex flex-wrap gap-3">
                <button wire:click="resolve('Merged')"
                        onclick="return confirm('Merge: the incoming record will be deactivated. Proceed?')"
                        class="text-xs font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                        style="background: #ca8a04;">
                    <i class="fas fa-sync-alt mr-1.5"></i> Merge (Keep Existing, Deactivate New)
                </button>
                <button wire:click="resolve('Retained Both')"
                        class="text-xs font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                        style="background: #16a34a;">
                    <i class="fas fa-check mr-1.5"></i> Retain Both (Not a Duplicate)
                </button>
                <button wire:click="resolve('Deleted')"
                        onclick="return confirm('Delete: the incoming record will be deactivated. Proceed?')"
                        class="text-xs font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                        style="background: #dc2626;">
                    <i class="fas fa-trash mr-1.5"></i> Delete New Record
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="text-center mt-6">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>
</div>