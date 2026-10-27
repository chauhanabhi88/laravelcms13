@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.tax.heading'))

@section('content')
    @php
        $yes = (int) config('core.yes');
        $no = (int) config('core.no');
        $hasGst = (int) old('has_gst', $seller->gst_status === \Modules\Seller\Models\Seller::GST_NOT_APPLICABLE && $allowNonGst ? $no : $yes);
        $maxMb = (int) config('seller.kyc_max_file_mb');
    @endphp
    <x-theme::card>
        <h1 class="text-xl font-semibold text-ink">{{ trans('seller::onboarding.tax.heading') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ trans('seller::onboarding.tax.intro') }}</p>

        <form method="POST" action="{{ frontRoute('seller.onboarding.save', [2]) }}" enctype="multipart/form-data" class="mt-6 space-y-5"
            x-data="{ loading: false, hasGst: {{ $hasGst }} }" x-on:submit="loading = true">
            @csrf

            @if ($allowNonGst)
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-ink">{{ trans('seller::onboarding.tax.has_gst') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ([$yes => 'yes', $no => 'no'] as $value => $label)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line-strong p-3 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                                <input type="radio" name="has_gst" value="{{ $value }}" x-model.number="hasGst" @checked($hasGst === $value) class="mt-0.5 accent-accent">
                                <span class="text-ink">{{ trans('seller::onboarding.tax.'.$label) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="has_gst" value="{{ $yes }}">
            @endif

            <div x-show="hasGst === {{ $yes }}" class="space-y-5">
                <x-theme::input name="gstin" :value="$seller->gstin" :label="trans('seller::onboarding.tax.gstin')" :hint="trans('seller::onboarding.tax.gstin_hint')"
                    maxlength="15" autocomplete="off" class="uppercase tracking-wide" x-bind:required="hasGst === {{ $yes }}" />

                <div>
                    @if ($certificate)
                        <p class="mb-2 flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <x-theme::icon name="document" size="size-4" />
                            <a href="{{ frontRoute('seller.kyc.download', [$certificate->id]) }}" target="_blank" rel="noopener" class="font-medium text-accent hover:text-accent-hover">
                                {{ trans('seller::onboarding.tax.certificate_on_file', ['name' => $certificate->original_name]) }}
                            </a>
                            <span class="text-muted">{{ trans('seller::onboarding.tax.replace') }}</span>
                        </p>
                    @endif
                    <x-theme::dropzone name="gst_certificate" :multiple="false" :max-files="1" :max-size-mb="$maxMb"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" :label="trans('seller::onboarding.tax.certificate')" />
                    <p class="mt-1.5 text-xs text-muted">{{ trans('seller::onboarding.tax.certificate_hint', ['size' => $maxMb]) }}</p>
                </div>
            </div>

            @if ($allowNonGst)
                <div x-show="hasGst === {{ $no }}" x-cloak>
                    <x-theme::input name="enrolment_id" :value="$seller->enrolment_id" :label="trans('seller::onboarding.tax.enrolment_id')"
                        :hint="trans('seller::onboarding.tax.enrolment_hint')" maxlength="20" class="uppercase" />
                </div>
            @endif

            @include('seller::seller.onboarding.partials.actions')
        </form>
    </x-theme::card>
@endsection
