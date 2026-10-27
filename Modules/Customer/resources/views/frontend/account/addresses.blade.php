@extends('customer::frontend.account.layout')

@php
    /* Re-open the right form after a failed submit (old input is flashed). */
    $editingId = old('address_id');
    $showForm = $errors->hasAny(array_filter(array_keys($errors->getMessages()), fn ($key) => str_starts_with($key, 'address.'))) || old('address');
    $atLimit = $addresses->count() >= $maxAddresses;
@endphp

@section('title', trans('customer::customer_front.titles.addresses'))
@section('heading', trans('customer::customer_front.titles.addresses'))
@section('subheading', trans('customer::customer_front.address.count', ['count' => $addresses->count(), 'max' => $maxAddresses]))

@section('account')
    <div x-data="{ open: @js($showForm ? ($editingId ?: 'new') : null) }" class="space-y-6">
        @error('address')
            <x-theme::alert type="error">{{ $message }}</x-theme::alert>
        @enderror

        @unless ($atLimit)
            <div class="flex justify-end">
                <x-theme::button type="button" x-on:click="open = 'new'" x-show="open !== 'new'">
                    <x-theme::icon name="plus" size="size-4" /> {{ trans('customer::customer_front.buttons.add_address') }}
                </x-theme::button>
            </div>

            <div x-show="open === 'new'" x-cloak>
                <x-theme::card>
                    <h2 class="mb-5 text-base font-semibold text-ink">{{ trans('customer::customer_front.titles.new_address') }}</h2>
                    @include('customer::frontend.account.partials.address-form', ['address' => null, 'useOld' => ! $editingId])
                </x-theme::card>
            </div>
        @endunless

        @if ($addresses->isEmpty())
            <x-theme::empty-state icon="map-pin" x-show="open !== 'new'" :title="trans('customer::customer_front.messages.no_addresses')" :text="trans('customer::customer_front.messages.no_addresses_text')" />
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($addresses as $address)
                    <li x-bind:class="open === {{ $address->id }} && 'md:col-span-2'">
                        <x-theme::card padding="p-5" class="h-full">
                            <div x-show="open !== {{ $address->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-theme::chip :icon="match ($address->tag) { 'home' => 'home', 'work' => 'store', default => 'map-pin' }">
                                            {{ in_array($address->tag, \Modules\Customer\Models\CustomerAddress::TAGS, true) ? trans('customer::customer_front.address.tags.'.$address->tag) : $address->tag }}
                                        </x-theme::chip>
                                        @if ($address->isDefault())
                                            <x-theme::badge color="accent">{{ trans('customer::customer_front.labels.default') }}</x-theme::badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button type="button" x-on:click="open = {{ $address->id }}" class="rounded-lg p-2 text-muted hover:bg-surface-alt hover:text-ink" aria-label="{{ trans('customer::customer_front.buttons.edit') }}">
                                            <x-theme::icon name="pencil" size="size-4" />
                                        </button>
                                        <form method="POST" action="{{ frontRoute('customer.addresses.destroy', [$address->id]) }}"
                                            x-on:submit="if (! confirm(@js(trans('customer::customer_front.messages.confirm_delete')))) $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg p-2 text-muted hover:bg-danger-soft hover:text-danger" aria-label="{{ trans('customer::customer_front.buttons.delete') }}">
                                                <x-theme::icon name="trash" size="size-4" />
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @if ($address->name)
                                    <p class="mt-3 font-medium text-ink">{{ $address->name }}</p>
                                @endif
                                <address class="mt-1 text-sm leading-6 text-ink-soft not-italic">{{ $address->summary() }}</address>
                                @if ($address->phone)
                                    <p class="tabular mt-1 text-sm text-muted">{{ \Modules\Customer\Support\PhoneNumber::display($address->phone) }}</p>
                                @endif
                            </div>
                            <div x-show="open === {{ $address->id }}" x-cloak>
                                <h2 class="mb-5 text-base font-semibold text-ink">{{ trans('customer::customer_front.titles.edit_address') }}</h2>
                                @include('customer::frontend.account.partials.address-form', ['address' => $address, 'useOld' => (string) $editingId === (string) $address->id])
                            </div>
                        </x-theme::card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
