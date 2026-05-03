<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#128101; Applicant Management</h5>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <input wire:model.live="search" class="form-control form-control-sm"
                   placeholder="&#128269; Search name or reference ID...">
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterStatus" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)<option>{{ $s }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterEdLevel" class="form-select form-select-sm">
                <option value="">All Education</option>
                @foreach($edLevels as $l)<option>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterCategory" class="form-select form-select-sm">
                <option value="">All Skill Categories</option>
                @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-1">
            <input type="date" wire:model.live="filterFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
            <input type="date" wire:model.live="filterTo" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
            <button wire:click="$set('search','');$set('filterStatus','');$set('filterEdLevel','');$set('filterCategory','');$set('filterFrom','');$set('filterTo','')"
                    class="btn btn-outline-secondary btn-sm w-100">Clear</button>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead style="background:#1F4E79; color:#fff">
                    <tr>
                        <th class="py-3 ps-3">Reference ID</th>
                        <th>Name</th>
                        <th>Barangay / Municipality</th>
                        <th>Education</th>
                        <th>Skills</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $a)
                    <tr>
                        <td class="ps-3 py-2"><code class="small">{{ $a->reference_id }}</code></td>
                        <td>{{ $a->full_name }}</td>
                        <td>{{ $a->barangay?->name }}, {{ $a->barangay?->municipality?->name }}</td>
                        <td>{{ $a->education?->highest_level ?? '—' }}</td>
                        <td>
                            @foreach($a->skills->take(3) as $skill)
                                <span class="badge bg-light text-dark border me-1">{{ $skill->name }}</span>
                            @endforeach
                            @if($a->skills->count() > 3)
                                <span class="text-muted small">+{{ $a->skills->count() - 3 }} more</span>
                            @endif
                        </td>
                        <td>
                            @php $badge = match($a->status) {
                                'Verified' => 'success', 'Flagged' => 'danger',
                                'Inactive' => 'secondary', default => 'warning'
                            }; @endphp
                            <span class="badge bg-{{ $badge }}">{{ $a->status }}</span>
                        </td>
                        <td>{{ $a->created_at->format('M d, Y') }}</td>
                        <td>
                            <button wire:click="openEdit({{ $a->id }})" class="btn btn-sm btn-outline-primary me-1">Edit</button>
                            <button wire:click="deactivate({{ $a->id }})"
                                    onclick="return confirm('Deactivate this applicant?')"
                                    class="btn btn-sm btn-outline-danger">Deactivate</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No applicants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">Showing {{ $applicants->firstItem() }}–{{ $applicants->lastItem() }} of {{ $applicants->total() }}</small>
        {{ $applicants->links() }}
    </div>
</div>

{{-- Edit Modal --}}
@if($showModal)
<div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,0.5)">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:#1F4E79; color:#fff">
                <h5 class="modal-title">Edit Applicant Record</h5>
                <button wire:click="$set('showModal',false)" class="btn-close btn-close-white"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Last Name *</label>
                        <input wire:model="editData.last_name" class="form-control @error('editData.last_name') is-invalid @enderror">
                        @error('editData.last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">First Name *</label>
                        <input wire:model="editData.first_name" class="form-control @error('editData.first_name') is-invalid @enderror">
                        @error('editData.first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input wire:model="editData.middle_name" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Number *</label>
                        <input wire:model="editData.contact_number" class="form-control @error('editData.contact_number') is-invalid @enderror">
                        @error('editData.contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input wire:model="editData.email" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status *</label>
                        <select wire:model="editData.status" class="form-select @error('editData.status') is-invalid @enderror">
                            <option>Pending</option><option>Verified</option>
                            <option>Flagged</option><option>Inactive</option>
                        </select>
                        @error('editData.status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input wire:model="editData.address" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('showModal',false)" class="btn btn-outline-secondary">Cancel</button>
                <button wire:click="saveEdit" class="btn text-white" style="background:#1F4E79">Save Changes</button>
            </div>
        </div>
    </div>
</div>
@endif

</div>
