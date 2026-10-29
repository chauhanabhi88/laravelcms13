<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewImage;
use Modules\Review\Services\ReviewModerationService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('the order page offers rate stars that open the prefilled form', function () {
    [$order, , $item, $customer] = reviewDeliveredOrder();
    $createUrl = frontRoute('customer.reviews.create', ['orderItem' => $item->id]);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertSee('data-rate-product="'.$item->id.'"', false)
        ->assertSee(e($createUrl.'?rating=4'), false);

    $this->get(frontRoute('customer.orders.index'))->assertSee('data-rate-link', false);

    $this->get($createUrl.'?rating=4')
        ->assertSuccessful()
        ->assertViewIs('review::frontend.account.review-form')
        ->assertViewHas('rating', 4)
        ->assertSee($item->name);
});

test('an undelivered order shows no rate stars', function () {
    [$order, , $item, $customer] = reviewUndeliveredOrder();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSuccessful()
        ->assertDontSee('data-rate-product', false);

    $this->get(frontRoute('customer.reviews.create', ['orderItem' => $item->id]))
        ->assertRedirect(frontRoute('customer.reviews.index'))
        ->assertSessionHas('error', trans('review::front.errors.not_delivered'));
});

test('a customer writes a review with photos', function () {
    [$order, , $item, $customer] = reviewDeliveredOrder();

    $this->actingAs($customer, 'customer')
        ->post(frontRoute('customer.reviews.store', ['orderItem' => $item->id]), [
            'rating' => 4,
            'title' => '  Pretty   print ',
            'body' => "Soft fabric.\r\n\r\n\r\nRuns a little large.",
            'photos' => [reviewPhoto('front.jpg'), reviewPhoto('back.png')],
        ])
        ->assertRedirect(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))
        ->assertSessionHas('success', trans('review::front.messages.published'));

    $review = ProductReview::query()->with('images')->where('sales_order_item_id', $item->id)->firstOrFail();

    expect($review->rating)->toBe(4)
        ->and($review->title)->toBe('Pretty print')
        ->and($review->body)->toBe("Soft fabric.\n\nRuns a little large.")
        ->and($review->hasImages())->toBeTrue()
        ->and($review->images)->toHaveCount(2)
        ->and($review->variant_label)->toBe($item->variant_label)
        ->and($review->author_name)->not->toContain('@');

    $disk = Storage::disk('public');
    foreach ($review->images as $image) {
        expect($disk->exists($image->path))->toBeTrue()
            ->and($disk->exists((string) $image->thumb_path))->toBeTrue()
            ->and($image->path)->toEndWith('.jpg')
            ->and(getimagesizefromstring($disk->get($image->path))['mime'])->toBe('image/jpeg');
    }

    $this->get(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))
        ->assertSee('data-review-state="'.$item->id.'"', false)
        ->assertDontSee('data-rate-product', false);
});

test('the form validates stars, text length, photo count and image files', function () {
    reviewSettings(['max_images' => 2]);
    [, , $item, $customer] = reviewDeliveredOrder();
    $url = frontRoute('customer.reviews.store', ['orderItem' => $item->id]);

    $this->actingAs($customer, 'customer')
        ->post($url, ['body' => 'Short'])
        ->assertSessionHasErrors(['rating', 'body']);

    $this->post($url, ['rating' => 5, 'photos' => [reviewPhoto(), reviewPhoto(), reviewPhoto()]])
        ->assertSessionHasErrors('photos');

    $this->post($url, ['rating' => 5, 'photos' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('photos.0');

    expect(ProductReview::query()->where('sales_order_item_id', $item->id)->exists())->toBeFalse();
});

test('the customer edits a review: removes a photo, adds one, changes the rating', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.', [reviewPhoto(), reviewPhoto()]);
    $removed = $review->images()->first();

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.reviews.edit', ['review' => $review->id]))
        ->assertSuccessful()
        ->assertSee('Soft cotton, colour exactly as shown.');

    $this->put(frontRoute('customer.reviews.update', ['review' => $review->id]), [
        'rating' => 3,
        'body' => 'Soft cotton, colour exactly as shown.',
        'remove_images' => [$removed->id],
        'photos' => [reviewPhoto('new.jpg')],
    ])->assertRedirect()->assertSessionHas('success', trans('review::front.messages.updated'));

    $review->refresh()->load('images');

    expect($review->rating)->toBe(3)
        ->and($review->images)->toHaveCount(2)
        ->and($review->images->modelKeys())->not->toContain($removed->id)
        ->and($review->edited_at)->not->toBeNull()
        ->and(Storage::disk('public')->exists($removed->path))->toBeFalse();
});

test('changed text that fails the checks sends a published review back to moderation', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.');

    $this->actingAs($customer, 'customer')
        ->put(frontRoute('customer.reviews.update', ['review' => $review->id]), ['rating' => 5, 'body' => 'Call 9876543210 for the same at half price'])
        ->assertSessionHas('success', trans('review::front.messages.pending'));

    expect($review->fresh()->isPending())->toBeTrue()
        ->and($review->fresh()->flags())->toBe([ProductReview::FLAG_CONTACT]);
});

test('editing a rejected review resubmits it', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.');
    app(ReviewModerationService::class)->reject($review, 'Please describe the product');

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))
        ->assertSee('Please describe the product');

    $this->put(frontRoute('customer.reviews.update', ['review' => $review->id]), ['rating' => 4, 'body' => 'Breathable fabric and neat stitching.']);

    expect($review->fresh()->isPublished())->toBeTrue()->and($review->fresh()->rejection_reason)->toBeNull();
});

test('hidden reviews and reviews past the edit window cannot be edited', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);
    app(ReviewModerationService::class)->hide($review);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.reviews.edit', ['review' => $review->id]))
        ->assertRedirect()
        ->assertSessionHas('error', trans('review::front.errors.review_hidden'));

    [, , $otherItem] = reviewDeliveredOrder([], $customer);
    $old = writeReview($customer, $otherItem, 4, 'Breathable fabric and neat stitching.');
    $this->travel(31)->days();

    $this->put(frontRoute('customer.reviews.update', ['review' => $old->id]), ['rating' => 1])
        ->assertSessionHas('error', trans('review::front.errors.edit_window_closed'));

    expect($old->fresh()->rating)->toBe(4);
});

test('my reviews lists items to review and written reviews', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    [, , $reviewedItem] = reviewDeliveredOrder([], $customer);
    $review = writeReview($customer, $reviewedItem);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.reviews.index'))
        ->assertSuccessful()
        ->assertViewHas('tab', 'to_review')
        ->assertSee('data-pending-item="'.$item->id.'"', false)
        ->assertDontSee('data-pending-item="'.$reviewedItem->id.'"', false);

    $this->get(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))
        ->assertSuccessful()
        ->assertSee('data-my-review="'.$review->id.'"', false);

    expect(ProductReviewImage::query()->count())->toBe(0);
});

test('a second submission for the same item goes to the edit form', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);

    $this->actingAs($customer, 'customer')
        ->get(frontRoute('customer.reviews.create', ['orderItem' => $item->id]))
        ->assertRedirect(frontRoute('customer.reviews.edit', ['review' => $review->id]));
});
