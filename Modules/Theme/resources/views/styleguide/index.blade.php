@extends('theme::layouts.frontend.master')

@section('title', 'Styleguide')

@section('meta')
    <meta name="robots" content="noindex">
@endsection

@php
    $tokens = [
        'bg-accent' => 'accent', 'bg-accent-hover' => 'accent-hover', 'bg-accent-soft' => 'accent-soft',
        'bg-deal' => 'deal', 'bg-deal-soft' => 'deal-soft', 'bg-rating' => 'rating',
        'bg-success' => 'success', 'bg-danger' => 'danger', 'bg-warning' => 'warning',
        'bg-ink' => 'ink', 'bg-ink-soft' => 'ink-soft', 'bg-muted' => 'muted',
        'bg-canvas' => 'canvas', 'bg-surface' => 'surface', 'bg-surface-alt' => 'surface-alt',
        'bg-line' => 'line', 'bg-line-strong' => 'line-strong',
    ];
    $icons = [
        'menu', 'x', 'check', 'check-circle', 'alert', 'info', 'chevron-right', 'chevron-left', 'chevron-down', 'chevron-up',
        'arrow-right', 'arrow-left', 'search', 'user', 'calendar', 'chat', 'mail', 'phone', 'map-pin', 'lock', 'logout', 'home',
        'plus', 'minus', 'pencil', 'trash', 'eye', 'eye-off', 'document', 'spinner', 'heart', 'heart-filled', 'cart', 'truck',
        'rotate-ccw', 'banknote', 'tag', 'star', 'star-filled', 'share', 'whatsapp', 'filter', 'sort', 'grid', 'package', 'store',
        'wallet', 'bell', 'upload', 'download', 'shield-check', 'clock', 'zoom-in', 'image', 'smartphone', 'credit-card', 'help',
        'trending-up', 'trending-down', 'settings', 'panel-left',
    ];
@endphp

@section('content')
    <div class="container-page space-y-14 py-10">
        <header>
            <h1 class="text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">Styleguide</h1>
            <p class="mt-2 text-muted">Every storefront component with sample data. Switch the OS colour scheme to check dark mode. Local environment only.</p>
            @if (\Illuminate\Support\Facades\Route::has('theme.styleguide.seller'))
                <p class="mt-2 text-sm"><a href="{{ frontRoute('theme.styleguide.seller') }}" class="font-medium text-accent hover:underline">Supplier panel layout preview →</a></p>
            @endif
        </header>

        <section class="space-y-4">
            <x-theme::section-heading title="Colour tokens" />
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($tokens as $class => $label)
                    <div class="overflow-hidden rounded-card border border-line bg-surface">
                        <div class="{{ $class }} h-14"></div>
                        <p class="px-2 py-1.5 text-xs text-ink-soft">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Typography" />
            <div class="space-y-3 rounded-card border border-line bg-surface p-6">
                <p class="text-[28px] leading-[34px] font-bold sm:text-[40px] sm:leading-[48px] sm:font-extrabold">Display: Everyday value, delivered</p>
                <p class="text-xl font-semibold sm:text-2xl">H1: Printed Cotton Straight Kurti</p>
                <p class="text-lg font-bold sm:text-[22px]">H2: Picked for You</p>
                <p class="text-base font-semibold">H3: Sold by a verified supplier</p>
                <p class="text-sm sm:text-base">Body: Soft breathable cotton with a relaxed straight fit, side pockets and a printed yoke.</p>
                <p class="text-xs font-medium text-muted">Small / meta: Delivery by Thu, 30 Oct</p>
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Buttons, badges & chips" />
            <div class="flex flex-wrap items-center gap-3">
                <x-theme::button type="button">Buy Now</x-theme::button>
                <x-theme::button type="button" variant="secondary">Add to Cart</x-theme::button>
                <x-theme::button type="button" variant="ghost">Ghost</x-theme::button>
                <x-theme::button type="button" variant="danger">Remove</x-theme::button>
                <x-theme::button type="button" size="sm">Small</x-theme::button>
                <x-theme::button type="button" size="lg">Large</x-theme::button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-theme::badge>Neutral</x-theme::badge>
                <x-theme::badge color="accent">Accent</x-theme::badge>
                <x-theme::badge color="success">Delivered</x-theme::badge>
                <x-theme::badge color="warning">Pending</x-theme::badge>
                <x-theme::badge color="danger">Cancelled</x-theme::badge>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-theme::chip>Free Delivery</x-theme::chip>
                <x-theme::chip variant="deal" icon="tag">Deal of the day</x-theme::chip>
                <x-theme::chip variant="success" icon="check">In stock</x-theme::chip>
                <x-theme::chip variant="danger">Out of stock</x-theme::chip>
                <x-theme::chip variant="accent" icon="banknote">COD</x-theme::chip>
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Price & rating" />
            <div class="space-y-3 rounded-card border border-line bg-surface p-6">
                <x-theme::price price="249" mrp="999" size="lg" />
                <x-theme::price price="249" mrp="999" />
                <x-theme::price price="1249.50" mrp="1499" size="sm" />
                <x-theme::price price="399" mrp="399" />
                <div class="flex flex-wrap items-center gap-4">
                    <x-theme::rating-badge value="4.1" count="2341" />
                    <x-theme::rating-badge value="3.2" />
                    <span class="text-xs text-muted">(count 0 renders nothing →)</span>
                    <x-theme::rating-badge value="4.5" :count="0" />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Product cards" />
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
                @foreach ($products as $product)
                    <x-theme::product-card :product="$product" />
                @endforeach
            </div>
            <x-theme::skeleton variant="card" class="max-w-xs" />
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Carousel" />
            <x-theme::carousel label="Similar products" :autoplay="5000">
                @foreach ($products as $product)
                    <div><x-theme::product-card :product="$product" /></div>
                @endforeach
            </x-theme::carousel>
        </section>

        <section class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-4">
                <x-theme::section-heading title="Tabs" />
                <x-theme::tabs :tabs="['details' => 'Product details', 'specs' => 'Specifications', 'returns' => 'Returns']" active="details" label="Product information">
                    <x-theme::tabs.panel name="details"><p class="text-sm text-ink-soft">Soft cotton, relaxed fit, machine wash.</p></x-theme::tabs.panel>
                    <x-theme::tabs.panel name="specs"><p class="text-sm text-ink-soft">Fabric: Cotton · Sleeve: Three-quarter · Pattern: Printed</p></x-theme::tabs.panel>
                    <x-theme::tabs.panel name="returns"><p class="text-sm text-ink-soft">7-day return and exchange.</p></x-theme::tabs.panel>
                </x-theme::tabs>
            </div>
            <div class="space-y-4">
                <x-theme::section-heading title="Accordion" />
                <x-theme::accordion class="rounded-card border border-line bg-surface px-4">
                    <x-theme::accordion.item title="How do I track my order?" :open="true">
                        <p class="text-sm text-ink-soft">Open My Orders and pick the order to see its timeline.</p>
                    </x-theme::accordion.item>
                    <x-theme::accordion.item title="Can I pay on delivery?">
                        <p class="text-sm text-ink-soft">Yes, for eligible pincodes and order values.</p>
                    </x-theme::accordion.item>
                </x-theme::accordion>
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Drawers & modal" text="Keyboard: Tab stays inside, Esc closes, focus returns to the trigger." />
            <div x-data class="flex flex-wrap gap-3">
                <x-theme::button type="button" variant="secondary" x-on:click="$dispatch('open-drawer', 'styleguide-left')">Left drawer</x-theme::button>
                <x-theme::button type="button" variant="secondary" x-on:click="$dispatch('open-drawer', 'styleguide-right')">Right drawer</x-theme::button>
                <x-theme::button type="button" variant="secondary" x-on:click="$dispatch('open-drawer', 'styleguide-bottom')">Bottom sheet</x-theme::button>
                <x-theme::button type="button" x-on:click="$dispatch('open-modal', 'styleguide-modal')">Modal</x-theme::button>
                <x-theme::button type="button" variant="ghost" x-on:click="$store.toast.push('Added to your wishlist')">Toast</x-theme::button>
            </div>
            <x-theme::drawer name="styleguide-left" title="Filters"><p class="p-4 text-sm text-ink-soft">Left drawer content.</p></x-theme::drawer>
            <x-theme::drawer name="styleguide-right" side="right" title="Your cart"><p class="p-4 text-sm text-ink-soft">Mini-cart content.</p></x-theme::drawer>
            <x-theme::drawer name="styleguide-bottom" side="bottom" title="Sort by">
                <div class="space-y-1 p-4 text-sm">
                    @foreach (['Relevance', 'New arrivals', 'Price: low to high', 'Price: high to low'] as $option)
                        <label class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-surface-alt"><input type="radio" name="sg_sort" class="accent-accent"> {{ $option }}</label>
                    @endforeach
                </div>
            </x-theme::drawer>
            <x-theme::modal name="styleguide-modal" title="Size chart">
                <p class="text-sm text-ink-soft">Measurements in inches.</p>
                <x-slot:footer>
                    <x-theme::button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'styleguide-modal')">Close</x-theme::button>
                </x-slot:footer>
            </x-theme::modal>
        </section>

        <section class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-4">
                <x-theme::section-heading title="Stepper" />
                <x-theme::stepper :steps="['Cart', 'Address', 'Payment', 'Summary']" :current="2" />
            </div>
            <div class="space-y-4">
                <x-theme::section-heading title="Timeline" />
                <x-theme::timeline :events="$timeline" />
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Form controls" />
            <div class="grid gap-8 rounded-card border border-line bg-surface p-6 md:grid-cols-2">
                <div class="space-y-6">
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-ink">Quantity</p>
                        <x-theme::qty-stepper name="sg_qty" :value="1" :min="1" :max="5" />
                    </div>
                    <x-theme::star-input name="sg_rating" :value="4" />
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-ink">Colour</p>
                        <div class="flex gap-2">
                            @foreach ($swatches as $swatch)
                                <x-theme::swatch :color="$swatch['color']" :label="$swatch['label']" :selected="$swatch['selected'] ?? false" :disabled="$swatch['disabled'] ?? false" />
                            @endforeach
                        </div>
                    </div>
                    <x-theme::pincode-checker />
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-ink">Price</p>
                        <x-theme::price-range :min="0" :max="5000" :from="199" :to="1999" :step="50" />
                    </div>
                    <x-theme::dropzone name="sg_photos" label="Upload photos" :max-files="5" :max-size-mb="5" />
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Stat cards" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-theme::stat-card label="Orders today" value="128" :delta="12.5" icon="package" />
                <x-theme::stat-card label="Pending acceptance" value="14" icon="clock" href="#" />
                <x-theme::stat-card label="Returns" value="3" :delta="-4" icon="rotate-ccw" />
                <x-theme::stat-card label="Upcoming payout" value="₹48,250" icon="wallet" />
            </div>
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Trust strip" />
        </section>
    </div>

    @include('theme::layouts.frontend.partials.trust-strip')

    <div class="container-page space-y-14 py-10">
        <section class="space-y-4">
            <x-theme::section-heading title="Feedback" />
            <x-theme::alert type="info" title="Heads up">Prices include GST.</x-theme::alert>
            <x-theme::alert type="success">Your address was saved.</x-theme::alert>
            <x-theme::alert type="warning">Only 3 left in stock.</x-theme::alert>
            <x-theme::alert type="error">This pincode isn't serviceable yet. Try another address.</x-theme::alert>
            <x-theme::empty-state icon="cart" title="Your cart is empty" text="Items you add will show up here." />
        </section>

        <section class="space-y-4">
            <x-theme::section-heading title="Icons" />
            <ul class="grid grid-cols-4 gap-3 sm:grid-cols-8 lg:grid-cols-10">
                @foreach ($icons as $icon)
                    <li class="flex flex-col items-center gap-1.5 rounded-lg border border-line bg-surface p-3 text-ink-soft">
                        <x-theme::icon :name="$icon" />
                        <span class="text-[10px] text-muted">{{ $icon }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
