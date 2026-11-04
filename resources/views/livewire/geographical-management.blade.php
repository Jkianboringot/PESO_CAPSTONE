<div>
    <x-slot name="title">Geographical Management</x-slot>

    {{-- Toast notifications --}}
    <x-toast />

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                <i class="fas fa-map-marked-alt mr-2 text-blue-600"></i>
                Geographical Management
            </h1>
            <p class="text-xs mt-0.5 text-slate-500">
                Add, edit, and manage barangays and the municipality they belong to
            </p>
        </div>
        <button wire:click="openCreate"
                class="text-xs font-semibold px-4 py-2.5 rounded-lg text-white bg-[#1a2035] transition-opacity hover:opacity-90">
            <i class="fas fa-plus mr-1.5"></i> Add Barangay
        </button>
    </div>

    {{-- Barangay Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

        {{-- Dark header bar + search --}}
        <div class="px-5 py-3 flex items-center justify-between gap-3 bg-[#1a2035]">
            <span class="text-sm font-semibold text-white">
                <i class="fas fa-table mr-2 opacity-70"></i>Barangay Records
            </span>
            <div class="relative w-full max-w-xs">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                <input wire:model.live.debounce.300ms="search"
                       class="w-full text-xs pl-8 pr-3 py-2 rounded-lg border border-gray-300 text-slate-800 bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                       placeholder="Search barangay, municipality, province...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide text-[10px] text-slate-500">Barangay</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide text-[10px] text-slate-500">Municipality</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide text-[10px] text-slate-500">Province</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide text-[10px] text-slate-500">Applicants</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide text-[10px] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($viewBarangays as $b)
                        <tr wire:key="barangay-{{ $b->id }}" class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $b->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $b->municipality->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $b->municipality->province ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                    {{ $b->applicants_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <button wire:click="openEdit({{ $b->id }})"
                                            class="text-xs font-medium px-3 py-1.5 rounded-lg border text-blue-600 border-blue-200 transition-colors hover:bg-blue-50 hover:text-blue-700">
                                        Edit
                                    </button>
                                    <button wire:key="delete-{{ $b->id }}"
                                            wire:click="delete({{ $b->id }})"
                                            wire:confirm="Delete this barangay?"
                                            class="text-xs font-medium px-3 py-1.5 rounded-lg border text-red-600 border-red-200 transition-colors hover:bg-red-50 hover:text-red-700">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-slate-400">
                                <i class="fas fa-search text-3xl mb-3 block opacity-30"></i>
                                No barangay found matching your search
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($viewBarangays->hasPages())
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50">
                {{ $viewBarangays->links() }}
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">

                <div class="flex items-center justify-between px-6 py-4 bg-[#1a2035]">
                    <h5 class="text-sm font-bold text-white">
                        <i class="fas fa-{{ $editingId ? 'edit' : 'plus' }} mr-2 opacity-70"></i>
                        {{ $editingId ? 'Edit Barangay' : 'Add Barangay' }}
                    </h5>
                    <button wire:click="closeModal" class="text-white opacity-60 hover:opacity-100 transition-opacity">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700">Barangay *</label>
                            <input wire:model="barangayName"
                                   class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('barangayName') border-red-400 @enderror">
                            @error('barangayName')
                                <p class="text-xs mt-1 text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1.5 text-gray-700">Municipality *</label>
                            <select wire:model="municipalityID"
                                    class="w-full text-sm px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('municipalityID') border-red-400 @enderror">
                                <option value="">Select Municipality</option>
                                @foreach($selectMunicipality as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                            @error('municipalityID')
                                <p class="text-xs mt-1 text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50">
                    <button wire:click="closeModal"
                            class="text-xs font-semibold px-4 py-2 rounded-lg border border-gray-300 text-slate-500 transition-colors hover:bg-slate-100">
                        Cancel
                    </button>
                    <button wire:click="save"
                            class="text-xs font-semibold px-5 py-2 rounded-lg text-white bg-[#1a2035] transition-opacity hover:opacity-90">
                        <i class="fas fa-save mr-1.5"></i>
                        {{ $editingId ? 'Update Barangay' : 'Create Barangay' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
