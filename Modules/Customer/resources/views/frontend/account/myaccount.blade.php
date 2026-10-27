@extends('customer::frontend.account.layout')

@php
    $fullName = $customer->displayName();
    $avatar = $customer->profile_picture
        ? getImageUrl(['module' => config('customer.name'), 'image' => $customer->profile_picture, 'image-type' => 'thumbnail'])
        : null;
@endphp

@section('title', trans('customer::customer_front.titles.my_account'))
@section('heading', trans('theme::frontend.account.greeting', ['name' => $customer->first_name ?: $fullName]))
@section('subheading', trans('customer::customer_front.titles.account_overview'))

@section('account')
    <div class="grid gap-6 xl:grid-cols-3">
        <x-theme::card class="xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="" class="size-16 rounded-full object-cover ring-4 ring-surface-alt">
                    @else
                        <span class="flex size-16 items-center justify-center rounded-full bg-accent-soft text-xl font-semibold text-accent">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($fullName, 0, 1)) }}
                        </span>
                    @endif
                    <div>
                        <p class="text-lg font-semibold text-ink">{{ $fullName }}</p>
                        @if ($customer->email)
                            <p class="text-sm text-muted">{{ $customer->email }}</p>
                        @endif
                    </div>
                </div>
                <x-theme::button variant="secondary" size="sm" :href="frontRoute('customer.profile.edit')">
                    <x-theme::icon name="pencil" size="size-4" /> {{ trans('customer::customer_front.buttons.edit') }}
                </x-theme::button>
            </div>

            <dl class="mt-8 grid gap-6 border-t border-line pt-6 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium tracking-wide text-muted uppercase">{{ trans('customer::customer_front.profile.mobile') }}</dt>
                    <dd class="tabular mt-1 text-sm text-ink">{{ $customer->phone ? \Modules\Customer\Support\PhoneNumber::display($customer->phone) : ($customer->contact_number ?: '—') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium tracking-wide text-muted uppercase">{{ trans('customer::customer_front.labels.member_since') }}</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $customer->created_at?->translatedFormat('F Y') ?? '—' }}</dd>
                </div>
            </dl>

            @if ($customer->needsProfileCompletion())
                <x-theme::alert type="warning" class="mt-6">
                    <a href="{{ frontRoute('customer.profile.complete') }}" class="font-medium underline">{{ trans('customer::customer_front.complete_profile.title') }}</a>
                </x-theme::alert>
            @elseif (! $customer->phone && ! $customer->contact_number)
                <x-theme::alert type="warning" class="mt-6">{{ trans('customer::customer_front.messages.profile_incomplete') }}</x-theme::alert>
            @endif
        </x-theme::card>

        <x-theme::card padding="p-6">
            <h2 class="text-sm font-semibold text-ink">{{ trans('customer::customer_front.titles.quick_links') }}</h2>
            <ul class="mt-4 space-y-1">
                <li>
                    <a href="{{ frontRoute('customer.addresses.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                        <span class="flex items-center gap-2.5"><x-theme::icon name="map-pin" size="size-4" /> {{ trans('customer::customer_front.titles.addresses') }}</span>
                        <x-theme::badge>{{ $addressCount }}</x-theme::badge>
                    </a>
                </li>
                @if (\Illuminate\Support\Facades\Route::has('customer.wishlist.index'))
                    <li>
                        <a href="{{ frontRoute('customer.wishlist.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                            <span class="flex items-center gap-2.5"><x-theme::icon name="heart" size="size-4" /> {{ trans('customer::customer_front.wishlist.title') }}</span>
                            <x-theme::badge>{{ $wishlistCount }}</x-theme::badge>
                        </a>
                    </li>
                @endif
                <li>
                    <a href="{{ frontRoute('customer.reseller.edit') }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                        <span class="flex items-center gap-2.5"><x-theme::icon name="store" size="size-4" /> {{ trans('customer::customer_front.reseller.title') }}</span>
                        <x-theme::badge>{{ $customer->isReseller() ? trans('customer::customer_front.reseller.status_on') : trans('customer::customer_front.reseller.status_off') }}</x-theme::badge>
                    </a>
                </li>
                <li>
                    <a href="{{ frontRoute('customer.change-password') }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                        <span class="flex items-center gap-2.5"><x-theme::icon name="lock" size="size-4" /> {{ $customer->hasPassword() ? trans('customer::customer_front.titles.change_password') : trans('customer::customer_front.titles.set_password') }}</span>
                        <x-theme::icon name="chevron-right" size="size-4" class="text-muted" />
                    </a>
                </li>
                @if (\Illuminate\Support\Facades\Route::has('blog.index'))
                    <li>
                        <a href="{{ frontRoute('blog.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-ink-soft hover:bg-surface-alt hover:text-ink">
                            <span class="flex items-center gap-2.5"><x-theme::icon name="document" size="size-4" /> {{ trans('theme::frontend.nav.blog') }}</span>
                            <x-theme::icon name="chevron-right" size="size-4" class="text-muted" />
                        </a>
                    </li>
                @endif
            </ul>
        </x-theme::card>
    </div>
@endsection
