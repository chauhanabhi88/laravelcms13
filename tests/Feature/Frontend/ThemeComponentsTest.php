<?php

use Modules\Core\Support\Money;

test('price shows the selling price, the struck MRP and the rounded-down discount', function () {
    $this->blade('<x-theme::price :price="$price" :mrp="$mrp" />', ['price' => '249', 'mrp' => '999'])
        ->assertSee('₹249')
        ->assertSee('<del', false)
        ->assertSee('₹999')
        ->assertSee('75% off');
});

test('price accepts Money and Indian digit grouping', function () {
    $this->blade('<x-theme::price :price="$price" :mrp="$mrp" />', ['price' => Money::of('123456'), 'mrp' => Money::of('246912')])
        ->assertSee('₹1,23,456')
        ->assertSee('₹2,46,912')
        ->assertSee('50% off');
});

test('price hides the MRP when it is not above the selling price', function (?string $mrp) {
    $this->blade('<x-theme::price price="399" :mrp="$mrp" />', ['mrp' => $mrp])
        ->assertSee('₹399')
        ->assertDontSee('<del', false)
        ->assertDontSee('% off');
})->with(['equal' => '399', 'lower' => '299', 'missing' => null]);

test('the rating badge shows the score and count, and is hidden without ratings', function () {
    $this->blade('<x-theme::rating-badge value="4.1" :count="2341" />')
        ->assertSee('4.1')
        ->assertSee('2,341 ratings')
        ->assertSee('bg-rating', false);

    expect(trim((string) $this->blade('<x-theme::rating-badge value="4.5" :count="0" />')))->toBe('');
});

test('the product card links to the product with alt text and a lazy image', function () {
    $product = [
        'id' => 42,
        'name' => 'Printed Cotton Kurti',
        'url' => 'https://example.test/p/printed-cotton-kurti-42',
        'image' => 'https://example.test/images/kurti.jpg',
        'price' => 249,
        'mrp' => 999,
        'rating' => 4.1,
        'rating_count' => 120,
        'free_delivery' => true,
        'cod' => true,
    ];

    $this->blade('<x-theme::product-card :product="$product" />', ['product' => $product])
        ->assertSee('href="https://example.test/p/printed-cotton-kurti-42"', false)
        ->assertSee('alt="Printed Cotton Kurti"', false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('aspect-[3/4]', false)
        ->assertSee('75% off')
        ->assertSee('Free Delivery')
        ->assertSee('COD')
        ->assertSee('wishlist-toggle', false);
});

test('chips map variants to token classes', function () {
    $this->blade('<x-theme::chip variant="deal" icon="tag">Deal</x-theme::chip>')
        ->assertSee('bg-deal-soft', false)
        ->assertSee('Deal');
});

test('the stepper marks the current step', function () {
    $this->blade('<x-theme::stepper :steps="[\'Cart\', \'Address\', \'Payment\']" :current="2" />')
        ->assertSee('Step 2 of 3')
        ->assertSee('aria-current="step"', false);
});

test('the timeline renders every event', function () {
    $this->blade('<x-theme::timeline :events="$events" />', ['events' => [
        ['label' => 'Ordered', 'at' => now(), 'state' => 'done'],
        ['label' => 'Shipped', 'at' => null, 'state' => 'current', 'note' => 'On the way'],
        ['label' => 'Delivered', 'at' => null, 'state' => 'pending'],
    ]])
        ->assertSeeInOrder(['Ordered', 'Shipped', 'On the way', 'Delivered']);
});

test('interactive components render their Alpine hooks', function (string $blade, string $hook) {
    $this->withViewErrors([])->blade($blade)->assertSee($hook, false);
})->with([
    'drawer' => ['<x-theme::drawer name="filters" title="Filters">x</x-theme::drawer>', "overlay({ name: 'filters' })"],
    'modal' => ['<x-theme::modal name="size" title="Size chart">x</x-theme::modal>', 'open-modal.window'],
    'tabs' => ['<x-theme::tabs :tabs="[\'a\' => \'A\']"><x-theme::tabs.panel name="a">x</x-theme::tabs.panel></x-theme::tabs>', 'role="tablist"'],
    'accordion' => ['<x-theme::accordion><x-theme::accordion.item title="Q">A</x-theme::accordion.item></x-theme::accordion>', 'aria-expanded'],
    'carousel' => ['<x-theme::carousel><div>1</div></x-theme::carousel>', 'carousel({ autoplay: 0 })'],
    'qty stepper' => ['<x-theme::qty-stepper name="qty" :value="2" :max="5" />', 'qtyStepper({ value: 2, min: 1, max: 5 })'],
    'star input' => ['<x-theme::star-input name="rating" />', 'type="radio"'],
    'dropzone' => ['<x-theme::dropzone name="photos" />', 'type="file"'],
    'price range' => ['<x-theme::price-range :min="0" :max="1000" />', 'name="price_from"'],
    'pincode checker' => ['<x-theme::pincode-checker />', 'pincodeChecker('],
    'stat card' => ['<x-theme::stat-card label="Orders" value="12" :delta="5" />', '+5%'],
    'swatch' => ['<x-theme::swatch color="#1E3A8A" label="Navy" />', 'background-color: #1E3A8A'],
]);
