<div>
    <x-slot name="title">Applicant Management</x-slot>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-users mr-2" style="color: #2563eb;"></i>
                Applicant Management
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Search, filter, edit, and manage all registered applicants</p>
        </div>
    </div>

    {{-- ===================== FILTER BAR ===================== --}}
    <div class="bg-white rounded-xl border mb-5 p-4" style="border-color: #e2e8f0;">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3 items-end">

            <div class="lg:col-span-2">
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: #94a3b8;"></i>
                    <input wire:model.live="search"
                           class="w-full text-xs pl-8 pr-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                           style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;"
                           placeholder="Name or Reference ID...">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Status</label>
                <select wire:model.live="filterStatus"
                        class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $s)<option>{{ $s }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Education</label>
                <select wire:model.live="filterEdLevel"
                        class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Education</option>
                    @foreach($edLevels as $l)<option>{{ $l }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Skill Category</label>
                <select wire:model.live="filterCategory"
                        class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
                    <option value="">All Categories</option>
                    @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">From</label>
                <input type="date" wire:model.live="filterFrom"
                       class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                       style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
            </div>

            <div>
                <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">To</label>
                <input type="date" wire:model.live="filterTo"
                       class="w-full text-xs px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                       style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;">
            </div>

        </div>
        <!-- <div class="flex justify-end mt-3 pt-3 border-t gap-2" style="border-color: #f1f5f9;">
            <button wire:click="$set('search','');$set('filterStatus','');$set('filterEdLevel','');$set('filterCategory','');$set('filterFrom','');$set('filterTo','')"
                    class="text-xs font-semibold px-4 py-2 rounded-lg border transition-colors hover:bg-slate-50"
                    style="color: #64748b; border-color: #d1d5db;">
                <i class="fas fa-times mr-1"></i> Clear
            </button>
            <button wire:click="$refresh"
                    class="text-xs font-semibold px-5 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
                    style="background: #16a34a;">
                <i class="fas fa-filter mr-1.5"></i> Apply Filters
            </button>
        </div> -->
    </div>

    {{-- ===================== TABLE ===================== --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">

        {{-- Dark header bar --}}
        <div class="px-5 py-3 flex items-center justify-between" style="background: #1a2035;">
            <span class="text-sm font-semibold text-white">
                <i class="fas fa-table mr-2 opacity-70"></i>Applicant Records
            </span>
      
        </div>

        {{-- Column headers --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <!-- // OPTIMIZE - create a component for this if livewire is good enough for system, this too repeatitive -->
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Reference ID</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Name</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Barangay / Municipality</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Education</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Skills</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Status</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Registered</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: #f1f5f9;">
                    @forelse($applicants as $a)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <code class="text-xs px-1.5 py-0.5 rounded font-mono"
                                  style="background: #f1f5f9; color: #1F4E79;">{{ $a->reference_id }}</code>
                        </td>
                        <td class="px-4 py-3 font-medium" style="color: #1e293b;">{{ $a->full_name }}</td>
                        <td class="px-4 py-3" style="color: #475569;">
                            {{ $a->barangay?->name }}, {{ $a->barangay?->municipality?->name }}
                        </td>
                        <td class="px-4 py-3" style="color: #475569;">{{ $a->education?->highest_level ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($a->skills->take(3) as $skill)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                          style="background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0;">
                                        {{ $skill->name }}
                                    </span>
                                @endforeach
                                @if($a->skills->count() > 3)
                                    <span class="text-xs" style="color: #94a3b8;">+{{ $a->skills->count() - 3 }} more</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badgeStyles = match($a->status) {
                                    'Verified'  => 'background:#dcfce7; color:#15803d;',
                                    'Flagged'   => 'background:#fee2e2; color:#dc2626;',
                                    'Inactive'  => 'background:#f1f5f9; color:#64748b;',
                                    default     => 'background:#fef9c3; color:#a16207;',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                  style="{{ $badgeStyles }}">
                                {{ $a->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3" style="color: #475569;">{{ $a->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <button wire:click="openEdit({{ $a->id }})"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200"
                                        style="color: #2563eb; border-color: #bfdbfe;">
                                    Edit
                                </button>
                                <button wire:click="deactivate({{ $a->id }})"
                                        onclick="return confirm('Deactivate this applicant?')"
                                        class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors hover:bg-red-50 hover:text-red-700 hover:border-red-200"
                                        style="color: #dc2626; border-color: #fecaca;">
                                    Deactivate
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center" style="color: #94a3b8;">
                            <i class="fas fa-search text-3xl mb-3 block opacity-30"></i>
                            No applicants found matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-5 py-3 flex items-center justify-between border-t" style="border-color: #e2e8f0; background: #f8fafc;">
            <span class="text-xs" style="color: #64748b;">
                Showing {{ $applicants->firstItem() }}–{{ $applicants->lastItem() }} of {{ $applicants->total() }} applicants
            </span>
            <div class="text-xs">
                {{ $applicants->links() }}
            </div>
        </div>
    </div>

    {{-- Footer Note --}}
    <div class="text-center mt-6">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>

    {{-- ===================== EDIT MODAL ===================== --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4" style="background: #1a2035;">
                <h5 class="text-sm font-bold text-white">
                    <i class="fas fa-edit mr-2 opacity-70"></i> Edit Applicant Record
                </h5>
                <button wire:click="$set('showModal',false)"
                        class="text-white opacity-60 hover:opacity-100 transition-opacity">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Last Name *</label>
                        <input wire:model="editData.last_name"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                      @error('editData.last_name') border-red-400 @enderror"
                               style="border-color: #d1d5db;">
                        @error('editData.last_name')
                            <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">First Name *</label>
                        <input wire:model="editData.first_name"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                      @error('editData.first_name') border-red-400 @enderror"
                               style="border-color: #d1d5db;">
                        @error('editData.first_name')
                            <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Middle Name</label>
                        <input wire:model="editData.middle_name"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               style="border-color: #d1d5db;">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Contact Number *</label>
                        <input wire:model="editData.contact_number"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                      @error('editData.contact_number') border-red-400 @enderror"
                               style="border-color: #d1d5db;">
                        @error('editData.contact_number')
                            <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Email</label>
                        <input wire:model="editData.email"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               style="border-color: #d1d5db;">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Status *</label>
                        <select wire:model="editData.status"
                                class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                       @error('editData.status') border-red-400 @enderror"
                                style="border-color: #d1d5db;">
                            <option>Pending</option>
                            <option>Verified</option>
                            <option>Flagged</option>
                            <option>Inactive</option>
                        </select>
                        @error('editData.status')
                            <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Address</label>
                        <input wire:model="editData.address"
                               class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               style="border-color: #d1d5db;">
                    </div>
                </div>
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t" style="border-color: #e2e8f0; background: #f8fafc;">
                <button wire:click="$set('showModal',false)"
                        class="text-xs font-semibold px-4 py-2 rounded-lg border transition-colors hover:bg-slate-100"
                        style="color: #64748b; border-color: #d1d5db;">
                    Cancel
                </button>
                <button wire:click="saveEdit"
                        class="text-xs font-semibold px-5 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
                        style="background: #1a2035;">
                    <i class="fas fa-save mr-1.5"></i> Save Changes
                </button>
            </div>

        </div>
    </div>
    @endif

</div>