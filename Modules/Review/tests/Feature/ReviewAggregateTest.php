<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Product\Events\ProductAggregatesChanged;
use Modules\Product\Models\Product;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Events\ReviewStatusChanged;
use Modules\Review\Listeners\RecalculateAggregatesOnPublish;
use Modules\Review\Services\ReviewAggregateService;
use Modules\Review\Services\ReviewModerationService;
use Modules\Review\Services\ReviewService;
use Modules\Seller\Models\Seller;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('published reviews set the product average, count and histogram', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();

    writeReview($customer, $item, 5);
    reviewByAnotherBuyer($variant, 4);
    reviewByAnotherBuyer($variant, 4);

    $product = Product::query()->findOrFail($variant->product_id);

    expect($product->rating_count)->toBe(3)
        ->and((string) $product->rating_avg)->toBe('4.33')
        ->and($product->rating_histogram)->toEqual(['5' => 1, '4' => 2, '3' => 0, '2' => 0, '1' => 0]);
});

test('pending reviews do not count until a moderator publishes them', function () {
    reviewSettings(['auto_publish' => 2]);
    [, , $item, $customer, $variant] = reviewDeliveredOrder();

    $review = writeReview($customer, $item, 2);

    expect(Product::query()->findOrFail($variant->product_id)->rating_count)->toBe(0);

    app(ReviewModerationService::class)->publish($review);

    $product = Product::query()->findOrFail($variant->product_id);
    expect($product->rating_count)->toBe(1)->and((string) $product->rating_avg)->toBe('2.00');
});

test('hiding, rejecting and deleting take a review out of the aggregates', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    $first = writeReview($customer, $item, 5);
    $second = reviewByAnotherBuyer($variant, 1);
    $third = reviewByAnotherBuyer($variant, 3);
    $moderation = app(ReviewModerationService::class);
    $product = fn (): Product => Product::query()->findOrFail($variant->product_id);

    $moderation->hide($second);
    expect($product()->rating_count)->toBe(2)->and((string) $product()->rating_avg)->toBe('4.00');

    $moderation->reject($third, 'Not about the product');
    expect($product()->rating_count)->toBe(1)->and((string) $product()->rating_avg)->toBe('5.00');

    $moderation->delete($first);
    expect($product()->rating_count)->toBe(0)
        ->and((string) $product()->rating_avg)->toBe('0.00')
        ->and($product()->rating_histogram)->toEqual(['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0]);
});

test('the seller rating averages every published review of their products', function () {
    $seller = orderSeller();
    [, , $item, $customer] = reviewDeliveredOrder([], null, $seller);
    [, , $otherItem, $otherCustomer] = reviewDeliveredOrder([], null, $seller);

    writeReview($customer, $item, 5);
    writeReview($otherCustomer, $otherItem, 2);

    $seller = Seller::query()->findOrFail($seller->id);

    expect($seller->rating_count)->toBe(2)->and((string) $seller->rating_avg)->toBe('3.50');
});

test('a rating change on a published review updates the aggregates', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.');

    app(ReviewService::class)->update($review, $customer, new ReviewSubmission(3, null, 'Soft cotton, colour exactly as shown.'));

    expect((string) Product::query()->findOrFail($variant->product_id)->rating_avg)->toBe('3.00');
});

test('the storefront search index gets the new rating', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();

    writeReview($customer, $item, 4);

    $row = DB::table('product_search_index')->where('product_id', $variant->product_id)->first(['rating_avg', 'rating_count']);

    expect($row)->not->toBeNull()
        ->and((string) $row->rating_avg)->toBe('4.00')
        ->and((int) $row->rating_count)->toBe(1);
});

test('recalculating fires ProductAggregatesChanged only when something changed', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    writeReview($customer, $item, 4);
    Event::fake([ProductAggregatesChanged::class]);

    expect(app(ReviewAggregateService::class)->recalculate($variant->product_id))->toBeFalse();
    Event::assertNotDispatched(ProductAggregatesChanged::class);
});

test('aggregates are recalculated by a queued listener', function () {
    Event::fake([ReviewStatusChanged::class]);
    [, , $item, $customer] = reviewDeliveredOrder();

    writeReview($customer, $item, 4);

    Event::assertDispatched(ReviewStatusChanged::class, fn (ReviewStatusChanged $event): bool => $event->from === null && $event->to === 'published' && $event->affectsAggregates());
    Event::assertListening(ReviewStatusChanged::class, RecalculateAggregatesOnPublish::class);
    expect(new RecalculateAggregatesOnPublish(app(ReviewAggregateService::class)))->toBeInstanceOf(ShouldQueue::class);
});
