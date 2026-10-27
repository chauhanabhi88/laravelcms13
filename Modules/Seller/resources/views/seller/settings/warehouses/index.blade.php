@extends('theme::layouts.seller.master')

@section('title', trans('seller::panel.warehouses.title'))

@section('page_actions')
    <x-theme::button size="sm" :href="frontRoute('seller.warehouse.create')">
        <x-theme::icon name="plus" size="size-4" /> {{ trans('seller::panel.warehouses.add') }}
    </x-theme::button>
@endsection

@section('content')
    @if ($warehouses->isEmpty())
        <x-theme::empty-state icon="map-pin" :title="trans('seller::panel.warehouses.empty')" />
    @else
        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($warehouses as $warehouse)
                <li class="flex flex-col rounded-card border border-line bg-surface p-5 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ $warehouse->name }}</h2>
                        @if ($warehouse->isDefault())
                            <x-theme::chip variant="accent">{{ trans('seller::panel.warehouses.default') }}</x-theme::chip>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-ink-soft">{{ $warehouse->fullAddress() }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $warehouse->contact_name }} · {{ \Modules\Seller\Support\Phone::display($warehouse->contact_phone) }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.warehouse.edit', [$warehouse->id])">
                            <x-theme::icon name="pencil" size="size-4" /> {{ trans('seller::panel.common.edit') }}
                        </x-theme::button>
                        @unless ($warehouse->isDefault())
                            <form method="POST" action="{{ frontRoute('seller.warehouse.default', [$warehouse->id]) }}">
                                @csrf
                                <x-theme::button variant="ghost" size="sm">{{ trans('seller::panel.warehouses.make_default') }}</x-theme::button>
                            </form>
                            <form method="POST" action="{{ frontRoute('seller.warehouse.destroy', [$warehouse->id]) }}" class="ml-auto"
                                x-data x-on:submit="if (! confirm(@js(trans('seller::panel.warehouses.delete_confirm')))) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <x-theme::button variant="ghost" size="sm" class="text-danger">
                                    <x-theme::icon name="trash" size="size-4" /> {{ trans('seller::panel.warehouses.delete') }}
                                </x-theme::button>
                            </form>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
