<?php

use Illuminate\Support\Facades\Notification;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\DTO\ReviewSummary;
use Modules\Storefront\Support\JsonLd\ProductJsonLd;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    Notification::fake();
});

test('the product carries an aggregate offer over its variants', function () {
    ['product' => $product, 'seller' => $seller] = pdpProduct();
    $data = ProductJsonLd::product(pdpPage($product));

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('Product')
        ->and($data['name'])->toBe('Printed Cotton Kurti')
        ->and($data['url'])->toBe(StorefrontUrls::product($product->slug, $product->id))
        ->and($data['offers'])->toMatchArray([
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'INR',
            'lowPrice' => '499.00',
            'highPrice' => '599.00',
            'offerCount' => 3,
            'availability' => 'https://schema.org/InStock',
        ])
        ->and($data['offers']['seller']['name'])->toBe($seller->displayName())
        ->and($data)->not->toHaveKey('aggregateRating');
});

test('a sold-out product is marked out of stock', function () {
    ['product' => $product] = pdpProduct(fn (array $catalog) => [productVariant($catalog, 'OOS-'.uniqid(), 'S', 'Red', '499', '999', 0)]);

    expect(ProductJsonLd::product(pdpPage($product))['offers']['availability'])->toBe('https://schema.org/OutOfStock');
});

test('aggregateRating appears once the product has ratings', function () {
    app()->instance(ReviewSummaryProvider::class, new class implements ReviewSummaryProvider
    {
        public function forProduct(int $productId): ReviewSummary
        {
            return new ReviewSummary(4.26, 31, [5 => 20, 4 => 6, 3 => 3, 2 => 1, 1 => 1]);
        }
    });
    ['product' => $product] = pdpProduct();

    expect(ProductJsonLd::product(pdpPage($product))['aggregateRating'])->toBe([
        '@type' => 'AggregateRating',
        'ratingValue' => 4.3,
        'reviewCount' => 31,
        'bestRating' => 5,
        'worstRating' => 1,
    ]);

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertSee('"@type":"AggregateRating"', false)
        ->assertSee('Based on 31 ratings');
});

test('the breadcrumb list follows the category trail', function () {
    ['product' => $product, 'catalog' => $catalog] = pdpProduct();
    $data = ProductJsonLd::breadcrumbs(pdpPage($product));

    expect($data['@type'])->toBe('BreadcrumbList')
        ->and(collect($data['itemListElement'])->pluck('name')->all())->toBe(['Home', 'Women', 'Kurtis', 'Printed Cotton Kurti'])
        ->and(collect($data['itemListElement'])->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($data['itemListElement'][2]['item'])->toBe(StorefrontUrls::category($catalog['category']->slug));
});

test('the encoded JSON cannot close the script tag', function () {
    expect(ProductJsonLd::encode(['name' => '</script><b>x</b>']))->not->toContain('</script>');
});
