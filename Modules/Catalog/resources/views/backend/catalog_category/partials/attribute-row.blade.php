{{--
    One editable mapping row. $attributeId may be "__ID__" (the JS template), $row an array of the
    current values, $attribute the catalogue entry (null in the template).
--}}
@php
    $yes = (int) config('core.yes');
    $checked = fn (string $flag): string => (int) ($row[$flag] ?? 0) === $yes ? 'checked' : '';
    $allowed = array_map('intval', (array) ($row['allowed_option_ids'] ?? []));
@endphp
<tr data-mapping-row="{{ $attributeId }}">
    <td>
        <strong data-attribute-name>{{ $attribute['name'] ?? '' }}</strong>
        <div class="text-muted small" data-attribute-code>{{ $attribute['code'] ?? '' }}</div>
    </td>
    <td data-attribute-type>{{ $attribute['input_type'] ?? '' }}</td>
    @foreach (['is_variant_axis', 'is_filterable', 'is_required', 'show_on_card'] as $flag)
        <td class="text-center">
            <input type="checkbox" name="mappings[{{ $attributeId }}][{{ $flag }}]" value="{{ $yes }}" {{ $checked($flag) }}
                aria-label="{{ trans('catalog::catalog_category.attributes.'.$flag) }}" @if ($flag === 'is_variant_axis') data-axis-flag @endif>
        </td>
    @endforeach
    <td style="width: 90px">
        <input type="number" min="0" max="9999" class="form-control form-control-sm" name="mappings[{{ $attributeId }}][sort_order]" value="{{ (int) ($row['sort_order'] ?? 0) }}" aria-label="{{ trans('catalog::catalog_category.attributes.sort_order') }}">
    </td>
    <td style="min-width: 220px">
        <select multiple class="form-control form-control-sm" name="mappings[{{ $attributeId }}][allowed_option_ids][]" data-allowed-options size="4"
            aria-label="{{ trans('catalog::catalog_category.attributes.allowed_options') }}">
            @foreach (($attribute['options'] ?? []) as $optionId => $optionName)
                <option value="{{ $optionId }}" {{ in_array((int) $optionId, $allowed, true) ? 'selected' : '' }}>{{ $optionName }}</option>
            @endforeach
        </select>
        <div class="small text-muted">{{ trans('catalog::catalog_category.attributes.allowed_help') }}</div>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="{{ trans('catalog::catalog_category.attributes.remove') }}"><i class="fas fa-times"></i></button>
    </td>
</tr>
