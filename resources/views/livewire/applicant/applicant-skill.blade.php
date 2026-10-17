<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-screwdriver-wrench mr-2" style="color:#059669;"></i> Skills
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Keep the skills on your PESO record current.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#d1fae5; color:#047857;">
            <i class="fas fa-circle-check mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- Add skill --}}
        <form wire:submit="addSkill" class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-1">
            <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Add a skill</h6>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <select wire:model.live="category_id" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Choose...</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Skill</label>
                    <select wire:model="skill_id" @disabled($category_id === '') class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100">
                        <option value="">Choose...</option>
                        @foreach ($available as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('skill_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Proficiency</label>
                    <select wire:model="proficiency" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        @foreach ($levels as $l)
                            <option value="{{ $l }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="mt-6 w-full px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60"
                    style="background:linear-gradient(90deg,#059669,#0d9488);">
                <i class="fas fa-plus mr-1 text-xs"></i> Add skill
            </button>
        </form>

        {{-- My skills --}}
        <div class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-2">
            <h6 class="text-sm font-bold mb-2" style="color:#1e293b;">My skills ({{ $mine->count() }})</h6>

            @forelse ($mine->groupBy('category_name') as $category => $rows)
                <p class="text-xs font-semibold uppercase tracking-wider mt-5 mb-1" style="color:#94a3b8;">{{ $category }}</p>
                <ul class="divide-y" style="border-color:#f1f5f9;">
                    @foreach ($rows as $row)
                        <li class="flex items-center justify-between py-2.5" wire:key="skill-{{ $row->skill_id }}">
                            <div>
                                <span class="text-sm font-medium" style="color:#1e293b;">{{ $row->skill_name }}</span>
                                <span class="ml-2 text-[11px] font-semibold px-2 py-0.5 rounded-full" style="background:#d1fae5; color:#047857;">{{ $row->proficiency_level }}</span>
                            </div>
                            <button type="button" wire:click="removeSkill({{ $row->skill_id }})"
                                    wire:confirm="Remove this skill?"
                                    class="text-xs px-2.5 py-1 rounded-lg border hover:bg-red-50 hover:text-red-600 hover:border-red-200"
                                    style="color:#64748b; border-color:#e2e8f0;">
                                <i class="fas fa-trash-can"></i>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @empty
                <div class="text-center py-10">
                    <i class="fas fa-screwdriver-wrench text-3xl mb-3" style="color:#cbd5e1;"></i>
                    <p class="text-sm" style="color:#64748b;">You haven't added any skills yet.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>