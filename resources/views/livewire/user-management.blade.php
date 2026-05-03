<div>
    <x-slot name="title">User Management</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-user-cog mr-2" style="color: #2563eb;"></i>
                User Management
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Manage staff accounts and access roles</p>
        </div>
        <button wire:click="openCreate"
                class="text-xs font-semibold px-4 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                style="background: #1a2035;">
            <i class="fas fa-plus mr-1.5"></i> Add New Staff User
        </button>
    </div>

    {{-- Create / Edit Form --}}
    @if($showForm)
    <div class="bg-white rounded-xl border overflow-hidden mb-5" style="border-color: #e2e8f0;">
        <div class="px-5 py-3" style="background: #1a2035;">
            <span class="text-sm font-bold text-white">
                <i class="fas fa-{{ $editingId ? 'edit' : 'user-plus' }} mr-2 opacity-70"></i>
                {{ $editingId ? 'Edit User Account' : 'Create New User Account' }}
            </span>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Full Name *</label>
                    <input wire:model="name"
                           class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-400 @enderror"
                           style="border-color: #d1d5db;">
                    @error('name')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Email Address *</label>
                    <input type="email" wire:model="email"
                           class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('email') border-red-400 @enderror"
                           style="border-color: #d1d5db;">
                    @error('email')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Role *</label>
                    <select wire:model="role_id"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('role_id') border-red-400 @enderror"
                            style="border-color: #d1d5db;">
                        <option value="">Select Role</option>
                        @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                    @error('role_id')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">
                        Password {{ $editingId ? '(leave blank to keep)' : '*' }}
                    </label>
                    <input type="password" wire:model="password"
                           class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('password') border-red-400 @enderror"
                           style="border-color: #d1d5db;"
                           placeholder="{{ $editingId ? 'Leave blank to keep current' : 'Min. 8 characters' }}">
                    @error('password')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3 px-5 py-4 border-t" style="border-color: #e2e8f0; background: #f8fafc;">
            <button wire:click="save"
                    class="text-xs font-semibold px-5 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
                    style="background: #1a2035;">
                <i class="fas fa-save mr-1.5"></i>
                {{ $editingId ? 'Update User' : 'Create User' }}
            </button>
            <button wire:click="$set('showForm', false)"
                    class="text-xs font-semibold px-4 py-2 rounded-lg border transition-colors hover:bg-slate-100"
                    style="color: #64748b; border-color: #d1d5db;">
                Cancel
            </button>
        </div>
    </div>
    @endif

    {{-- Users Table --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
        <div class="px-5 py-3" style="background: #1a2035;">
            <span class="text-sm font-semibold text-white">
                <i class="fas fa-users-cog mr-2 opacity-70"></i>Staff Accounts
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Name</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Email</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Role</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Status</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Last Login</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: #f1f5f9;">
                    @forelse($users as $user)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                                     style="background: #2563eb;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <span class="font-semibold" style="color: #1e293b;">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3" style="color: #475569;">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: #eff6ff; color: #1d4ed8;">
                                {{ $user->role?->name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($user->is_active)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: #dcfce7; color: #15803d;">Active</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: #f1f5f9; color: #64748b;">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3" style="color: #475569;">
                            {{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <button wire:click="openEdit({{ $user->id }})"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200"
                                        style="color: #2563eb; border-color: #bfdbfe;">
                                    Edit
                                </button>
                                @if($user->is_active && $user->id !== auth()->id())
                                <button wire:click="deactivate({{ $user->id }})"
                                        onclick="return confirm('Deactivate this user?')"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-red-50 hover:text-red-700 hover:border-red-200"
                                        style="color: #dc2626; border-color: #fecaca;">
                                    Deactivate
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <i class="fas fa-users text-3xl mb-3 block opacity-20"></i>
                            <p class="text-xs" style="color: #94a3b8;">No users found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div class="px-5 py-3 border-t" style="border-color: #e2e8f0; background: #f8fafc;">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <div class="text-center mt-6">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>
</div>