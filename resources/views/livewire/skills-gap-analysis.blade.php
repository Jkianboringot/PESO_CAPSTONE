<div>
    <x-slot name="title">Skills Gap Analysis</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color: #1e293b;">
                <i class="fas fa-search mr-2" style="color: #00796b;"></i>
                Skills Gap Analysis
            </h1>
            <p class="text-xs mt-0.5" style="color: #64748b;">Identify underrepresented PQF skill clusters in Catanduanes</p>
        </div>
    </div>

    {{-- Threshold Control --}}
    <div class="bg-white rounded-xl border mb-5 p-4" style="border-color: #e2e8f0;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div>
                    <label class="block text-xs font-bold mb-1.5 uppercase tracking-wide" style="color: #374151;">
                        Gap Threshold
                    </label>
                    <p class="text-xs mb-2" style="color: #64748b;">Applicants below this = skill gap</p>
                    <div class="flex items-center gap-2">
                        <input type="number" wire:model.live="threshold"
                               class="text-sm px-3 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                               style="border-color: #d1d5db; width: 90px;"
                               min="1">
                        <span class="text-xs" style="color: #64748b;">applicants</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold"
                      style="background: #fee2e2; color: #dc2626;">
                    <i class="fas fa-arrow-down mr-1.5"></i>
                    {{ $gapSkills->count() }} Gap Skills
                </span>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold"
                      style="background: #dcfce7; color: #15803d;">
                    <i class="fas fa-arrow-up mr-1.5"></i>
                    {{ $surplusSkills->count() }} Adequate Skills
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

        {{-- Gap Skills --}}
        <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
            <div class="px-4 py-3 text-sm font-bold text-white" style="background: #C00000;">
                <i class="fas fa-arrow-down mr-1.5"></i>
                Skills Gap (Below {{ $threshold }} Registrants)
            </div>
            <div class="overflow-y-auto" style="max-height: 400px;">
                <table class="w-full text-xs">
                    <thead class="sticky top-0" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Skill</th>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Category</th>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Registrants</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: #f1f5f9;">
                        @forelse($gapSkills as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium" style="color: #1e293b;">{{ $row->skill }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded text-xs" style="background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0;">{{ $row->category }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: #fee2e2; color: #dc2626;">{{ $row->applicant_count }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-xs" style="color: #94a3b8;">No gap skills found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Adequate Skills --}}
        <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
            <div class="px-4 py-3 text-sm font-bold text-white" style="background: #2e7d32;">
                <i class="fas fa-arrow-up mr-1.5"></i>
                Adequate Skills ({{ $threshold }}+ Registrants)
            </div>
            <div class="overflow-y-auto" style="max-height: 400px;">
                <table class="w-full text-xs">
                    <thead class="sticky top-0" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Skill</th>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Category</th>
                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Registrants</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: #f1f5f9;">
                        @forelse($surplusSkills as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium" style="color: #1e293b;">{{ $row->skill }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded text-xs" style="background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0;">{{ $row->category }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background: #dcfce7; color: #15803d;">{{ $row->applicant_count }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-xs" style="color: #94a3b8;">No data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Category Totals --}}
    <div class="bg-white rounded-xl border overflow-hidden" style="border-color: #e2e8f0;">
        <div class="px-5 py-3 border-b" style="background: #f8fafc; border-color: #e2e8f0;">
            <span class="text-sm font-bold" style="color: #1e293b;">Skills by Category (Total Registrations)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th class="px-5 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Category</th>
                        <th class="px-5 py-3 text-left font-semibold uppercase tracking-wide" style="color: #64748b; font-size: 10px;">Total Registrations</th>
                        <th class="px-5 py-3 text-left font-semibold uppercase tracking-wide w-1/2" style="color: #64748b; font-size: 10px;">Distribution</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: #f1f5f9;">
                    @php $max = $categoryTotals->max('total') ?: 1; @endphp
                    @foreach($categoryTotals as $cat)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium" style="color: #1e293b;">{{ $cat->name }}</td>
                        <td class="px-5 py-3 font-bold" style="color: #1F4E79;">{{ $cat->total }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 rounded-full overflow-hidden" style="background: #f1f5f9; height: 8px;">
                                    <div class="h-full rounded-full" style="width: {{ ($cat->total / $max) * 100 }}%; background: #1F4E79;"></div>
                                </div>
                                <span class="text-xs" style="color: #94a3b8; min-width: 40px;">{{ round(($cat->total / $max) * 100) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center mt-6">
        <p class="text-xs italic" style="color: #94a3b8;">Design Pattern: Shneiderman's Information-Seeking Mantra</p>
    </div>
</div>