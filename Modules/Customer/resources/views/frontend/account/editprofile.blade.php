@extends('customer::frontend.account.layout')

@php
    $avatar = $customer->profile_picture
        ? getImageUrl(['module' => config('customer.name'), 'image' => $customer->profile_picture, 'image-type' => 'thumbnail'])
        : null;
    $imageTypes = settings('customer', 'image_type') ?: 'jpeg,jpg,png';
@endphp

@section('title', trans('customer::customer_front.titles.edit_profile'))
@section('heading', trans('customer::customer_front.titles.edit_profile'))

@section('account')
    <x-theme::card>
        <form method="POST" action="{{ frontRoute('customer.update') }}" enctype="multipart/form-data" class="space-y-6"
            x-data="{ loading: false, preview: @js($avatar), remove: false }" x-on:submit="loading = true">
            @csrf

            <div class="flex items-center gap-5">
                <div class="relative size-20 shrink-0 overflow-hidden rounded-full bg-surface-alt ring-4 ring-surface-alt">
                    <img x-show="preview && ! remove" x-bind:src="preview" alt="" class="size-full object-cover" @if (! $avatar) x-cloak @endif>
                    <span x-show="! preview || remove" @if ($avatar) x-cloak @endif class="flex size-full items-center justify-center text-muted"><x-theme::icon name="user" size="size-8" /></span>
                </div>
                <div class="space-y-2">
                    <label class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-line-strong bg-surface px-3 text-sm font-medium text-ink shadow-soft hover:bg-surface-alt">
                        {{ trans('customer::customer_front.labels.profile_picture') }}
                        <input type="file" name="profile_picture" accept=".{{ str_replace(',', ',.', $imageTypes) }}" class="sr-only"
                            x-on:change="const file = $event.target.files[0]; if (file) { preview = URL.createObjectURL(file); remove = false }">
                    </label>
                    @if ($avatar)
                        <label class="flex items-center gap-2 text-xs text-muted">
                            <input type="checkbox" name="remove_profile_picture" value="1" x-model="remove" class="size-3.5 accent-danger"> {{ trans('customer::customer_front.buttons.delete') }}
                        </label>
                    @endif
                    <x-theme::field-error name="profile_picture" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-theme::input name="first_name" :value="$customer->first_name" :label="trans('customer::customer_front.labels.first_name')" autocomplete="given-name" required />
                <x-theme::input name="last_name" :value="$customer->last_name" :label="trans('customer::customer_front.labels.last_name')" autocomplete="family-name" optional />
                @if ($customer->phone)
                    <div>
                        <x-theme::label for="mobile">{{ trans('customer::customer_front.profile.mobile') }}</x-theme::label>
                        <input type="tel" id="mobile" value="{{ \Modules\Customer\Support\PhoneNumber::display($customer->phone) }}" readonly
                            class="tabular block h-11 w-full rounded-lg border border-line bg-surface-alt px-3.5 text-sm text-ink-soft">
                        <p class="mt-1.5 text-xs text-muted">{{ trans('customer::customer_front.profile.mobile_help') }}</p>
                    </div>
                @endif
                <x-theme::input type="email" name="email" :value="$customer->email" :label="trans('customer::customer_front.labels.email')" autocomplete="email"
                    :required="blank($customer->phone)" :optional="filled($customer->phone)" />
                <x-theme::select name="gender" :value="$customer->gender" :label="trans('customer::customer_front.profile.gender')"
                    :placeholder="trans('customer::customer_front.profile.gender_placeholder')"
                    :options="collect(\Modules\Customer\Models\Customer::GENDERS)->mapWithKeys(fn ($gender) => [$gender => trans('customer::customer_front.profile.genders.'.$gender)])->all()" />
                <x-theme::input type="date" name="date_of_birth" :value="$customer->date_of_birth?->format('Y-m-d')" :label="trans('customer::customer_front.profile.date_of_birth')"
                    autocomplete="bday" :max="now()->subDay()->format('Y-m-d')" optional />
                @if (count($languages) > 1)
                    <x-theme::select name="preferred_locale" :value="$customer->preferred_locale" :label="trans('customer::customer_front.profile.language')" :options="$languages" />
                @endif
                @if (! $customer->phone && $customer->contact_number)
                    <x-theme::input type="tel" name="contact_number" :value="$customer->contact_number" :label="trans('customer::customer_front.labels.contact_number')" autocomplete="tel" inputmode="numeric" optional />
                @endif
            </div>

            <label class="flex items-center gap-2.5 text-sm text-ink-soft">
                <input type="hidden" name="marketing_opt_in" value="{{ config('core.no') }}">
                <input type="checkbox" name="marketing_opt_in" value="{{ config('core.yes') }}" @checked((int) old('marketing_opt_in', $customer->marketing_opt_in ?? config('core.yes')) === (int) config('core.yes')) class="size-4 rounded accent-accent">
                {{ trans('customer::customer_front.profile.marketing_opt_in') }}
            </label>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-theme::button variant="ghost" :href="frontRoute('customer.myaccount')">{{ trans('customer::customer_front.buttons.cancel') }}</x-theme::button>
                <x-theme::button loading="loading">{{ trans('customer::customer_front.buttons.save') }}</x-theme::button>
            </div>
        </form>
    </x-theme::card>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
        <a href="{{ frontRoute('customer.change-password') }}" class="inline-flex items-center gap-1.5 font-medium text-ink-soft hover:text-ink">
            <x-theme::icon name="lock" size="size-4" />
            {{ $customer->hasPassword() ? trans('customer::customer_front.titles.change_password') : trans('customer::customer_front.titles.set_password') }}
        </a>
        <a href="{{ frontRoute('customer.deletion.create') }}" class="inline-flex items-center gap-1.5 font-medium text-danger hover:underline">
            <x-theme::icon name="trash" size="size-4" /> {{ trans('customer::customer_front.deletion.title') }}
        </a>
    </div>
@endsection
