@extends('theme::layouts.backend.master')

@section('title')
{{ trans('catalog::catalog_category.titles.attributes', ['name' => $catalogCategory->name]) }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans('catalog::catalog_category.titles.attributes', ['name' => $catalogCategory->name]) }}</h4>
            <p class="text-muted small mb-0">{{ $breadcrumb }}</p>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.catalog_category.index', updateUrlParams() + ['selected' => $catalogCategory->id]) }}')">{{ trans('catalog::catalog_category.attributes.back') }}</button>
                <button class="btn btn-primary btn-fw" type="submit" form="mapping_form">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
@php
    $flagIcon = fn (bool $on): string => $on ? '<i class="fas fa-check text-success"></i>' : '<span class="text-muted">—</span>';
    $oldMappings = old('mappings');
@endphp
<p class="text-muted">{{ trans('catalog::catalog_category.attributes.axes_help', ['max' => $maxAxes]) }}</p>

{{ formStart(null, 'POST', 'admin.catalog_category.attributes_save', updateUrlParams([$catalogCategory->id]), ['id' => 'mapping_form']) }}
<div class="card mb-4">
    <div class="card-header"><strong>{{ trans('catalog::catalog_category.titles.own_attributes') }}</strong></div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-sm align-middle" id="mapping-table">
            <thead>
                <tr>
                    <th>{{ trans('catalog::catalog_category.attributes.attribute') }}</th>
                    <th>{{ trans('catalog::catalog_category.attributes.input_type') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_variant_axis') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_filterable') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_required') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.show_on_card') }}</th>
                    <th>{{ trans('catalog::catalog_category.attributes.sort_order') }}</th>
                    <th>{{ trans('catalog::catalog_category.attributes.allowed_options') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-mapping-body>
                @if (is_array($oldMappings))
                    @foreach ($oldMappings as $attributeId => $row)
                        @include('catalog::backend.catalog_category.partials.attribute-row', ['attributeId' => $attributeId, 'row' => $row, 'attribute' => $attributes[$attributeId] ?? null])
                    @endforeach
                @else
                    @foreach ($ownRows as $attributeId => $mapping)
                        @include('catalog::backend.catalog_category.partials.attribute-row', ['attributeId' => $attributeId, 'row' => $mapping->toArray(), 'attribute' => $attributes[$attributeId] ?? null])
                    @endforeach
                @endif
                <tr data-empty-row @if ((is_array($oldMappings) ? $oldMappings : $ownRows) !== []) hidden @endif>
                    <td colspan="9" class="text-center text-muted">{{ trans('catalog::catalog_category.attributes.none_own') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="form-inline mt-2">
            <select class="custom-select custom-select-sm mr-2" data-add-attribute aria-label="{{ trans('catalog::catalog_category.attributes.add') }}">
                <option value="">{{ trans('catalog::catalog_category.attributes.choose') }}</option>
                @foreach ($attributes as $attribute)
                    <option value="{{ $attribute['id'] }}">{{ $attribute['name'] }} ({{ $attribute['code'] }})</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-button><i class="fas fa-plus"></i> {{ trans('catalog::catalog_category.attributes.add') }}</button>
        </div>
    </div>
</div>
{{ formEnd() }}

<div class="card">
    <div class="card-header"><strong>{{ trans('catalog::catalog_category.titles.inherited_attributes') }}</strong></div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>{{ trans('catalog::catalog_category.attributes.attribute') }}</th>
                    <th>{{ trans('catalog::catalog_category.attributes.from') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_variant_axis') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_filterable') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.is_required') }}</th>
                    <th class="text-center">{{ trans('catalog::catalog_category.attributes.show_on_card') }}</th>
                    <th>{{ trans('catalog::catalog_category.attributes.allowed_options') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inherited as $row)
                    <tr class="{{ $row['overridden'] ? 'text-muted' : '' }}" style="opacity: .75">
                        <td><strong>{{ $row['name'] }}</strong> <span class="small text-muted">{{ $row['code'] }}</span></td>
                        <td class="small">{{ $row['source_name'] }}</td>
                        <td class="text-center">{!! $flagIcon($row['is_variant_axis']) !!}</td>
                        <td class="text-center">{!! $flagIcon($row['is_filterable']) !!}</td>
                        <td class="text-center">{!! $flagIcon($row['is_required']) !!}</td>
                        <td class="text-center">{!! $flagIcon($row['show_on_card']) !!}</td>
                        <td class="small">{{ $row['allowed_option_ids'] === null ? trans('catalog::catalog_category.attributes.all_options') : collect($row['options'])->pluck('name')->implode(', ') }}</td>
                        <td>
                            @if ($row['overridden'])
                                <span class="badge badge-info">{{ trans('catalog::catalog_category.attributes.overridden') }}</span>
                            @else
                                @php
                                    $overrideValues = array_intersect_key($row, array_flip(['attribute_id', 'is_variant_axis', 'is_filterable', 'is_required', 'show_on_card', 'sort_order']))
                                        + ['allowed_option_ids' => $row['allowed_option_ids'] ?? []];
                                @endphp
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-override="{{ json_encode($overrideValues) }}">{{ trans('catalog::catalog_category.attributes.override') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ trans('catalog::catalog_category.attributes.none_inherited') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<template id="mapping-row-template">
    @include('catalog::backend.catalog_category.partials.attribute-row', ['attributeId' => '__ID__', 'row' => [], 'attribute' => null])
</template>
@stop

@push('js-stack')
<script type="text/javascript">
    (function($) {
        var catalogue = @json($attributes);
        var $body = $('[data-mapping-body]');

        function refreshEmptyState() {
            $body.find('[data-empty-row]').prop('hidden', $body.find('[data-mapping-row]').length > 0);
        }

        function addRow(values) {
            var id = String(values.attribute_id);
            var attribute = catalogue[id];
            if (!attribute || $body.find('[data-mapping-row="' + id + '"]').length) {
                return;
            }

            var html = $('#mapping-row-template').html().replace(/__ID__/g, id);
            var $row = $(html);
            $row.find('[data-attribute-name]').text(attribute.name);
            $row.find('[data-attribute-code]').text(attribute.code);
            $row.find('[data-attribute-type]').text(attribute.input_type);

            var $select = $row.find('[data-allowed-options]');
            var allowed = (values.allowed_option_ids || []).map(String);
            $.each(attribute.options, function(optionId, optionName) {
                $('<option>').val(optionId).text(optionName).prop('selected', allowed.indexOf(String(optionId)) !== -1).appendTo($select);
            });

            ['is_variant_axis', 'is_filterable', 'is_required', 'show_on_card'].forEach(function(flag) {
                $row.find('input[name$="[' + flag + ']"]').prop('checked', !!values[flag]);
            });
            $row.find('input[name$="[sort_order]"]').val(values.sort_order || 0);

            $body.find('[data-empty-row]').before($row);
            refreshEmptyState();
        }

        $('[data-add-button]').on('click', function() {
            var id = $('[data-add-attribute]').val();
            if (id) {
                addRow({ attribute_id: id, sort_order: $body.find('[data-mapping-row]').length });
            }
        });

        $(document).on('click', '[data-override]', function() {
            addRow($(this).data('override'));
            $(this).replaceWith('<span class="badge badge-info">{{ trans('catalog::catalog_category.attributes.overridden') }}</span>');
        });

        $body.on('click', '[data-remove-row]', function() {
            $(this).closest('[data-mapping-row]').remove();
            refreshEmptyState();
        });
    })(jQuery);
</script>
@endpush
