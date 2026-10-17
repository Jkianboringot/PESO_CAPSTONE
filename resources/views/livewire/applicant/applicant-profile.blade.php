<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-id-card mr-2" style="color:#059669;"></i> My Registration
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Details you submitted to PESO.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#d1fae5; color:#047857;">
            <i class="fas fa-circle-check mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- Read-only details --}}
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Registration details</h6>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                <div><dt class="text-xs" style="color:#64748b;">Reference ID</dt><dd class="font-semibold break-all">{{ $applicant->reference_id }}</dd></div>
                <div><dt class="text-xs" style="color:#64748b;">Full name</dt><dd class="font-semibold">{{ $applicant->first_name }} {{ $applicant->middle_name }} {{ $applicant->last_name }}</dd></div>
                <div><dt class="text-xs" style="color:#64748b;">Birthdate</dt><dd class="font-semibold">{{ optional($applicant->birthdate)->format('M d, Y') ?? $applicant->birthdate }}</dd></div>
                <div><dt class="text-xs" style="color:#64748b;">Sex</dt><dd class="font-semibold">{{ $applicant->sex }}</dd></div>
                <div><dt class="text-xs" style="color:#64748b;">Civil status</dt><dd class="font-semibold">{{ $applicant->civil_status }}</dd></div>
                <div><dt class="text-xs" style="color:#64748b;">Barangay</dt><dd class="font-semibold">{{ $barangay ?? '—' }}</dd></div>
            </dl>
            <p class="text-xs mt-5 pt-4 border-t" style="color:#94a3b8; border-color:#f1f5f9;">
                <i class="fas fa-lock mr-1"></i>
                To correct your name, birthdate or other locked details, please visit the PESO office.
            </p>
        </div>

        {{-- Editable contact info --}}
        <form wire:submit="save" class="bg-white rounded-xl border border-slate-100 p-6">
            <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Contact information</h6>

            <div class="space-y-4">
                <div>
                    <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact number</label>
                    <input id="contact_number" type="text" wire:model="contact_number" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('contact_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
                    <input id="email" type="email" wire:model="email" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input id="address" type="text" wire:model="address" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="mt-6 px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60"
                    style="background:linear-gradient(90deg,#059669,#0d9488);">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </form>
    </div>

    {{-- PWD information --}}
    <form wire:submit="saveDisabilities" class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
        <h6 class="text-sm font-bold" style="color:#1e293b;">
            <i class="fas fa-wheelchair mr-1" style="color:#059669;"></i> Person with disability (PWD)
        </h6>
        <p class="text-xs mt-1 mb-4" style="color:#64748b;">
            Optional. Tick all that apply to you. Leave everything unchecked if none apply.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($disabilityOptions as $d)
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-700 cursor-pointer hover:bg-emerald-50">
                    <input type="checkbox" wire:model="disabilities" value="{{ $d }}"
                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    <span>{{ $d }}</span>
                </label>
            @endforeach
        </div>
        @error('disabilities.*') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="saveDisabilities"
                class="mt-6 px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60"
                style="background:linear-gradient(90deg,#059669,#0d9488);">
            <span wire:loading.remove wire:target="saveDisabilities">Save PWD information</span>
            <span wire:loading wire:target="saveDisabilities">Saving...</span>
        </button>
    </form>
</div>