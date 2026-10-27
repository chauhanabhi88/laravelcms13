@extends('theme::layouts.seller.master')

@section('title', trans('seller::panel.business.title'))

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-card border border-line bg-surface p-5 shadow-card lg:order-last">
            <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.business.kyc') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ trans('seller::panel.business.kyc_hint') }}</p>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach ([
                    'code' => $seller->code,
                    'store_name' => $seller->store_name,
                    'legal_name' => $seller->legal_name,
                    'business_type' => $seller->business_type ? trans('seller::seller.business_types.'.$seller->business_type) : null,
                    'pan' => $seller->maskedPan(),
                    'gstin' => $seller->gstin ?: $seller->enrolment_id,
                    'state' => $seller->state?->name,
                ] as $label => $value)
                    <div>
                        <dt class="text-muted">{{ trans('seller::seller.labels.'.$label) }}</dt>
                        <dd class="font-medium text-ink">{{ $value ?: trans('seller::seller.labels.none') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <form method="POST" action="{{ frontRoute('seller.profile.update') }}" enctype="multipart/form-data"
            class="space-y-6 rounded-card border border-line bg-surface p-5 shadow-card lg:col-span-2" x-data="{ loading: false }" x-on:submit="loading = true">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.business.contact') }}</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-theme::input type="email" name="email" :value="$seller->email" :label="trans('seller::panel.business.email')" required />
                    <x-theme::input type="tel" name="phone" :value="$phone" :label="trans('seller::panel.business.phone')" inputmode="numeric" maxlength="14" required />
                </div>
                <x-theme::field-error name="phone_e164" />
            </div>

            <div class="space-y-3">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.business.logo') }}</h2>
                <div class="flex items-center gap-4">
                    <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-card border border-line bg-surface-alt text-muted">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="size-full object-cover">
                        @else
                            <x-theme::icon name="store" />
                        @endif
                    </span>
                    <div class="text-sm">
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="block text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-accent-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-accent">
                        <p class="mt-1 text-xs text-muted">{{ trans('seller::panel.business.logo_hint') }}</p>
                        @if ($logoUrl)
                            <label class="mt-2 flex items-center gap-2 text-ink-soft">
                                <input type="checkbox" name="remove_logo" value="1" class="size-4 rounded border-line-strong accent-accent"> {{ trans('seller::panel.business.remove_logo') }}
                            </label>
                        @endif
                    </div>
                </div>
                <x-theme::field-error name="logo" />
            </div>

            <div class="space-y-3">
                <h2 class="text-base font-semibold text-ink">{{ trans('seller::panel.business.orders') }}</h2>
                <label class="flex items-start gap-2.5 text-sm text-ink">
                    <input type="checkbox" name="auto_accept_orders" value="{{ config('core.yes') }}" @checked((int) old('auto_accept_orders', $seller->auto_accept_orders) === (int) config('core.yes'))
                        class="mt-0.5 size-4 rounded border-line-strong accent-accent">
                    <span>
                        {{ trans('seller::panel.business.auto_accept') }}
                        <span class="block text-xs text-muted">{{ trans('seller::panel.business.auto_accept_hint') }}</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end border-t border-line pt-5">
                <x-theme::button loading="loading">{{ trans('seller::panel.business.save') }}</x-theme::button>
            </div>
        </form>
    </div>
@endsection
