@extends('theme::layouts.frontend.master')

@section('title', trans('logistics::front.tracking.result_title', ['reference' => $reference]))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@php
    $dateFormat = (string) config('storefront.delivery.date_format', 'D, j M');
@endphp

@section('content')
    {{-- Public tracking (FR-LOG-007): status, steps, courier, AWB, EDD and scan locations only. --}}
    <div class="container-page max-w-2xl space-y-5 py-10 lg:py-14">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-ink sm:text-2xl">{{ trans('logistics::front.tracking.result_title', ['reference' => $reference]) }}</h1>
            <a href="{{ frontRoute('tracking.index') }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('logistics::front.tracking.submit') }} ›</a>
        </div>

        @foreach ($timelines as $timeline)
            <x-theme::card padding="p-5 sm:p-6" data-tracking-package="{{ $timeline['sub_order_no'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink">{{ trans('logistics::front.tracking.package', ['no' => $timeline['sub_order_no']]) }}</h2>
                    @if ($timeline['branch'])
                        <x-theme::chip :variant="$timeline['branch']['key'] === 'cancelled' ? 'danger' : 'deal'">{{ $timeline['branch']['label'] }}</x-theme::chip>
                    @endif
                </div>

                <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                    @if ($timeline['courier'])
                        <div><dt class="text-muted">{{ trans('logistics::front.tracking.courier') }}</dt><dd class="font-medium text-ink">{{ $timeline['courier'] }}</dd></div>
                    @endif
                    @if ($timeline['awb'])
                        <div><dt class="text-muted">{{ trans('logistics::front.tracking.awb') }}</dt><dd class="font-medium text-ink">{{ $timeline['awb'] }}</dd></div>
                    @endif
                    @if ($timeline['edd'])
                        <div><dt class="text-muted">{{ trans('logistics::front.tracking.edd') }}</dt><dd class="font-medium text-ink">{{ $timeline['edd']->translatedFormat($dateFormat) }}</dd></div>
                    @endif
                </dl>

                <x-theme::timeline :events="$timeline['events']" class="mt-5" />

                @if ($timeline['tracking_url'])
                    <a href="{{ $timeline['tracking_url'] }}" target="_blank" rel="noopener nofollow" class="mt-4 inline-block text-sm font-medium text-accent hover:text-accent-hover">{{ trans('logistics::front.tracking.courier_link') }} ↗</a>
                @endif

                <details class="mt-5 border-t border-line pt-4">
                    <summary class="cursor-pointer text-sm font-medium text-ink">{{ trans('logistics::front.tracking.scans') }}</summary>
                    @if ($timeline['scans'] === [])
                        <p class="mt-3 text-sm text-muted">{{ trans('logistics::front.tracking.no_scans') }}</p>
                    @else
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($timeline['scans'] as $scan)
                                <li class="flex flex-wrap justify-between gap-2">
                                    <span class="text-ink">{{ $scan['label'] }}@if ($scan['location']) <span class="text-muted">· {{ $scan['location'] }}</span>@endif</span>
                                    <time class="text-xs text-muted" datetime="{{ $scan['at']->format(DATE_ATOM) }}">{{ $scan['at']->format('D, j M, g:i A') }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            </x-theme::card>
        @endforeach
    </div>
@endsection
