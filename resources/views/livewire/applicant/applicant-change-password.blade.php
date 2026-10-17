<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color:#1e293b;">
            <i class="fas fa-key mr-2" style="color:#059669;"></i> Change Password
        </h1>
        <p class="text-xs mt-0.5" style="color:#64748b;">Keep your account secure.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#d1fae5; color:#047857;">
            <i class="fas fa-circle-check mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <form wire:submit="save" class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="space-y-4">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current password</label>
                    <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">New password</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm new password</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-xl border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="mt-6 px-5 py-2.5 rounded-xl text-sm font-bold text-white disabled:opacity-60"
                    style="background:linear-gradient(90deg,#059669,#0d9488);">
                <span wire:loading.remove wire:target="save">Update password</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </form>

        <div class="rounded-xl p-6" style="background:#ecfdf5; border:1px solid #a7f3d0;">
            <h6 class="text-sm font-bold mb-3" style="color:#064e3b;">
                <i class="fas fa-shield-halved mr-1"></i> Tips for a strong password
            </h6>
            <ul class="text-sm space-y-2" style="color:#047857;">
                <li><i class="fas fa-check mr-2 text-xs"></i> At least 8 characters</li>
                <li><i class="fas fa-check mr-2 text-xs"></i> Different from your current password</li>
                <li><i class="fas fa-check mr-2 text-xs"></i> Mix letters, numbers and symbols</li>
                <li><i class="fas fa-check mr-2 text-xs"></i> Don't share it with anyone</li>
            </ul>
        </div>
    </div>
</div>