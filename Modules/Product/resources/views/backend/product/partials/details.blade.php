{{--
    Read-only product sections shared by the product detail page and the QC review screen.
    $product, $labels, $presenter, $specRows, $categoryLabel.
--}}
@php
    $none = trans('product::product.labels.none');
    $yesNo = fn ($value) => (int) $value === (int) config('core.yes') ? trans('product::product.labels.yes') : trans('product::product.labels.no');
    $party = fn (?array $details) => $details ? trim(($details['name'] ?? '').(! empty($details['address']) ? ', '.$details['address'] : ''), ', ') : null;
    $highlights = (array) ($product->highlights ?? []);
@endphp

{{-- Listing --}}
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.listing') }}</strong></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.name') }}</dt>
            <dd class="col-sm-8">{{ $product->name ?: $none }}</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.category') }}</dt>
            <dd class="col-sm-8">{{ $categoryLabel ?: $none }}</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.brand') }}</dt>
            <dd class="col-sm-8">{{ $product->brand?->name ?: $none }}</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.seller') }}</dt>
            <dd class="col-sm-8">
                @if ($product->seller)
                    @can('admin.seller.show')
                        <a href="{{ route('admin.seller.show', updateUrlParams([$product->seller->id])) }}">{{ $product->seller->displayName() }}</a>
                    @else
                        {{ $product->seller->displayName() }}
                    @endcan
                    <small class="text-muted">{{ $product->seller->code }}</small>
                @else
                    {{ $none }}
                @endif
            </dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.storefront') }}</dt>
            <dd class="col-sm-8">
                @if ($product->isVisibleOnStorefront())
                    <span class="badge badge-success">{{ trans('product::product.labels.visible') }}</span>
                @else
                    <span class="badge badge-secondary">{{ trans('product::product.labels.hidden') }}</span>
                @endif
                @if ($product->seller_active !== (int) config('core.yes'))
                    <span class="badge badge-dark">{{ trans('product::product.labels.seller_suspended') }}</span>
                @endif
            </dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.price_range') }}</dt>
            <dd class="col-sm-8">
                @if ($product->min_price->isZero())
                    {{ $none }}
                @else
                    {{ $product->min_price->format(true) }}@if (! $product->max_price->equals($product->min_price)) – {{ $product->max_price->format(true) }}@endif
                    <small class="text-muted">{{ trans('product::product.labels.mrp') }} <s>{{ $product->min_mrp->format(true) }}</s></small>
                @endif
            </dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.discount') }}</dt>
            <dd class="col-sm-8">{{ $product->max_discount_percent }}%</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.available') }}</dt>
            <dd class="col-sm-8">{{ $product->total_stock }}</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.slug') }}</dt>
            <dd class="col-sm-8"><code>{{ $product->slug }}-{{ $product->id }}</code></dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.published_at') }}</dt>
            <dd class="col-sm-8">{{ $product->published_at ? getFormatedDate($product->published_at, getGridDateFormat()) : $none }}</dd>
            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.rating') }} · {{ trans('product::product.labels.sold') }}</dt>
            <dd class="col-sm-8">{{ $product->rating_avg }} ({{ $product->rating_count }}) · {{ $product->sold_count }}</dd>
        </dl>

        @if ($highlights !== [])
            <h6 class="mt-4">{{ trans('product::product.labels.highlights') }}</h6>
            <ul class="mb-0">
                @foreach ($highlights as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @if ($product->description)
            <h6 class="mt-4">{{ trans('product::product.labels.description') }}</h6>
            <div class="small" style="white-space: pre-line">{{ $product->description }}</div>
        @endif
    </div>
</div>

{{-- Images --}}
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.images') }}</strong> <span class="text-muted">({{ $product->images->count() }})</span></div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            @forelse ($product->images as $image)
                <figure class="mr-3 mb-3 text-center" style="width: 150px">
                    <a href="{{ $image->url() }}" target="_blank" rel="noopener">
                        <img src="{{ $image->url() }}" alt="{{ $image->alt }}" class="img-thumbnail" style="width: 150px; height: 200px; object-fit: contain">
                    </a>
                    <figcaption class="small text-muted">
                        @if ($image->path === $product->primary_image)
                            <span class="badge badge-info">{{ trans('product::product.labels.primary') }}</span>
                        @endif
                        {{ $image->attribute_option_id ? ($labels['options'][$image->attribute_option_id] ?? '') : '' }}
                        @if ($image->width)
                            <div>{{ $image->width }} × {{ $image->height }}</div>
                        @endif
                    </figcaption>
                </figure>
            @empty
                <p class="mb-0 text-muted">{{ $none }}</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Variants --}}
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.variants') }}</strong> <span class="text-muted">({{ $product->variants->count() }})</span></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-striped mb-0 text-nowrap">
            <thead>
                <tr>
                    <th>{{ trans('product::product.labels.variant') }}</th>
                    <th>{{ trans('product::product.labels.sku') }}</th>
                    <th>{{ trans('product::product.labels.seller_sku') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.mrp') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.price') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.stock') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.reserved') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.available') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.threshold') }}</th>
                    <th class="text-right">{{ trans('product::product.labels.weight') }}</th>
                    <th>{{ trans('product::product.labels.dimensions') }}</th>
                    <th>{{ trans('product::product.labels.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($product->variants as $variant)
                    <tr class="{{ $variant->isEnabled() ? '' : 'text-muted' }}">
                        <td>{{ $presenter->variantLabel($variant, $labels) ?: $none }}</td>
                        <td><code>{{ $variant->sku }}</code></td>
                        <td>{{ $variant->seller_sku }}</td>
                        <td class="text-right">{{ $variant->mrp->format(true) }}</td>
                        <td class="text-right">{{ $variant->price->format(true) }}</td>
                        <td class="text-right">{{ $variant->stock }}</td>
                        <td class="text-right">{{ $variant->reserved }}</td>
                        <td class="text-right">
                            {{ $variant->available }}
                            @if ($variant->isEnabled() && $variant->isLowStock())
                                <span class="badge badge-warning">!</span>
                            @endif
                        </td>
                        <td class="text-right">{{ $variant->low_stock_threshold }}</td>
                        <td class="text-right">{{ $variant->weight_grams }}</td>
                        <td>{{ $variant->length_cm ? $variant->length_cm.' × '.$variant->breadth_cm.' × '.$variant->height_cm : $none }}</td>
                        <td>
                            <span class="badge badge-{{ $variant->isEnabled() ? 'success' : 'secondary' }}">{{ trans($variant->isEnabled() ? 'product::product.labels.enabled' : 'product::product.labels.disabled') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted">{{ $none }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Specifications --}}
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.attributes') }}</strong></div>
    <div class="card-body">
        @if ($specRows === [])
            <p class="mb-0 text-muted">{{ $none }}</p>
        @else
            <dl class="row mb-0">
                @foreach ($specRows as $label => $value)
                    <dt class="col-sm-4 text-muted font-weight-normal">{{ $label }}</dt>
                    <dd class="col-sm-8">{{ $value }}</dd>
                @endforeach
            </dl>
        @endif
    </div>
</div>

{{-- Tax & compliance --}}
<div class="card card-info card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.compliance') }}</strong></div>
    <div class="card-body">
        <dl class="row mb-0">
            @foreach ([
                'hsn_code' => $product->hsn_code,
                'gst_percent' => $product->gst_percent.'%',
                'country_of_origin' => $product->country_of_origin,
                'manufacturer_details' => $party($product->manufacturer_details),
                'packer_details' => $party($product->packer_details),
                'importer_details' => $party($product->importer_details),
                'return_window' => $product->return_window_days !== null ? $product->return_window_days : null,
                'return_type' => $product->return_type,
                'cod' => $yesNo($product->is_cod_allowed),
                'keywords' => $product->search_keywords,
            ] as $label => $value)
                <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('product::product.labels.'.$label) }}</dt>
                <dd class="col-sm-8">{{ $value !== null && $value !== '' ? $value : $none }}</dd>
            @endforeach
        </dl>
    </div>
</div>
