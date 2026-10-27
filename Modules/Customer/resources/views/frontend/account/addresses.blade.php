@extends('customer::frontend.account.layout')

@php
    $defaultFlag = (int) config('customer.is_default_address.yes');
    /* Re-open the right form after a failed submit (old input is flashed). */
    $editingId = old('address_id');
    $showForm = $errors->any() || old('address');
@endphp

@section('title', trans('customer::customer_front.titles.addresses'))
@section('heading', trans('customer::customer_front.titles.addresses'))

@section('account')
    <div x-data="{ open: @js($showForm ? ($editingId ?: 'new') : null) }" class="space-y-6">
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

        @if ($addresses->isEmpty())
            <x-theme::empty-state icon="map-pin" x-show="open !== 'new'" :title="trans('customer::customer_front.messages.no_addresses')" :text="trans('customer::customer_front.messages.no_addresses_text')" />
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($addresses as $address)
                    <li>
                        <x-theme::card padding="p-5" class="h-full">
                            <div x-show="open !== {{ $address->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <x-theme::icon name="map-pin" size="size-4" class="text-muted" />
                                        <span class="font-medium text-ink">{{ $address->tag }}</span>
                                        @if ((int) $address->is_default_address === $defaultFlag)
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
                                <address class="mt-3 text-sm leading-6 text-ink-soft not-italic">
                                    {{ collect([$address->unit_no, $address->building])->filter()->implode(', ') }}<br>
                                    {{ $address->street_name }}<br>
                                    {{ $address->postal_code }}
                                </address>
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
