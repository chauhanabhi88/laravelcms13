@extends('theme::layouts.seller.master')

@section('title', $warehouse ? trans('seller::panel.warehouses.edit') : trans('seller::panel.warehouses.create'))

@section('content')
    <form method="POST" action="{{ $warehouse ? frontRoute('seller.warehouse.update', [$warehouse->id]) : frontRoute('seller.warehouse.store') }}"
        class="max-w-3xl rounded-card border border-line bg-surface p-5 shadow-card" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        @if ($warehouse)
            @method('PUT')
        @endif

        @include('seller::seller.partials.warehouse-form', ['warehouse' => $warehouse])

        @unless ($warehouse?->isDefault())
            <label class="mt-5 flex items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" name="make_default" value="1" @checked(old('make_default')) class="size-4 rounded border-line-strong accent-accent">
                {{ trans('seller::panel.warehouses.make_default_label') }}
            </label>
        @endunless

        <div class="mt-6 flex justify-end gap-3 border-t border-line pt-5">
            <x-theme::button variant="ghost" :href="frontRoute('seller.warehouse.index')">{{ trans('seller::panel.common.cancel') }}</x-theme::button>
            <x-theme::button loading="loading">{{ trans('seller::panel.warehouses.save') }}</x-theme::button>
        </div>
    </form>
@endsection
