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
                <x-theme::input name="last_name" :value="$customer->last_name" :label="trans('customer::customer_front.labels.last_name')" autocomplete="family-name" required />
                <x-theme::input type="email" name="email" :value="$customer->email" :label="trans('customer::customer_front.labels.email')" autocomplete="email" required />
                <x-theme::input type="tel" name="contact_number" :value="$customer->contact_number" :label="trans('customer::customer_front.labels.contact_number')" autocomplete="tel" inputmode="numeric" required />
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <x-theme::button variant="ghost" :href="frontRoute('customer.myaccount')">{{ trans('customer::customer_front.buttons.cancel') }}</x-theme::button>
                <x-theme::button loading="loading">{{ trans('customer::customer_front.buttons.save') }}</x-theme::button>
            </div>
        </form>
    </x-theme::card>
@endsection
