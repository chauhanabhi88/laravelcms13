<?php

use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewModerationService;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\Support\StorefrontUrls;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

function reviewsUrl(Product $product, array $query = []): string
{
    return frontRoute('storefront.product.reviews', [$product->slug.'-'.$product->id]).($query !== [] ? '?'.http_build_query($query) : '');
}

/**
 * A product with five published reviews: ratings 5, 5, 4, 2, 1; the 4-star one has a photo
 * and the 2-star one is the most helpful.
 *
 * @return array{0: Product, 1: array<int, ProductReview>}
 */
function reviewedProduct(): array
{
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    $reviews = [
        'five' => writeReview($customer, $item, 5, 'Excellent stitching and fabric.', [], 'Loved it'),
        'five_b' => reviewByAnotherBuyer($variant, 5, 'Fits perfectly, would buy again.'),
        'four' => reviewByAnotherBuyer($variant, 4, 'Nice colour, slightly loose.', [reviewPhoto()]),
        'two' => reviewByAnotherBuyer($variant, 2, 'Colour faded after one wash.'),
        'one' => reviewByAnotherBuyer($variant, 1, 'Wrong size delivered, poor quality.'),
    ];
    ProductReview::query()->whereKey($reviews['two']->id)->update(['helpful_count' => 9]);

    return [Product::query()->findOrFail($variant->product_id), $reviews];
}

test('the reviews page lists published reviews with the summary', function () {
    [$product, $reviews] = reviewedProduct();
    $hidden = $reviews['one'];
    app(ReviewModerationService::class)->hide($hidden);

    $this->get(reviewsUrl($product))
        ->assertSuccessful()
        ->assertViewIs('review::frontend.product-reviews')
        ->assertSee('Ratings &amp; reviews', false)
        ->assertSee('Loved it')
        ->assertSee('Verified Buyer')
        ->assertSee('data-review="'.$reviews['four']->id.'"', false)
        ->assertDontSee('data-review="'.$hidden->id.'"', false)
        ->assertSee('<link rel="canonical" href="'.reviewsUrl($product).'">', false);
});

test('reviews are sorted by most helpful by default and can be sorted by latest or rating', function () {
    [$product, $reviews] = reviewedProduct();

    $ids = fn (array $query): array => $this->get(reviewsUrl($product, $query))->viewData('reviews')->getCollection()->modelKeys();

    expect($ids([])[0])->toBe($reviews['two']->id)
        ->and($ids(['sort' => 'latest'])[0])->toBe($reviews['one']->id)
        ->and($ids(['sort' => 'rating_high'])[0])->toBeIn([$reviews['five']->id, $reviews['five_b']->id])
        ->and($ids(['sort' => 'rating_low'])[0])->toBe($reviews['one']->id)
        ->and($ids(['sort' => 'nonsense'])[0])->toBe($reviews['two']->id);
});

test('reviews filter by stars and photos', function () {
    [$product, $reviews] = reviewedProduct();

    $five = $this->get(reviewsUrl($product, ['rating' => 5]));
    expect($five->viewData('reviews')->getCollection()->pluck('rating')->unique()->all())->toBe([5])
        ->and($five->viewData('reviews')->total())->toBe(2);
    $five->assertSee('noindex, follow', false);

    $photos = $this->get(reviewsUrl($product, ['with_photos' => 1]));
    expect($photos->viewData('reviews')->getCollection()->modelKeys())->toBe([$reviews['four']->id]);

    $this->get(reviewsUrl($product, ['rating' => 3]))->assertSee('No reviews match these filters');
});

test('reviews are paginated', function () {
    config(['review.per_page' => 2]);
    [$product] = reviewedProduct();

    $page = $this->get(reviewsUrl($product, ['page' => 3]))->viewData('reviews');

    expect($page->total())->toBe(5)->and($page->count())->toBe(1)->and($page->lastPage())->toBe(3);
});

test('an outdated slug redirects and hidden products answer 404', function () {
    [$product] = reviewedProduct();

    $this->get(frontRoute('storefront.product.reviews', ['old-name-'.$product->id]).'?rating=5')
        ->assertRedirect(reviewsUrl($product, ['rating' => 5]))
        ->assertStatus(301);

    $product->forceFill(['status' => 'paused'])->save();
    $this->get(reviewsUrl($product))->assertNotFound();
});

test('the product page shows the summary, photos and top reviews from the Review module', function () {
    [$product, $reviews] = reviewedProduct();
    $summary = app(ReviewSummaryProvider::class)->forProduct($product->id);

    expect($summary->count)->toBe(5)
        ->and($summary->average)->toBe(3.4)
        ->and($summary->histogram)->toBe([5 => 2, 4 => 1, 3 => 0, 2 => 1, 1 => 1])
        ->and($summary->photos)->toHaveCount(1)
        ->and($summary->top)->toHaveCount(3)
        ->and($summary->top[0]['id'])->toBe($reviews['two']->id)
        ->and($summary->allReviewsUrl)->toBe(reviewsUrl($product));

    $this->get(StorefrontUrls::product($product->slug, $product->id))
        ->assertSuccessful()
        ->assertDontSee('No ratings yet')
        ->assertSee('View all 5 reviews')
        ->assertSee('data-star-filter="5"', false)
        ->assertSee('Colour faded after one wash.');
});

test('the public API lists reviews with the summary', function () {
    [$product, $reviews] = reviewedProduct();

    $this->getJson(route('review_api.index', ['id' => $product->id, 'rating' => 4]))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.count', 5)
        ->assertJsonPath('data.filters.rating', 4)
        ->assertJsonPath('data.reviews.0.id', $reviews['four']->id)
        ->assertJsonCount(1, 'data.reviews.0.photos')
        ->assertJsonPath('meta.total', 1);
});
