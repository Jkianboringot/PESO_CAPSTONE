<div>
<h5 class="fw-bold mb-4" style="color:#1F4E79">&#9881; User Management</h5>

<div class="mb-3 text-end">
    <button wire:click="openCreate" class="btn text-white fw-semibold" style="background:#1F4E79">
        + Add New Staff User
    </button>
</div>

@if($showForm)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-bold py-3" style="background:#1F4E79; color:#fff">
        {{ $editingId ? 'Edit User Account' : 'Create New User Account' }}
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Full Name *</label>
                <input wire:model="name" class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Email Address *</label>
                <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Role *</label>
                <select wire:model="role_id" class="form-select @error('role_id') is-invalid @enderror">
                    <option value="">Select Role</option>
                    @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                </select>
                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Password {{ $editingId ? '(leave blank to keep current)' : '*' }}</label>
                <input type="password" wire:model="password" class="form-control @error('password') is-invalid @enderror"
                       placeholder="{{ $editingId ? 'Leave blank to keep current' : 'Min. 8 characters' }}">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 py-3">
        <button wire:click="save" class="btn text-white fw-semibold" style="background:#1F4E79">
            {{ $editingId ? 'Update User' : 'Create User' }}
        </button>
        <button wire:click="$set('showForm', false)" class="btn btn-outline-secondary">Cancel</button>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <thead style="background:#1F4E79; color:#fff">
                    <tr>
                        <th class="py-3 ps-3">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="ps-3 py-2 fw-semibold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge" style="background:#2E74B5">{{ $user->role?->name }}</span></td>
                        <td>
                            @if($user->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}</td>
                        <td>
                            <button wire:click="openEdit({{ $user->id }})" class="btn btn-sm btn-outline-primary me-1">Edit</button>
                            @if($user->is_active && $user->id !== auth()->id())
                            <button wire:click="deactivate({{ $user->id }})"
                                    onclick="return confirm('Deactivate this user?')"
                                    class="btn btn-sm btn-outline-danger">Deactivate</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
    <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
</div>
