{{--
    Similar products and "More from this seller" (FR-PDP-009): each row loads its cards from
    storefront.product.similar when it scrolls into view and stays hidden when empty.
--}}
@foreach ([\Modules\Storefront\Services\SimilarProductsService::SIMILAR, \Modules\Storefront\Services\SimilarProductsService::SELLER] as $rowType)
    <section class="mt-10" aria-labelledby="pdp-row-{{ $rowType }}"
        x-data="lazyProductRow({ url: @js(frontRoute('storefront.product.similar', [$product->id, ['type' => $rowType]])) })" x-show="state !== 'empty'">
        <h2 id="pdp-row-{{ $rowType }}" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
            {{ trans('storefront::product.similar.'.$rowType) }}
        </h2>
        <div x-show="state === 'idle' || state === 'loading'" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5" aria-hidden="true">
            @for ($index = 0; $index < 5; $index++)
                <div @class(['aspect-[3/4] animate-pulse rounded-card bg-surface-alt', 'hidden sm:block' => $index >= 2, 'hidden lg:block' => $index === 4])></div>
            @endfor
            <span class="sr-only">{{ trans('storefront::product.similar.loading') }}</span>
        </div>
        <div x-ref="items" class="mt-4"></div>
    </section>
@endforeach
