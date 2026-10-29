<?php

use Illuminate\Support\Facades\DB;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Services\CheckoutService;
use Modules\Order\Services\OrderCancellationService;
use Modules\Product\Contracts\PriceResolver;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;
use Modules\Promotion\Services\PromotionOrderHooks;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Services\Home\HomePageBuilder;
use Modules\Storefront\Services\ProductCardPresenter;
use Modules\Storefront\ViewModels\ProductPageViewModel;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    promotionSetup();
});

function dealOn(ProductVariant $variant, string $price = '199', ?int $cap = null, ?FlashDeal $deal = null): FlashDealItem
{
    return FlashDealItem::factory()->forVariant($variant)->create([
        'flash_deal_id' => ($deal ?? FlashDeal::factory()->create())->id,
        'deal_price' => $price,
        'stock_cap' => $cap,
    ]);
}

test('deal prices apply only inside the window, below the own price, while units last', function () {
    $resolver = app(PriceResolver::class);
    $running = cartVariant(['price' => '499', 'mrp' => '999']);
    $scheduled = cartVariant(['price' => '499', 'mrp' => '999']);
    $ended = cartVariant(['price' => '499', 'mrp' => '999']);
    $disabled = cartVariant(['price' => '499', 'mrp' => '999']);
    $notLower = cartVariant(['price' => '150', 'mrp' => '999']);
    $soldOut = cartVariant(['price' => '499', 'mrp' => '999']);

    dealOn($running);
    dealOn($scheduled, '199', null, FlashDeal::factory()->scheduled()->create());
    dealOn($ended, '199', null, FlashDeal::factory()->ended()->create());
    dealOn($disabled, '199', null, FlashDeal::factory()->disabled()->create());
    dealOn($notLower);
    dealOn($soldOut, '199', 2)->forceFill(['sold' => 2])->save();

    $overrides = $resolver->forVariants([$running->id, $scheduled->id, $ended->id, $disabled->id, $notLower->id, $soldOut->id]);

    expect(array_keys($overrides))->toBe([$running->id])
        ->and($overrides[$running->id]->price->toDecimal())->toBe('199.00')
        ->and($overrides[$running->id]->endsAt)->not->toBeNull()
        ->and($resolver->forProducts([$running->product_id])[$running->product_id]->price->toDecimal())->toBe('199.00');
});

test('the lowest of two running deals wins', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    dealOn($variant, '299');
    dealOn($variant, '249');

    expect(app(PriceResolver::class)->forVariants([$variant->id])[$variant->id]->price->toDecimal())->toBe('249.00');
});

test('cart lines, product cards and the product page show the deal price', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    dealOn($variant, '199');
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);

    $line = cartService()->snapshot(customerCart($customer))->lines()[0];
    $card = app(ProductCardPresenter::class)->present([$variant->product_id])[0];
    $page = app(ProductPageViewModel::class)->load(Product::query()->findOrFail($variant->product_id));

    expect($line->unitPrice->toDecimal())->toBe('199.00')
        ->and(promotionTotals($customer)->subtotal->toDecimal())->toBe('398.00')
        ->and($card['price']->toDecimal())->toBe('199.00')
        ->and($page->displayPrice()->toDecimal())->toBe('199.00')
        ->and($page->dealEndsAt())->not->toBeNull()
        ->and($page->variants[0]['price'])->toBe('199.00');
});

test('placing an order takes deal units from the cap and records the deal on the item', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $deal = dealOn($variant, '199', 5);
    $customer = orderCustomer();

    $order = placeWithCoupon($customer, [[$variant, 2]], null)->order;
    $item = SalesOrderItem::query()->where('sales_order_id', $order->id)->sole();

    expect($item->price->toDecimal())->toBe('199.00')
        ->and($item->flash_deal_item_id)->toBe($deal->id)
        ->and($deal->fresh()->sold)->toBe(2);
});

test('when the cap runs out the price falls back to the variant\'s own price', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999', 'stock' => 20]);
    $deal = dealOn($variant, '199', 1);

    placeWithCoupon(orderCustomer(), [$variant], null);

    $customer = orderCustomer();
    orderCart($customer, [$variant]);

    expect($deal->fresh()->sold)->toBe(1)
        ->and(cartService()->snapshot(customerCart($customer))->lines()[0]->unitPrice->toDecimal())->toBe('499.00');
});

test('a cap taken by another buyer during checkout makes place-order refuse the deal price', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999', 'stock' => 20]);
    $deal = dealOn($variant, '199', 3);
    $customer = orderCustomer();
    orderCart($customer, [[$variant, 2]]);
    $summary = app(CheckoutService::class)->summary($customer, orderCheckout($customer));
    $order = placeWithCoupon(orderCustomer(), [$variant], null)->order;

    // Another buyer took 2 more meanwhile: 1 unit left, the stale summary wants 2.
    $deal->forceFill(['sold' => 2])->save();
    SalesOrderItem::query()->where('sales_order_id', $order->id)->update(['flash_deal_item_id' => null, 'quantity' => 2]);

    expect(fn () => DB::transaction(fn () => app(PromotionOrderHooks::class)->onPlacing($order, $summary)))
        ->toThrow(PriceChanged::class);
});

test('cancelled deal items give their units back', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $deal = dealOn($variant, '199', 5);
    $order = placeWithCoupon(orderCustomer(), [[$variant, 3]], null)->order;

    expect($deal->fresh()->sold)->toBe(3);

    app(OrderCancellationService::class)->cancelOrder($order->fresh(), OrderCancellationService::BY_CUSTOMER, 'No longer needed');

    expect($deal->fresh()->sold)->toBe(0);
});

test('the home flash-deal section shows the running deal with its countdown', function () {
    $variant = cartVariant(['price' => '499', 'mrp' => '999']);
    $deal = FlashDeal::factory()->create(['name' => 'Midnight Madness']);
    dealOn($variant, '199', null, $deal);
    $section = HomeSection::factory()->ofType('flash_deal', ['limit' => 12])->create();
    $builder = app(HomePageBuilder::class);

    $data = collect($builder->sections())->firstWhere('id', $section->id);
    $html = $builder->html($data);

    expect(in_array('flash_deal', (array) config('storefront.home_section_types'), true))->toBeTrue()
        ->and($html)->toContain('Midnight Madness')
        ->and($html)->toContain('data-flash-deal-section="'.$deal->id.'"')
        ->and($html)->toContain('₹199');

    $deal->forceFill(['ends_at' => now()->subMinute()])->save();

    expect($builder->html($data))->toBe('');
});
