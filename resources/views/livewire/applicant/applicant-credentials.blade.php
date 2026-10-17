@php
    $input = 'mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
    $label = 'block text-sm font-medium text-gray-700';
    $btn   = 'mt-5 w-full px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60';
    $grad  = 'background:linear-gradient(90deg,#059669,#0d9488);';
    $del   = 'text-xs px-2.5 py-1 rounded-lg border hover:bg-red-50 hover:text-red-600 hover:border-red-200';
@endphp

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-award mr-2" style="color:#059669;"></i> Experience &amp; Credentials
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Your work history, trainings, seminars and certifications.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#d1fae5; color:#047857;">
            <i class="fas fa-circle-check mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">

        {{-- ================= WORK EXPERIENCE ================= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <form wire:submit="addWork" class="bg-white rounded-xl border border-slate-100 p-6">
                <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Add work experience</h6>
                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">Company / employer</label>
                        <input type="text" wire:model="work.company_name" class="{{ $input }}">
                        @error('work.company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Position</label>
                        <input type="text" wire:model="work.position" class="{{ $input }}">
                        @error('work.position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Duration</label>
                        <input type="text" wire:model="work.duration" placeholder="e.g. 2 years 3 months" class="{{ $input }}">
                        @error('work.duration') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Description (optional)</label>
                        <textarea rows="3" wire:model="work.description" class="{{ $input }}"></textarea>
                        @error('work.description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="{{ $btn }}" style="{{ $grad }}">
                    <i class="fas fa-plus mr-1 text-xs"></i> Add experience
                </button>
            </form>

            <div class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-2">
                <h6 class="text-sm font-bold mb-2" style="color:#1e293b;">
                    <i class="fas fa-briefcase mr-1" style="color:#059669;"></i> Work experience ({{ $works->count() }})
                </h6>
                <ul class="divide-y" style="border-color:#f1f5f9;">
                    @forelse ($works as $w)
                        <li class="flex items-start justify-between py-3 gap-3" wire:key="work-{{ $w->id }}">
                            <div>
                                <p class="text-sm font-semibold" style="color:#1e293b;">{{ $w->position }}</p>
                                <p class="text-xs" style="color:#64748b;">{{ $w->company_name }}@if($w->duration) · {{ $w->duration }}@endif</p>
                                @if ($w->description)
                                    <p class="text-xs mt-1" style="color:#94a3b8;">{{ $w->description }}</p>
                                @endif
                            </div>
                            <button type="button" wire:click="removeWork({{ $w->id }})" wire:confirm="Remove this work experience?"
                                    class="{{ $del }}" style="color:#64748b; border-color:#e2e8f0;">
                                <i class="fas fa-trash-can"></i>
                            </button>
                        </li>
                    @empty
                        <li class="text-center py-8 text-sm" style="color:#64748b;">No work experience added yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- ================= TRAININGS ================= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <form wire:submit="addTraining" class="bg-white rounded-xl border border-slate-100 p-6">
                <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Add training</h6>
                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">Title</label>
                        <input type="text" wire:model="training.title" class="{{ $input }}">
                        @error('training.title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Provider</label>
                        <input type="text" wire:model="training.provider" placeholder="e.g. TESDA, DOLE" class="{{ $input }}">
                        @error('training.provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Year</label>
                        <input type="number" wire:model="training.year" placeholder="e.g. 2022" class="{{ $input }}">
                        @error('training.year') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="{{ $btn }}" style="{{ $grad }}">
                    <i class="fas fa-plus mr-1 text-xs"></i> Add training
                </button>
            </form>

            <div class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-2">
                <h6 class="text-sm font-bold mb-2" style="color:#1e293b;">
                    <i class="fas fa-chalkboard-user mr-1" style="color:#059669;"></i> Trainings ({{ $trainings->count() }})
                </h6>
                <ul class="divide-y" style="border-color:#f1f5f9;">
                    @forelse ($trainings as $t)
                        <li class="flex items-center justify-between py-3" wire:key="training-{{ $t->id }}">
                            <div>
                                <p class="text-sm font-semibold" style="color:#1e293b;">{{ $t->title }}</p>
                                <p class="text-xs" style="color:#64748b;">{{ $t->provider ?? '—' }}@if($t->year) · {{ $t->year }}@endif</p>
                            </div>
                            <button type="button" wire:click="removeTraining({{ $t->id }})" wire:confirm="Remove this training?"
                                    class="{{ $del }}" style="color:#64748b; border-color:#e2e8f0;">
                                <i class="fas fa-trash-can"></i>
                            </button>
                        </li>
                    @empty
                        <li class="text-center py-8 text-sm" style="color:#64748b;">No trainings added yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- ================= SEMINARS ================= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <form wire:submit="addSeminar" class="bg-white rounded-xl border border-slate-100 p-6">
                <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Add seminar</h6>
                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">Title</label>
                        <input type="text" wire:model="seminar.title" class="{{ $input }}">
                        @error('seminar.title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Organizer</label>
                        <input type="text" wire:model="seminar.organizer" class="{{ $input }}">
                        @error('seminar.organizer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Year</label>
                        <input type="number" wire:model="seminar.year" placeholder="e.g. 2023" class="{{ $input }}">
                        @error('seminar.year') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="{{ $btn }}" style="{{ $grad }}">
                    <i class="fas fa-plus mr-1 text-xs"></i> Add seminar
                </button>
            </form>

            <div class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-2">
                <h6 class="text-sm font-bold mb-2" style="color:#1e293b;">
                    <i class="fas fa-users-rectangle mr-1" style="color:#059669;"></i> Seminars ({{ $seminars->count() }})
                </h6>
                <ul class="divide-y" style="border-color:#f1f5f9;">
                    @forelse ($seminars as $s)
                        <li class="flex items-center justify-between py-3" wire:key="seminar-{{ $s->id }}">
                            <div>
                                <p class="text-sm font-semibold" style="color:#1e293b;">{{ $s->title }}</p>
                                <p class="text-xs" style="color:#64748b;">{{ $s->organizer ?? '—' }}@if($s->year) · {{ $s->year }}@endif</p>
                            </div>
                            <button type="button" wire:click="removeSeminar({{ $s->id }})" wire:confirm="Remove this seminar?"
                                    class="{{ $del }}" style="color:#64748b; border-color:#e2e8f0;">
                                <i class="fas fa-trash-can"></i>
                            </button>
                        </li>
                    @empty
                        <li class="text-center py-8 text-sm" style="color:#64748b;">No seminars added yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- ================= CERTIFICATIONS ================= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <form wire:submit="addCert" class="bg-white rounded-xl border border-slate-100 p-6">
                <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Add certification</h6>
                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">Certification / license</label>
                        <input type="text" wire:model="cert.name" placeholder="e.g. NC II Welding" class="{{ $input }}">
                        @error('cert.name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">Issuing body</label>
                        <input type="text" wire:model="cert.issuing_body" placeholder="e.g. TESDA, PRC" class="{{ $input }}">
                        @error('cert.issuing_body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">Year issued</label>
                            <input type="number" wire:model="cert.year_issued" class="{{ $input }}">
                            @error('cert.year_issued') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Expiry year</label>
                            <input type="number" wire:model="cert.expiry_year" placeholder="Blank = none" class="{{ $input }}">
                            @error('cert.expiry_year') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <button type="submit" wire:loading.attr="disabled" class="{{ $btn }}" style="{{ $grad }}">
                    <i class="fas fa-plus mr-1 text-xs"></i> Add certification
                </button>
            </form>

            <div class="bg-white rounded-xl border border-slate-100 p-6 lg:col-span-2">
                <h6 class="text-sm font-bold mb-2" style="color:#1e293b;">
                    <i class="fas fa-certificate mr-1" style="color:#059669;"></i> Certifications ({{ $certs->count() }})
                </h6>
                <ul class="divide-y" style="border-color:#f1f5f9;">
                    @forelse ($certs as $c)
                        <li class="flex items-center justify-between py-3" wire:key="cert-{{ $c->id }}">
                            <div>
                                <p class="text-sm font-semibold" style="color:#1e293b;">{{ $c->name }}</p>
                                <p class="text-xs" style="color:#64748b;">
                                    {{ $c->issuing_body ?? '—' }}
                                    @if($c->year_issued) · Issued {{ $c->year_issued }}@endif
                                    · {{ $c->expiry_year ? 'Expires ' . $c->expiry_year : 'No expiry' }}
                                </p>
                            </div>
                            <button type="button" wire:click="removeCert({{ $c->id }})" wire:confirm="Remove this certification?"
                                    class="{{ $del }}" style="color:#64748b; border-color:#e2e8f0;">
                                <i class="fas fa-trash-can"></i>
                            </button>
                        </li>
                    @empty
                        <li class="text-center py-8 text-sm" style="color:#64748b;">No certifications added yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

    </div>
</div>