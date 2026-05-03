<div>
    <x-slot name="title">Report Generation</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-file-alt mr-2" style="color: #e65100;"></i>
                Report Generation
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Export DOLE BLE-compliant reports with custom filters</p>
        </div>
    </div>

    {{-- Config Panel --}}
    <div class="bg-white rounded-xl border overflow-hidden mb-5" style="border-color: #e2e8f0;">
        <div class="px-5 py-3 border-b" style="background: #f8fafc; border-color: #e2e8f0;">
            <span class="text-sm font-bold" style="color: #1e293b;">Configure Report Parameters</span>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Date From</label>
                    <input type="date" wire:model="dateFrom"
                           class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('dateFrom') border-red-400 @enderror"
                           style="border-color: #d1d5db;">
                    @error('dateFrom')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Date To</label>
                    <input type="date" wire:model="dateTo"
                           class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('dateTo') border-red-400 @enderror"
                           style="border-color: #d1d5db;">
                    @error('dateTo')<p class="text-xs mt-1" style="color: #dc2626;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Barangay</label>
                    <select wire:model="barangayId"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="border-color: #d1d5db;">
                        <option value="">All Barangays</option>
                        @foreach($barangays as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Skill Category</label>
                    <select wire:model="categoryId"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="border-color: #d1d5db;">
                        <option value="">All Categories</option>
                        @foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Education Level</label>
                    <select wire:model="educLevel"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="border-color: #d1d5db;">
                        <option value="">All Levels</option>
                        @foreach(['Elementary','High School','Senior High School','Vocational/Technical','College Undergraduate','College Graduate','Post-Graduate'] as $l)
                            <option>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Sex</label>
                    <select wire:model="sex"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="border-color: #d1d5db;">
                        <option value="">All</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">Export Format</label>
                    <select wire:model="format"
                            class="w-full text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            style="border-color: #d1d5db;">
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="csv">CSV (.csv)</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="px-5 py-4 flex items-center justify-between border-t" style="border-color: #e2e8f0; background: #f8fafc;">
            <p class="text-xs" style="color: #64748b;">
                <i class="fas fa-info-circle mr-1 text-blue-400"></i>
                Report columns are aligned with DOLE BLE submission format.
            </p>
            <button wire:click="generate"
                    class="text-xs font-semibold px-5 py-2.5 rounded-lg text-white transition-opacity hover:opacity-90"
                    style="background: #1a2035;">
                <span wire:loading wire:target="generate">
                    <i class="fas fa-spinner fa-spin mr-1.5"></i> Generating...
                </span>
                <span wire:loading.remove wire:target="generate">
                    <i class="fas fa-download mr-1.5"></i> Download Report
                </span>
            </button>
        </div>
    </div>

    {{-- Columns Panel --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
        <div class="px-5 py-3 border-b" style="background: #f8fafc; border-color: #e2e8f0;">
            <span class="text-sm font-bold" style="color: #1e293b;">Report Columns Included</span>
        </div>
        <div class="p-5">
            <div class="flex flex-wrap gap-2">
                @foreach(['Reference ID','Last Name','First Name','Middle Name','Birthdate','Age','Sex','Civil Status','Contact Number','Email','Address','Barangay','Municipality','Highest Education','Course/Program','Skills','Skill Categories','Registration Status','Date Registered'] as $col)
                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                          style="background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0;">
                        <i class="fas fa-columns mr-1.5 text-blue-400"></i>
                        {{ $col }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="text-center mt-6">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>
</div>