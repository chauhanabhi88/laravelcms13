{{-- Delivery check (FR-PDP-004): serviceability, COD and estimated delivery for a pincode. --}}
<section class="mt-6 rounded-card border border-line bg-surface p-4" aria-labelledby="pdp-delivery-title">
    <h2 id="pdp-delivery-title" class="sr-only">{{ trans('storefront::product.delivery.title') }}</h2>
    <x-theme::pincode-checker :endpoint="frontRoute('storefront.delivery_check')" method="post" :product-id="$product->id"
        :variant-id="$page->selectedVariant?->id" :pincode="$pincode" />
    <ul class="mt-4 space-y-2 text-sm text-ink-soft">
        <li class="flex items-center gap-2">
            <x-theme::icon name="rotate-ccw" size="size-4" class="shrink-0 text-muted" />
            <a href="#pdp-returns" class="hover:underline">{{ $page->returnPolicy->summary() }}</a>
        </li>
        @if (\Modules\Storefront\Support\StorefrontSettings::freeDeliveryBadge())
            <li class="flex items-center gap-2">
                <x-theme::icon name="truck" size="size-4" class="shrink-0 text-muted" />
                {{ trans('theme::frontend.commerce.free_delivery') }}
            </li>
        @endif
    </ul>
    <p class="mt-3 text-xs text-muted">{{ trans('storefront::product.delivery.estimate_note') }}</p>
</section>
