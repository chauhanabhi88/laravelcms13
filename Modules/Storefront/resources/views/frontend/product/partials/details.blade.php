{{--
    Product details (FR-PDP-006): highlights + specifications, description, manufacturer &
    origin (legal metrology, NFR-CMP-04) and the return policy, as native disclosure rows
    (no JS needed; "Product Details" starts open).
--}}
@php
    $highlights = $page->highlights();
    $description = $page->description();
@endphp
<div class="mt-6 divide-y divide-line rounded-card border border-line bg-surface">
    <details open class="group px-4 py-3">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-base font-semibold text-ink [&::-webkit-details-marker]:hidden">
            {{ trans('storefront::product.details.title') }}
            <x-theme::icon name="chevron-down" size="size-5" class="text-muted transition group-open:rotate-180" />
        </summary>
        <div class="mt-3 space-y-4 pb-1">
            @if ($highlights !== [])
                <div>
                    <h3 class="text-sm font-semibold text-ink">{{ trans('storefront::product.details.highlights') }}</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-soft marker:text-muted">
                        @foreach ($highlights as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($page->specs !== [] || $page->brand !== null)
                <div>
                    <h3 class="text-sm font-semibold text-ink">{{ trans('storefront::product.details.specifications') }}</h3>
                    <dl class="mt-2 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-2 text-sm">
                        @if ($page->brand !== null)
                            <dt class="text-muted">{{ trans('storefront::product.details.brand') }}</dt>
                            <dd class="text-ink">{{ $page->brand['name'] }}</dd>
                        @endif
                        @foreach ($page->specs as $spec)
                            <dt class="text-muted">{{ $spec['name'] }}</dt>
                            <dd class="text-ink">{{ $spec['value'] }}</dd>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    </details>

    @if ($description !== null)
        <details class="group px-4 py-3">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-base font-semibold text-ink [&::-webkit-details-marker]:hidden">
                {{ trans('storefront::product.details.description') }}
                <x-theme::icon name="chevron-down" size="size-5" class="text-muted transition group-open:rotate-180" />
            </summary>
            <div class="mt-3 pb-1 text-sm leading-6 text-ink-soft">{!! nl2br(e($description)) !!}</div>
        </details>
    @endif

    <details class="group px-4 py-3">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-base font-semibold text-ink [&::-webkit-details-marker]:hidden">
            {{ trans('storefront::product.details.manufacturer') }}
            <x-theme::icon name="chevron-down" size="size-5" class="text-muted transition group-open:rotate-180" />
        </summary>
        <dl class="mt-3 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-2 pb-1 text-sm">
            @foreach ($page->manufacturerRows() as $row)
                <dt class="text-muted">{{ $row['label'] }}</dt>
                <dd class="text-ink">{{ $row['value'] }}</dd>
            @endforeach
        </dl>
    </details>

    <details id="pdp-returns" class="group px-4 py-3">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-base font-semibold text-ink [&::-webkit-details-marker]:hidden">
            {{ trans('storefront::product.returns.title') }}
            <x-theme::icon name="chevron-down" size="size-5" class="text-muted transition group-open:rotate-180" />
        </summary>
        <div class="mt-3 space-y-1 pb-1 text-sm">
            <p class="font-medium text-ink">{{ $page->returnPolicy->summary() }}</p>
            @if ($page->returnPolicy->allowsReturns())
                <p class="text-ink-soft">{{ trans('storefront::product.returns.note') }}</p>
            @endif
        </div>
    </details>
</div>
