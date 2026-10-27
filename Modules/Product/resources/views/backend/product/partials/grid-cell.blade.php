{{-- One grid cell of a product, shared by the product and QC queue grids. --}}
@php
    $duplicateIds = $duplicates[$product->id] ?? [];
    $ageHours = $product->qcAgeHours();
@endphp
@if ($columnCode == 'primary_image')
    <td>
        <img src="{{ getImageUrl(['module' => config('product.upload.folder'), 'image' => $product->primary_image, 'defualt-image' => true]) }}" alt="" style="max-height: 40px; max-width: 40px; object-fit: cover">
    </td>
@elseif ($columnCode == 'name')
    <td>
        {{ wordWrapper((string) $product->name) }}
        @if ($duplicateIds !== [])
            <div><span class="badge badge-warning">{{ trans('product::product.labels.duplicate_of', ['ids' => implode(', #', $duplicateIds)]) }}</span></div>
        @endif
    </td>
@elseif ($columnCode == 'seller')
    <td>
        {{ wordWrapper((string) $product->seller?->displayName()) }}
        <div class="small text-muted">{{ $product->seller?->code }}</div>
    </td>
@elseif ($columnCode == 'category')
    <td>{{ wordWrapper((string) $product->category?->name) }}</td>
@elseif ($columnCode == 'price')
    <td>
        @if ($product->min_price->isZero())
            <span class="text-muted">{{ trans('product::product.labels.none') }}</span>
        @else
            {{ $product->min_price->format() }}@if (! $product->max_price->equals($product->min_price)) – {{ $product->max_price->format() }}@endif
            @if ($product->max_discount_percent > 0)
                <div class="small text-muted"><s>{{ $product->min_mrp->format() }}</s> · {{ $product->max_discount_percent }}%</div>
            @endif
        @endif
    </td>
@elseif ($columnCode == 'total_stock')
    <td>
        @if ($product->total_stock > 0)
            {{ $product->total_stock }}
        @else
            <span class="badge badge-danger">{{ trans('product::product.stock_filters.out_of_stock') }}</span>
        @endif
    </td>
@elseif ($columnCode == 'status')
    <td>
        <span class="badge badge-{{ $product->status->badge() }}">{{ $product->status->label() }}</span>
        @if ($product->isPublished() && $product->hasPendingChanges())
            <span class="badge badge-info">{{ trans('product::product.titles.pending_changes') }}</span>
        @endif
        @if ($product->seller_active !== (int) config('core.yes'))
            <span class="badge badge-dark">{{ trans('product::product.labels.seller_suspended') }}</span>
        @endif
    </td>
@elseif ($columnCode == 'queue_type')
    <td>{{ trans('product::product.queue_types.'.($product->status === \Modules\Product\Enums\ProductStatus::PendingQc ? 'new' : 'changes')) }}</td>
@elseif ($columnCode == 'submitted_at')
    <td>
        @if ($ageHours !== null)
            {{ $ageHours >= 48 ? trans('product::product.labels.days', ['days' => intdiv($ageHours, 24)]) : trans('product::product.labels.hours', ['hours' => $ageHours]) }}
            @if (isset($slaHours) && $ageHours >= $slaHours)
                <span class="badge badge-danger">{{ trans('product::product.labels.overdue') }}</span>
            @endif
        @endif
    </td>
@elseif (in_array($columnCode, ['published_at', 'created_at', 'updated_at'], true))
    <td>{{ $product->$columnCode ? getFormatedDate($product->$columnCode, getGridDateFormat()) : '' }}</td>
@else
    <td>{{ $product->$columnCode }}</td>
@endif
