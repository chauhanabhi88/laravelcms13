{{-- Switches for PreferenceService::toggles() rows; posts enabled[] = "<channel>.<category>". --}}
<div class="divide-y divide-line">
    @foreach ($toggles as $toggle)
        @php
            $toggleId = 'pref-'.str_replace('.', '-', $toggle['key']);
            $copy = 'notification::front.preferences.toggles.'.$toggle['channel'].'.'.$toggle['category'];
        @endphp
        <div class="flex items-start justify-between gap-4 py-4 first:pt-0 last:pb-0">
            <div>
                <p id="{{ $toggleId }}-label" class="text-sm font-semibold text-ink">{{ trans($copy.'.label') }}</p>
                <p id="{{ $toggleId }}-help" class="mt-1 text-sm text-muted">{{ trans($copy.'.help') }}</p>
            </div>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input type="checkbox" id="{{ $toggleId }}" name="enabled[]" value="{{ $toggle['key'] }}" role="switch" class="peer sr-only"
                    aria-labelledby="{{ $toggleId }}-label" aria-describedby="{{ $toggleId }}-help" @checked($toggle['enabled'])>
                <span class="h-7 w-12 rounded-full bg-line-strong transition peer-checked:bg-accent peer-focus-visible:ring-4 peer-focus-visible:ring-accent/25"></span>
                <span class="absolute left-1 size-5 rounded-full bg-surface shadow-soft transition peer-checked:translate-x-5"></span>
            </label>
        </div>
    @endforeach
</div>
