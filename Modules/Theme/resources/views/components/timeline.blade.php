@props(['events' => []])
{{-- events: [['label' => …, 'at' => string|DateTimeInterface|null, 'state' => 'done'|'current'|'pending', 'note' => ?string]] --}}
<ol {{ $attributes->class('relative') }}>
    @foreach ($events as $event)
        @php
            $state = $event['state'] ?? 'pending';
            $at = $event['at'] ?? null;
            $atText = $at instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($at)->format('D, j M, g:i A') : $at;
        @endphp
        <li class="relative flex gap-3 pb-6 last:pb-0" @if ($state === 'current') aria-current="step" @endif>
            @unless ($loop->last)
                <span @class(['absolute top-6 bottom-0 left-[11px] w-0.5', 'bg-accent' => $state === 'done', 'bg-line' => $state !== 'done']) aria-hidden="true"></span>
            @endunless
            <span @class([
                'relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full',
                'bg-accent text-accent-ink' => $state === 'done',
                'bg-accent-soft text-accent ring-2 ring-accent' => $state === 'current',
                'border-2 border-line-strong bg-surface' => $state === 'pending',
            ])>
                @if ($state === 'done')
                    <x-theme::icon name="check" size="size-3.5" />
                @elseif ($state === 'current')
                    <span class="size-2 rounded-full bg-accent"></span>
                @endif
            </span>
            <div class="min-w-0 -mt-0.5">
                <p @class(['text-sm font-medium', 'text-ink' => $state !== 'pending', 'text-muted' => $state === 'pending'])>{{ $event['label'] }}</p>
                @if ($atText)
                    <p class="text-xs text-muted">
                        @if ($at instanceof \DateTimeInterface)
                            <time datetime="{{ $at->format(DATE_ATOM) }}">{{ $atText }}</time>
                        @else
                            {{ $atText }}
                        @endif
                    </p>
                @endif
                @if (! empty($event['note']))
                    <p class="mt-1 text-sm text-ink-soft">{{ $event['note'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
