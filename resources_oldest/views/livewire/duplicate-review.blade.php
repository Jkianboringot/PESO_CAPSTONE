<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#9888; Duplicate Review Queue</h5>

@if(!$activeFlag)
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead style="background:#1F4E79; color:#fff">
                    <tr>
                        <th class="py-3 ps-3">New Applicant</th>
                        <th>Existing Applicant</th>
                        <th>Match Criteria</th>
                        <th>Score</th>
                        <th>Flagged</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags as $flag)
                    <tr>
                        <td class="ps-3 py-2">
                            {{ $flag->newApplicant?->full_name ?? '—' }}<br>
                            <small class="text-muted">{{ $flag->newApplicant?->reference_id }}</small>
                        </td>
                        <td>
                            {{ $flag->existingApplicant?->full_name ?? '—' }}<br>
                            <small class="text-muted">{{ $flag->existingApplicant?->reference_id }}</small>
                        </td>
                        <td>
                            @if($flag->matched_phonetic)  <span class="badge bg-warning text-dark me-1">Name</span>@endif
                            @if($flag->matched_birthdate) <span class="badge bg-info text-dark me-1">Birthdate</span>@endif
                            @if($flag->matched_contact)   <span class="badge bg-secondary me-1">Contact</span>@endif
                        </td>
                        <td><span class="badge bg-danger">{{ $flag->match_score }}/3</span></td>
                        <td>{{ $flag->created_at->format('M d, Y') }}</td>
                        <td>
                            <button wire:click="openFlag({{ $flag->id }})" class="btn btn-sm btn-outline-primary">Review</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <div class="fs-4 mb-2">&#10003;</div>
                        No pending duplicate flags. All clear!
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($flags->hasPages())
    <div class="card-footer">{{ $flags->links() }}</div>
    @endif
</div>

@else
{{-- Side-by-side comparison view --}}
<div class="mb-3">
    <button wire:click="$set('activeFlag', null)" class="btn btn-outline-secondary btn-sm">&larr; Back to Queue</button>
</div>

<div class="row g-4 mb-4">
    @foreach([['NEW (Incoming)', $activeFlag->newApplicant, 'danger'], ['EXISTING (On Record)', $activeFlag->existingApplicant, 'primary']] as [$label, $applicant, $color])
    <div class="col-md-6">
        <div class="card border-{{ $color }} shadow-sm">
            <div class="card-header bg-{{ $color }} text-white fw-bold">{{ $label }}</div>
            <div class="card-body small">
                <table class="table table-sm mb-0">
                    <tr><th>Reference ID</th><td><code>{{ $applicant?->reference_id }}</code></td></tr>
                    <tr><th>Name</th><td>{{ $applicant?->full_name }}</td></tr>
                    <tr><th>Birthdate</th><td>{{ $applicant?->birthdate?->format('M d, Y') }}</td></tr>
                    <tr><th>Sex</th><td>{{ $applicant?->sex }}</td></tr>
                    <tr><th>Contact</th><td>{{ $applicant?->contact_number }}</td></tr>
                    <tr><th>Email</th><td>{{ $applicant?->email ?? '—' }}</td></tr>
                    <tr><th>Barangay</th><td>{{ $applicant?->barangay?->name }}</td></tr>
                    <tr><th>Education</th><td>{{ $applicant?->education?->highest_level ?? '—' }}</td></tr>
                    <tr><th>Skills</th><td>
                        @foreach($applicant?->skills ?? [] as $s)
                            <span class="badge bg-light text-dark border me-1">{{ $s->name }}</span>
                        @endforeach
                    </td></tr>
                    <tr><th>Registered</th><td>{{ $applicant?->created_at?->format('M d, Y h:i A') }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-bold" style="background:#f8f9fa">Resolve This Flag</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-semibold">Resolution Notes (optional)</label>
            <textarea wire:model="resolutionNotes" class="form-control" rows="2"
                      placeholder="Add any notes about your decision..."></textarea>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button wire:click="resolve('Merged')"
                    onclick="return confirm('Merge: the incoming record will be deactivated. Proceed?')"
                    class="btn btn-warning fw-semibold">
                &#128257; Merge (Keep Existing, Deactivate New)
            </button>
            <button wire:click="resolve('Retained Both')"
                    class="btn btn-success fw-semibold">
                &#10003; Retain Both (Not a Duplicate)
            </button>
            <button wire:click="resolve('Deleted')"
                    onclick="return confirm('Delete: the incoming record will be deactivated. Proceed?')"
                    class="btn btn-danger fw-semibold">
                &#128465; Delete New Record
            </button>
        </div>
    </div>
</div>
@endif

</div>
