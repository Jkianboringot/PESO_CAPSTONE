<div>
    <x-slot name="title">Geogrophical Management</x-slot>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-users mr-2" style="color: #2563eb;"></i>
                Geogrophical Management
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Search, filter, edit, and manage all registered applicants
                barangay/municipality
            </p>
        </div>
    </div>

    <!-- //TODO - all ui must not use inline css, let others do this  -->

    <div class="flex items-center gap-1.5" >
        <!-- //REMOVE-LATER - temporary,remove it just for testing -->

        <button wire:click="openCreate"
            class=" px-3 py-1.5 rounded-lg border transition-colors hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200"
            style="color: #2563eb; border-color: #bfdbfe; margin-left:90%"> 
            Create
        </button>

    </div>
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">

        {{-- Dark header bar --}}
        <div class="px-5 py-3 flex items-center justify-between" style="background: #1a2035;">
            <span class="text-sm font-semibold text-white">
                <i class="fas fa-table mr-2 opacity-70"></i>Barangay Records
            </span>
            <div class="lg:col-span-2">

                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs"
                        style="color: #94a3b8;"></i>
                    <input wire:model.live="search"
                        class="w-full text-xs pl-8 pr-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        style="border-color: #d1d5db; color: #1e293b; background: #f9fafb;" placeholder="Barangay...">
                </div>
            </div>
        </div>

        {{-- Column headers --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                            style="color: #64748b; font-size: 10px;">Barangay</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                            style="color: #64748b; font-size: 10px;">Municipality</th>
                        <th class="px-4 py-3 text-left font-semibold uppercase tracking-wide"
                            style="color: #64748b; font-size: 10px;">Province</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: #f1f5f9;">

                    @forelse($viewBarangays as $b)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3" style="color: #475569;">
                                {{ $b->name ?? 'System' }}
                            </td>

                            <td class="px-4 py-3" style="color: #475569;">
                                {{ $b->municipality->name }}
                            </td>

                            <td class="px-4 py-3" style="color: #475569;">
                                {{ $b->municipality->province }}
                            </td>


                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center" style="color: #94a3b8;">
                                <i class="fas fa-search text-3xl mb-3 block opacity-30"></i>
                                No barangay found matching your search
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-4 py-3 border-top">
            {{ $viewBarangays->links() }}
        </div>

    </div>

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
                            <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Barangay *</label>
                            <input wire:model="barangayName" class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                                  @error('editData.last_name') border-red-400 @enderror"
                                style="border-color: #d1d5db;">
                            @error('barangayName')
                                <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                            @enderror
                        </div>
                       
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color: #374151;">Municipality *</label>
                            <select wire:model="municipalityID" class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none
                                                   @error('municipalityID') border-red-400 @enderror"
                                style="border-color: #d1d5db;">
                                @foreach ($selectMunicipality as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            
                            </select>
                            @error('municipalityID')
                                <p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>
                            @enderror
                        </div>
                      
                    </div>
                </div>

                {{-- Modal footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t"
                    style="border-color: #e2e8f0; background: #f8fafc;">
                    <button wire:click="$set('showModal',false)"
                        class="text-xs font-semibold px-4 py-2 rounded-lg border transition-colors hover:bg-slate-100"
                        style="color: #64748b; border-color: #d1d5db;">
                        Cancel
                    </button>
                    <button wire:click="save"
                        class="text-xs font-semibold px-5 py-2 rounded-lg text-white transition-opacity hover:opacity-90"
                        style="background: #1a2035;">
                        <i class="fas fa-save mr-1.5"></i> Save Changes
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>