<?php

use Modules\Order\Models\SalesOrderItem;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewEligibilityService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

function reviewEligibility(): ReviewEligibilityService
{
    return app(ReviewEligibilityService::class);
}

test('the buyer of a delivered item can review it', function () {
    [, , $item, $customer] = reviewDeliveredOrder();

    expect(reviewEligibility()->reason($item, $customer))->toBeNull()
        ->and(reviewEligibility()->pendingItems($customer)->modelKeys())->toBe([$item->id])
        ->and(reviewEligibility()->pendingCount($customer))->toBe(1);
});

test('an item that is not delivered yet cannot be reviewed', function () {
    [, , $item, $customer] = reviewUndeliveredOrder();

    expect(reviewEligibility()->reason($item, $customer))->toBe('not_delivered')
        ->and(reviewEligibility()->pendingCount($customer))->toBe(0)
        ->and(fn () => writeReview($customer, $item))->toThrow(ReviewException::class);

    expect(ProductReview::query()->where('sales_order_item_id', $item->id)->exists())->toBeFalse();
});

test('another customer cannot review the item', function () {
    [, , $item] = reviewDeliveredOrder();
    $stranger = orderCustomer();

    expect(reviewEligibility()->reason($item, $stranger))->toBe('not_found');

    try {
        writeReview($stranger, $item);
        $this->fail('A stranger reviewed someone else\'s item.');
    } catch (ReviewException $e) {
        expect($e->reasonCode)->toBe('not_found');
    }
});

test('an item is reviewed once only', function () {
    [, , $item, $customer] = reviewDeliveredOrder();

    writeReview($customer, $item);

    expect(reviewEligibility()->reason($item, $customer))->toBe('already_reviewed')
        ->and(reviewEligibility()->pendingCount($customer))->toBe(0);

    try {
        writeReview($customer, $item, 1, 'Changed my mind about this.');
        $this->fail('The item was reviewed twice.');
    } catch (ReviewException $e) {
        expect($e->reasonCode)->toBe('already_reviewed');
    }

    expect(ProductReview::query()->where('sales_order_item_id', $item->id)->count())->toBe(1);
});

test('a cancelled item cannot be reviewed', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $item->forceFill(['status' => SalesOrderItem::STATUS_CANCELLED, 'cancelled_qty' => $item->quantity])->save();

    expect(reviewEligibility()->reason($item->fresh(), $customer))->toBe('not_delivered');
});

test('a returned item can still be reviewed and is flagged returned', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $item->forceFill(['status' => SalesOrderItem::STATUS_RETURNED, 'returned_qty' => $item->quantity])->save();

    $review = writeReview($customer, $item);

    expect($review->isReturned())->toBeTrue();
});

test('a review can be edited within the edit window only', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);

    expect(reviewEligibility()->editReason($review, $customer))->toBeNull();

    $this->travel(31)->days();

    expect(reviewEligibility()->editReason($review->fresh(), $customer))->toBe('edit_window_closed');
});
