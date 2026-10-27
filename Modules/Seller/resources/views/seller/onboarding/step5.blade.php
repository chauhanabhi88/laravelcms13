@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.agreement.heading'))

@section('content')
    <x-theme::card>
        <h1 class="text-xl font-semibold text-ink">{{ trans('seller::onboarding.agreement.heading') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ trans('seller::onboarding.agreement.intro') }}</p>

        <section class="mt-6" aria-labelledby="review-heading">
            <h2 id="review-heading" class="text-sm font-semibold text-ink">{{ trans('seller::onboarding.agreement.summary') }}</h2>
            <ul class="mt-3 divide-y divide-line rounded-lg border border-line">
                @foreach ($steps as $number => $label)
                    @continue($number === 5)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <span class="flex items-center gap-2 text-ink">
                            @if ($completed[$number] ?? false)
                                <x-theme::icon name="check-circle" size="size-4" class="text-success" />
                            @else
                                <x-theme::icon name="alert" size="size-4" class="text-danger" />
                            @endif
                            {{ $label }}
                        </span>
                        <a href="{{ frontRoute('seller.onboarding.show', [$number]) }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('seller::onboarding.buttons.edit') }}</a>
                    </li>
                @endforeach
            </ul>
            @if ($missing)
                <x-theme::alert type="warning" class="mt-3">
                    {{ trans('seller::onboarding.agreement.missing') }}
                    {{ collect($missing)->map(fn ($number) => $steps[$number])->implode(', ') }}
                </x-theme::alert>
            @endif
        </section>

        <form method="POST" action="{{ frontRoute('seller.onboarding.save', [5]) }}" class="mt-8"
            x-data="{ loading: false, readToEnd: false, check() { const el = this.$refs.agreement; if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) { this.readToEnd = true } } }"
            x-init="$nextTick(() => check())" x-on:submit="loading = true">
            @csrf
            <input type="hidden" name="agreement_version" value="{{ $agreementVersion }}">

            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-semibold text-ink">{{ trans('seller::agreement.title') }}</h2>
                <span class="text-xs text-muted">{{ trans('seller::onboarding.agreement.version', ['version' => $agreementVersion]) }}</span>
            </div>
            <div x-ref="agreement" x-on:scroll.throttle.100ms="check()" tabindex="0"
                class="mt-2 max-h-72 space-y-4 overflow-y-auto rounded-lg border border-line bg-surface-alt p-4 text-sm text-ink-soft focus-visible:outline-2 focus-visible:outline-accent">
                @foreach (trans('seller::agreement.sections') as $section)
                    <div>
                        <h3 class="font-semibold text-ink">{{ $section['heading'] }}</h3>
                        <p class="mt-1">{{ $section['body'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-muted" x-show="! readToEnd">{{ trans('seller::onboarding.agreement.scroll_hint') }}</p>

            <label class="mt-4 flex items-start gap-2.5 text-sm text-ink">
                <input type="checkbox" name="accept_agreement" value="1" required x-bind:disabled="! readToEnd" class="mt-0.5 size-4 shrink-0 rounded border-line-strong accent-accent">
                <span>{{ trans('seller::onboarding.agreement.accept') }}</span>
            </label>
            <x-theme::field-error name="accept_agreement" />
            <x-theme::field-error name="agreement_version" />

            @include('seller::seller.onboarding.partials.actions', ['submitLabel' => trans('seller::onboarding.agreement.submit')])
        </form>
    </x-theme::card>
@endsection
