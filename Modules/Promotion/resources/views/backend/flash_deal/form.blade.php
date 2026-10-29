{{-- Flash deal form: the window and its variant rows. $deal: null on create; $items: its rows (variant + product loaded). --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('promotion::flash_deal.titles.'.($deal ? 'edit' : 'create')) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('promotion::flash_deal.titles.'.($deal ? 'edit' : 'create')) }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.flash_deal.index', updateUrlParams()) }}')">{{ trans('core::core.buttons.cancel') }}</button>
                @if ($deal)
                    @can('admin.flash_deal.delete')
                        <button type="submit" form="delete_form" class="btn btn-danger btn-fw" onclick="return confirm('{{ trans('promotion::flash_deal.messages.confirm_delete') }}')">{{ trans('promotion::flash_deal.buttons.delete') }}</button>
                    @endcan
                @endif
                <button type="submit" form="main_form" class="btn btn-primary btn-fw">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $newRows = max((int) config('promotion.flash_deal_new_rows', 5), count((array) old('new_items', [])));
@endphp
<form method="POST" id="main_form" action="{{ $deal ? route('admin.flash_deal.update', updateUrlParams([$deal->id])) : route('admin.flash_deal.store', updateUrlParams()) }}">
    @csrf
    <div class="card card-info card-outline">
        <div class="card-header"><strong>{{ trans('promotion::flash_deal.titles.general') }}</strong></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="name">{{ trans('promotion::flash_deal.fields.name') }}</label><span class="asterisk"> *</span>
                    <input type="text" id="name" name="name" maxlength="150" required value="{{ old('name', $deal?->name) }}" class="form-control @error('name') is-invalid @enderror">
                    @error('name')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="form-group col-md-3">
                    <label for="starts_at">{{ trans('promotion::flash_deal.fields.starts_at') }}</label><span class="asterisk"> *</span>
                    <input type="datetime-local" id="starts_at" name="starts_at" required value="{{ old('starts_at', $deal?->starts_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('starts_at') is-invalid @enderror">
                    @error('starts_at')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="form-group col-md-3">
                    <label for="ends_at">{{ trans('promotion::flash_deal.fields.ends_at') }}</label><span class="asterisk"> *</span>
                    <input type="datetime-local" id="ends_at" name="ends_at" required value="{{ old('ends_at', $deal?->ends_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('ends_at') is-invalid @enderror">
                    @error('ends_at')<label class="error">{{ $message }}</label>@enderror
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-9">
                    <label for="banner">{{ trans('promotion::flash_deal.fields.banner') }}</label>
                    <input type="url" id="banner" name="banner" maxlength="255" value="{{ old('banner', $deal?->banner) }}" class="form-control @error('banner') is-invalid @enderror">
                    @error('banner')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <label class="mb-2"><input type="checkbox" name="status" value="1" @checked((bool) old('status', $deal === null || $deal->isEnabled()))> {{ trans('promotion::flash_deal.fields.status') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-info card-outline">
        <div class="card-header"><strong>{{ trans('promotion::flash_deal.titles.items') }}</strong></div>
        <div class="card-body table-responsive">
            <p class="text-muted">{{ trans('promotion::flash_deal.hints.items') }}</p>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ trans('promotion::flash_deal.fields.sku') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.product') }}</th>
                        <th class="text-right">{{ trans('promotion::flash_deal.fields.price') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.deal_price') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.stock_cap') }}</th>
                        <th class="text-right">{{ trans('promotion::flash_deal.fields.sold') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.remove') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr data-deal-item="{{ $item->id }}">
                            <td>{{ $item->variant?->sku }}</td>
                            <td class="text-truncate" style="max-width: 260px;">{{ $item->product?->name ?? $item->product?->slug }}</td>
                            <td class="text-right">{{ $item->variant?->price->format(true) }}</td>
                            <td>
                                <input type="number" step="0.01" min="0" name="items[{{ $item->id }}][deal_price]" value="{{ old('items.'.$item->id.'.deal_price', $item->deal_price->toDecimal()) }}" class="form-control form-control-sm @error('items.'.$item->id.'.deal_price') is-invalid @enderror">
                                @error('items.'.$item->id.'.deal_price')<label class="error">{{ $message }}</label>@enderror
                            </td>
                            <td><input type="number" min="1" name="items[{{ $item->id }}][stock_cap]" value="{{ old('items.'.$item->id.'.stock_cap', $item->stock_cap) }}" class="form-control form-control-sm"></td>
                            <td class="text-right">{{ $item->sold }}</td>
                            <td class="text-center"><input type="checkbox" name="items[{{ $item->id }}][remove]" value="1" @checked(old('items.'.$item->id.'.remove'))></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">{{ trans('promotion::flash_deal.messages.no_items') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h6 class="mt-4">{{ trans('promotion::flash_deal.titles.add_items') }}</h6>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>{{ trans('promotion::flash_deal.fields.sku') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.deal_price') }}</th>
                        <th>{{ trans('promotion::flash_deal.fields.stock_cap') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $newRows; $i++)
                        <tr>
                            <td>
                                <input type="text" maxlength="32" name="new_items[{{ $i }}][sku]" value="{{ old('new_items.'.$i.'.sku') }}" class="form-control form-control-sm @error('new_items.'.$i.'.sku') is-invalid @enderror">
                                @error('new_items.'.$i.'.sku')<label class="error">{{ $message }}</label>@enderror
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="new_items[{{ $i }}][deal_price]" value="{{ old('new_items.'.$i.'.deal_price') }}" class="form-control form-control-sm @error('new_items.'.$i.'.deal_price') is-invalid @enderror">
                                @error('new_items.'.$i.'.deal_price')<label class="error">{{ $message }}</label>@enderror
                            </td>
                            <td><input type="number" min="1" name="new_items[{{ $i }}][stock_cap]" value="{{ old('new_items.'.$i.'.stock_cap') }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</form>
@if ($deal)
    <form method="POST" id="delete_form" action="{{ route('admin.flash_deal.delete', updateUrlParams([$deal->id])) }}">@csrf</form>
@endif
@stop
