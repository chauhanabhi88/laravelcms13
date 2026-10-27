{{-- Toast stack. Seeded from the flashed success/error/warning/info that controllers set; JS adds more via Alpine.store('toast').push(). --}}
@php
    $flashed = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn ($type) => session()->has($type))
        ->map(fn ($type) => ['type' => $type, 'message' => (string) session($type)])
        ->values();
@endphp
<div x-data x-init="@js($flashed).forEach((toast) => $store.toast.push(toast.message, toast.type, 7000))"
    aria-live="polite" aria-atomic="false"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-center gap-2 sm:inset-x-auto sm:top-20 sm:right-6 sm:bottom-auto sm:items-end">
    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
            x-bind:role="toast.type === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-surface p-4 text-sm shadow-pop">
            <span class="mt-0.5 flex size-5 items-center justify-center rounded-full"
                x-bind:class="{
                    'bg-success-soft text-success': toast.type === 'success',
                    'bg-danger-soft text-danger': toast.type === 'error',
                    'bg-warning-soft text-warning': toast.type === 'warning',
                    'bg-accent-soft text-accent': toast.type === 'info',
                }">
                <x-theme::icon name="check" size="size-3.5" x-show="toast.type === 'success'" />
                <x-theme::icon name="alert" size="size-3.5" x-show="toast.type !== 'success'" />
            </span>
            <p class="flex-1 text-ink" x-text="toast.message"></p>
            <button type="button" x-on:click="$store.toast.dismiss(toast.id)" class="-m-1 rounded p-1 text-muted hover:text-ink"
                aria-label="{{ trans('theme::frontend.common.dismiss') }}">
                <x-theme::icon name="x" size="size-4" />
            </button>
        </div>
    </template>
</div>

{{-- No-JS fallback --}}
<noscript>
    <div class="container-page mt-4 space-y-2">
        @foreach ($flashed as $toast)
            <x-theme::alert :type="$toast['type']">{{ $toast['message'] }}</x-theme::alert>
        @endforeach
    </div>
</noscript>
