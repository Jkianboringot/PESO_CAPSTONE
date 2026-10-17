<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-briefcase mr-2" style="color:#059669;"></i> Education &amp; Employment
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Your highest educational attainment.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#d1fae5; color:#047857;">
            <i class="fas fa-circle-check mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="bg-white rounded-xl border border-slate-100 p-6">
        <h6 class="text-sm font-bold mb-4" style="color:#1e293b;">Education</h6>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Highest level reached</label>
                <select wire:model="highest_level" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Choose...</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l }}">{{ $l }}</option>
                    @endforeach
                </select>
                @error('highest_level') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Year graduated</label>
                <input type="number" wire:model="year_graduated" placeholder="e.g. 2019" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('year_graduated') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Course / program</label>
                <input type="text" wire:model="course_program" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('course_program') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">School</label>
                <input type="text" wire:model="school_name" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('school_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="mt-6 px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60"
                style="background:linear-gradient(90deg,#059669,#0d9488);">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </form>
</div>