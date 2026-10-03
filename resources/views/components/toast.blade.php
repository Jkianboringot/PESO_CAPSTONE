{{--
    Reusable toast notification.

    Place it once in any Livewire view:   <x-toast />
    Optional:                              <x-toast :duration="8000" />

    Trigger it from the Livewire component:
        $this->dispatch('notify', type: 'success', message: 'Saved.');
        $this->dispatch('notify', type: 'error',   message: 'Something went wrong.');

    type = 'success' | 'error'
--}}
@props(['duration' => 5000])

<style>
    @keyframes toastIn { from { opacity: 0; transform: translateX(48px) scale(.97); } to { opacity: 1; transform: none; } }
    @keyframes glowGreen {
        0%, 100% { box-shadow: 0 0 0 4px rgba(34,197,94,.10), 0 0 22px rgba(34,197,94,.26), 0 10px 28px rgba(0,0,0,.08); }
        50%      { box-shadow: 0 0 0 6px rgba(34,197,94,.14), 0 0 32px rgba(34,197,94,.38), 0 10px 28px rgba(0,0,0,.08); } }
    @keyframes glowRed {
        0%, 100% { box-shadow: 0 0 0 4px rgba(239,68,68,.10), 0 0 22px rgba(239,68,68,.26), 0 10px 28px rgba(0,0,0,.08); }
        50%      { box-shadow: 0 0 0 6px rgba(239,68,68,.14), 0 0 32px rgba(239,68,68,.38), 0 10px 28px rgba(0,0,0,.08); } }

    .pt-toast { position: fixed; top: 20px; right: 20px; z-index: 9999; width: calc(100% - 40px); max-width: 400px; animation: toastIn .35s ease-out; }
    .pt-toast-box { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border: 1px solid; border-radius: 22px; cursor: pointer; }
    .pt-toast-box.pt-success { background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; animation: glowGreen 2.4s ease-in-out infinite; }
    .pt-toast-box.pt-error   { background: #fef2f2; border-color: #fecaca; color: #dc2626; animation: glowRed 2.4s ease-in-out infinite; }
    .pt-toast-icon { width: 40px; height: 40px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }
    .pt-success .pt-toast-icon { background: #dcfce7; }
    .pt-error .pt-toast-icon   { background: #fee2e2; }
    .pt-toast-text { flex: 1; min-width: 0; }
    .pt-toast-title { font-size: 14px; font-weight: 700; line-height: 1.2; }
    .pt-toast-msg { font-size: 13px; font-weight: 500; line-height: 1.35; margin-top: 2px; }
    .pt-toast-x { opacity: .45; font-size: 12px; flex-shrink: 0; }
</style>

<div x-data="{ show: false, type: 'success', message: '', t: null }"
    x-on:notify.window="
        clearTimeout(t); show = false;
        type = $event.detail.type; message = $event.detail.message;
        $nextTick(() => { show = true; t = setTimeout(() => show = false, {{ (int) $duration }}); })"
    x-show="show" x-cloak @click="show = false"
    class="pt-toast" style="display:none;">
    <div class="pt-toast-box" :class="'pt-' + type">
        <div class="pt-toast-icon">
            <i class="fas" :class="type === 'success' ? 'fa-check' : 'fa-exclamation'"></i>
        </div>
        <div class="pt-toast-text">
            <div class="pt-toast-title" x-text="type === 'success' ? 'Success' : 'Error'"></div>
            <div class="pt-toast-msg" x-text="message"></div>
        </div>
        <i class="fas fa-times pt-toast-x"></i>
    </div>
</div>