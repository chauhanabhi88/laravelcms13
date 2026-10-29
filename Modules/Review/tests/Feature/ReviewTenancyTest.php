<?php

use Modules\Customer\Events\CustomerAnonymised;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Services\ReviewService;
use Modules\Review\Services\SellerReplyService;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('a customer cannot open, edit or write reviews for someone else', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);
    $stranger = orderCustomer();

    $this->actingAs($stranger, 'customer');

    $this->get(frontRoute('customer.reviews.edit', ['review' => $review->id]))->assertNotFound();
    $this->put(frontRoute('customer.reviews.update', ['review' => $review->id]), ['rating' => 1])->assertNotFound();
    $this->get(frontRoute('customer.reviews.create', ['orderItem' => $item->id]))->assertNotFound();
    $this->post(frontRoute('customer.reviews.store', ['orderItem' => $item->id]), ['rating' => 1])->assertNotFound();

    expect(fn () => app(ReviewService::class)->update($review, $stranger, new ReviewSubmission(1)))->toThrow(ReviewException::class);
    expect($review->fresh()->rating)->toBe(5);
});

test('a seller replies only to reviews of their own products', function () {
    [, $sellerOrder, $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());
    $otherOwner = fulfilmentOwner(orderSeller());

    $this->actingAs($otherOwner, 'seller')
        ->post(frontRoute('seller.review.reply', ['id' => $review->id]), ['reply' => 'Thank you!'])
        ->assertNotFound();

    expect(fn () => app(SellerReplyService::class)->reply($review, $otherOwner, 'Thank you!'))->toThrow(ReviewException::class)
        ->and($review->fresh()->seller_reply)->toBeNull();

    $this->actingAs($owner, 'seller')
        ->post(frontRoute('seller.review.reply', ['id' => $review->id]), ['reply' => 'Thank you for the kind words!'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($review->fresh()->seller_reply)->toBe('Thank you for the kind words!');
});

test('an anonymised account keeps its reviews under a generic author name', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item);
    [, , $otherItem, $otherCustomer] = reviewDeliveredOrder();
    $other = writeReview($otherCustomer, $otherItem);

    event(new CustomerAnonymised($customer->id));

    expect($review->fresh()->author_name)->toBe(trans('review::front.card.anonymous'))
        ->and($review->fresh()->isPublished())->toBeTrue()
        ->and($other->fresh()->author_name)->toBe($other->author_name);
});

test('the supplier reviews page lists only the seller\'s own reviews', function () {
    [, $sellerOrder, $item, $customer] = reviewDeliveredOrder();
    $own = writeReview($customer, $item, 4, 'Good quality cotton fabric.');
    [, , $otherItem, $otherCustomer] = reviewDeliveredOrder();
    $foreign = writeReview($otherCustomer, $otherItem, 2, 'Size runs small for me.');

    $this->actingAs(fulfilmentOwner($sellerOrder->seller()->firstOrFail()), 'seller')
        ->get(frontRoute('seller.review.index'))
        ->assertSuccessful()
        ->assertSee('data-seller-review="'.$own->id.'"', false)
        ->assertDontSee('data-seller-review="'.$foreign->id.'"', false);
});
