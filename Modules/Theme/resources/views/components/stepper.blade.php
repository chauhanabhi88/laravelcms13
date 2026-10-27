@props(['steps' => [], 'current' => 1])
{{-- steps: list of labels; current: 1-based number of the active step. Horizontal from md, compact on mobile. --}}
@php
    $steps = array_values($steps);
    $total = count($steps);
    $current = max(1, min((int) $current, $total));
@endphp
<nav aria-label="{{ trans('theme::frontend.stepper.progress') }}" {{ $attributes }}>
    <p class="mb-2 text-xs font-medium text-muted md:hidden">
        {{ trans('theme::frontend.stepper.step_of', ['current' => $current, 'total' => $total]) }}
        <span class="text-ink">· {{ $steps[$current - 1] ?? '' }}</span>
    </p>
    <ol class="flex items-center gap-2">
        @foreach ($steps as $index => $label)
            @php
                $number = $index + 1;
                $state = $number < $current ? 'done' : ($number === $current ? 'current' : 'pending');
            @endphp
            <li @class(['flex items-center gap-2', 'flex-1' => ! $loop->last]) @if ($state === 'current') aria-current="step" @endif>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-accent text-accent-ink' => $state !== 'pending',
                    'border border-line-strong bg-surface text-muted' => $state === 'pending',
                ])>
                    @if ($state === 'done')
                        <x-theme::icon name="check" size="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'hidden text-sm whitespace-nowrap md:inline',
                    'font-semibold text-ink' => $state === 'current',
                    'text-ink-soft' => $state === 'done',
                    'text-muted' => $state === 'pending',
                ])>{{ $label }}</span>
                @if ($state !== 'pending')
                    <span class="sr-only">({{ trans($state === 'done' ? 'theme::frontend.stepper.completed' : 'theme::frontend.stepper.current') }})</span>
                @endif
                @unless ($loop->last)
                    <span @class(['h-0.5 flex-1 rounded-full', 'bg-accent' => $state === 'done', 'bg-line' => $state !== 'done']) aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
