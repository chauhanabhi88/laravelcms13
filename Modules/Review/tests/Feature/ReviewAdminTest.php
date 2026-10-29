<?php

use Illuminate\Support\Facades\Storage;
use Modules\Notification\Models\NotificationLog;
use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('the moderation queue shows waiting and reported reviews', function () {
    reviewSettings(['auto_publish' => 2]);
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    $pending = writeReview($customer, $item, 2, 'Colour faded after one wash.');
    reviewSettings(['auto_publish' => 1]);
    $published = reviewByAnotherBuyer($variant, 5, 'Fits perfectly, would buy again.');
    $reported = reviewByAnotherBuyer($variant, 1, 'Worst purchase of the year.');
    ProductReview::query()->whereKey($reported->id)->update(['report_count' => 3]);

    $this->actingAs(reviewAdmin(), 'web');

    $queue = $this->get(route('admin.product_review.index', updateUrlParams()))->assertSuccessful()->viewData('collection');
    expect($queue->getCollection()->modelKeys())->toContain($pending->id)->toContain($reported->id)->not->toContain($published->id);

    $all = $this->post(route('admin.product_review.filters', updateUrlParams()), ['queue' => 'all'])->assertSuccessful();
    expect($all->json('type'))->toBe('success')->and($all->json('content.html'))->toContain('Fits perfectly');
});

test('a moderator publishes, rejects and hides reviews', function () {
    reviewSettings(['auto_publish' => 2]);
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 4, 'Breathable fabric and neat stitching.');
    $admin = reviewAdmin();

    $this->actingAs($admin, 'web')
        ->get(route('admin.product_review.show', updateUrlParams([$review->id])))
        ->assertSuccessful()
        ->assertSee('Breathable fabric and neat stitching.');

    $this->post(route('admin.product_review.publish', updateUrlParams([$review->id])))
        ->assertRedirect(route('admin.product_review.show', updateUrlParams([$review->id])));

    expect($review->fresh()->isPublished())->toBeTrue()
        ->and($review->fresh()->moderated_by)->toBe($admin->id)
        ->and((int) Product::query()->whereKey($variant->product_id)->value('rating_count'))->toBe(1);

    $this->post(route('admin.product_review.hide', updateUrlParams([$review->id])), ['reason' => 'Duplicate of an older review']);
    expect($review->fresh()->isHidden())->toBeTrue();

    $this->post(route('admin.product_review.reject', updateUrlParams([$review->id])), [])->assertSessionHasErrors('reason');
    $this->post(route('admin.product_review.reject', updateUrlParams([$review->id])), ['reason' => 'Mentions another seller']);

    expect($review->fresh()->isRejected())->toBeTrue()
        ->and($review->fresh()->rejection_reason)->toBe('Mentions another seller')
        ->and(NotificationLog::query()->where('event_key', 'review.rejected')->where('notifiable_id', $customer->id)->exists())->toBeTrue();
});

test('a moderator deletes a review with its photos', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.', [reviewPhoto()]);
    $path = $review->images()->value('path');

    $this->actingAs(reviewAdmin(), 'web')
        ->post(route('admin.product_review.delete', updateUrlParams([$review->id])))
        ->assertRedirect(route('admin.product_review.index', updateUrlParams()));

    expect(ProductReview::query()->find($review->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

test('moderation needs the permissions', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);

    $this->actingAs(reviewAdmin(['admin.product_review.index', 'admin.product_review.show']), 'web')
        ->post(route('admin.product_review.hide', updateUrlParams([$review->id])))
        ->assertForbidden();

    expect($review->fresh()->isPublished())->toBeTrue();
});
