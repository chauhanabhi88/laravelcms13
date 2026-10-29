<?php

use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;
use Modules\Notification\Models\NotificationLog;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Modules\Review\Listeners\PromptReviewAfterDelivery;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('a delivery queues the review prompt with the configured delay', function () {
    reviewSettings(['prompt_delay_days' => 3]);
    [, $sellerOrder] = reviewUndeliveredOrder();
    Queue::fake();

    $event = new SellerOrderStatusChanged($sellerOrder, SellerOrder::STATUS_OUT_FOR_DELIVERY, SellerOrder::STATUS_DELIVERED);
    event($event);

    Queue::assertPushedOn('notifications', CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PromptReviewAfterDelivery::class);

    // The queue fake keeps no delay; the dispatcher reads it from withDelay().
    expect(app(PromptReviewAfterDelivery::class)->withDelay($event))->toBe(3 * 86400);

    reviewSettings(['prompt_delay_days' => 5]);
    expect(app(PromptReviewAfterDelivery::class)->withDelay($event))->toBe(5 * 86400);
});

test('other moves and deliveries after a closed return queue no prompt', function () {
    [, $sellerOrder] = reviewUndeliveredOrder();
    Queue::fake();

    event(new SellerOrderStatusChanged($sellerOrder, SellerOrder::STATUS_SHIPPED, SellerOrder::STATUS_OUT_FOR_DELIVERY));
    event(new SellerOrderStatusChanged($sellerOrder, SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_DELIVERED));

    Queue::assertNotPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === PromptReviewAfterDelivery::class);
});

test('the prompt asks the buyer to rate the delivered item', function () {
    [$order, , $item, $customer] = reviewDeliveredOrder();

    $log = NotificationLog::query()->where('event_key', 'review.request')->where('notifiable_id', $customer->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->data()['product_name'])->toBe($item->name)
        ->and($log->data()['order_no'])->toBe($order->order_no)
        ->and($log->data()['url'])->toBe(frontRoute('customer.reviews.create', ['orderItem' => $item->id]));
});

test('no prompt once the item is reviewed or the package is no longer delivered', function () {
    [, $sellerOrder, $item, $customer] = reviewDeliveredOrder();
    writeReview($customer, $item);
    $before = NotificationLog::query()->where('event_key', 'review.request')->count();

    app(PromptReviewAfterDelivery::class)->handle(new SellerOrderStatusChanged($sellerOrder, SellerOrder::STATUS_OUT_FOR_DELIVERY, SellerOrder::STATUS_DELIVERED));

    [, $undelivered] = reviewUndeliveredOrder();
    app(PromptReviewAfterDelivery::class)->handle(new SellerOrderStatusChanged($undelivered, SellerOrder::STATUS_OUT_FOR_DELIVERY, SellerOrder::STATUS_DELIVERED));

    expect(NotificationLog::query()->where('event_key', 'review.request')->count())->toBe($before);
});
