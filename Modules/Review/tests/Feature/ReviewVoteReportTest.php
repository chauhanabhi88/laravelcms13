<?php

use Illuminate\Support\Facades\DB;
use Modules\Notification\Models\NotificationLog;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewReport;
use Modules\Review\Services\ReviewFeedbackService;
use Modules\Review\Services\ReviewModerationService;
use Modules\Review\Support\ReviewSettings;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('a shopper toggles a helpful vote', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);
    $shopper = orderCustomer();

    $this->actingAs($shopper, 'customer')
        ->postJson(frontRoute('review.vote', ['id' => $review->id]))
        ->assertSuccessful()
        ->assertJsonPath('voted', true)
        ->assertJsonPath('helpful_count', 1);

    expect($review->fresh()->helpful_count)->toBe(1)
        ->and(DB::table('product_review_vote')->where('product_review_id', $review->id)->count())->toBe(1);

    $this->postJson(frontRoute('review.vote', ['id' => $review->id]))
        ->assertSuccessful()
        ->assertJsonPath('voted', false)
        ->assertJsonPath('helpful_count', 0);

    expect($review->fresh()->helpful_count)->toBe(0);
});

test('guests must sign in to vote or report', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);

    $this->postJson(frontRoute('review.vote', ['id' => $review->id]))->assertUnauthorized();
    $this->postJson(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'spam'])->assertUnauthorized();
});

test('nobody votes on or reports their own review', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);

    $this->actingAs($author, 'customer')
        ->postJson(frontRoute('review.vote', ['id' => $review->id]))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'own_review');

    $this->postJson(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'spam'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'own_review');
});

test('unpublished reviews cannot be voted on', function () {
    reviewSettings(['auto_publish' => 2]);
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);

    $this->actingAs(orderCustomer(), 'customer')
        ->postJson(frontRoute('review.vote', ['id' => $review->id]))
        ->assertNotFound();
});

test('a shopper reports a review once', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);

    $this->actingAs(orderCustomer(), 'customer')
        ->postJson(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'fake', 'comment' => 'Same text on every product'])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->postJson(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'spam'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'already_reported');

    $this->postJson(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'boring'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($review->fresh()->report_count)->toBe(1)
        ->and(ProductReviewReport::query()->where('product_review_id', $review->id)->value('reason'))->toBe('fake');
});

test('reaching the report threshold sends the review to the moderation queue and tells moderators', function () {
    $moderator = reviewAdmin();
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);
    $feedback = app(ReviewFeedbackService::class);
    $inQueue = fn (): bool => ProductReview::query()->needsModeration(ReviewSettings::reportThreshold())->whereKey($review->id)->exists();

    $feedback->report($review, orderCustomer(), 'spam');
    $feedback->report($review->fresh(), orderCustomer(), 'offensive');

    expect($inQueue())->toBeFalse();

    $feedback->report($review->fresh(), orderCustomer(), 'fake');

    expect($inQueue())->toBeTrue()
        ->and($review->fresh()->isPublished())->toBeTrue()
        ->and(NotificationLog::query()->where('event_key', 'admin.review.reported')->where('notifiable_id', $moderator->id)->exists())->toBeTrue();

    // "Keep" clears the reports and takes it out of the queue.
    app(ReviewModerationService::class)->publish($review->fresh());

    expect($inQueue())->toBeFalse()->and($review->fresh()->report_count)->toBe(0);
});

test('the vote and report forms work without JavaScript', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);

    $this->actingAs(orderCustomer(), 'customer')
        ->from('/somewhere')
        ->post(frontRoute('review.vote', ['id' => $review->id]))
        ->assertRedirect('/somewhere')
        ->assertSessionHas('success');

    $this->from('/somewhere')
        ->post(frontRoute('review.report', ['id' => $review->id]), ['reason' => 'irrelevant'])
        ->assertRedirect('/somewhere')
        ->assertSessionHas('success');
});
