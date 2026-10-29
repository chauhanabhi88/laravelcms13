<?php

use Laravel\Passport\Passport;
use Modules\Customer\Services\CustomerAuthService;
use Modules\Review\Models\ProductReview;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('a customer writes a review over the API with photos', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->getJson(route('review_api.mine'))
        ->assertSuccessful()
        ->assertJsonPath('data.to_review.0.order_item_id', $item->id)
        ->assertJsonCount(0, 'data.reviews');

    $response = $this->post(route('review_api.store'), [
        'order_item_id' => $item->id,
        'rating' => 4,
        'title' => 'Nice print',
        'body' => 'Breathable fabric and neat stitching.',
        'photos' => [reviewPhoto()],
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.order_item_id', $item->id)
        ->assertJsonCount(1, 'data.photos');

    $this->getJson(route('review_api.mine'))
        ->assertJsonCount(0, 'data.to_review')
        ->assertJsonPath('data.reviews.0.id', $response->json('data.id'));
});

test('API errors use the envelope', function () {
    [, , $item, $customer] = reviewDeliveredOrder();
    Passport::actingAs($customer, [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('review_api.store'), ['order_item_id' => $item->id])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['rating']]);

    [, , $foreignItem] = reviewDeliveredOrder();
    $this->postJson(route('review_api.store'), ['order_item_id' => $foreignItem->id, 'rating' => 5])->assertNotFound();

    writeReview($customer, $item);
    $this->postJson(route('review_api.store'), ['order_item_id' => $item->id, 'rating' => 3])
        ->assertStatus(422)
        ->assertJsonPath('errors.reason', 'already_reviewed');
});

test('a customer toggles helpful over the API and sees it in the listing', function () {
    [, , $item, $author] = reviewDeliveredOrder();
    $review = writeReview($author, $item);
    Passport::actingAs(orderCustomer(), [CustomerAuthService::API_SCOPE], 'api');

    $this->postJson(route('review_api.vote', ['id' => $review->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.voted', true)
        ->assertJsonPath('data.helpful_count', 1);

    $this->getJson(route('review_api.index', ['id' => $review->product_id]))
        ->assertJsonPath('data.reviews.0.voted', true)
        ->assertJsonPath('data.reviews.0.helpful_count', 1);
});

test('the review endpoints for customers need a customer token', function () {
    $this->getJson(route('review_api.mine'))->assertUnauthorized();
    $this->postJson(route('review_api.store'), ['rating' => 5])->assertUnauthorized();
});

test('a seller reply that fails the checks is refused and a second reply is not allowed', function () {
    [, $sellerOrder, $item, $customer] = reviewDeliveredOrder();
    $review = writeReview($customer, $item, 2, 'Colour faded after one wash.');
    $owner = fulfilmentOwner($sellerOrder->seller()->firstOrFail());
    $url = frontRoute('seller.review.reply', ['id' => $review->id]);

    $this->actingAs($owner, 'seller')
        ->from(frontRoute('seller.review.index'))
        ->post($url, ['reply' => 'Call us on 9876543210 for a replacement'])
        ->assertSessionHasErrors('reply');

    expect($review->fresh()->hasSellerReply())->toBeFalse();

    $this->post($url, ['reply' => 'Sorry about that! Please raise a return from your orders page.'])->assertSessionHas('success');
    $this->post($url, ['reply' => 'Another reply'])->assertSessionHas('error', trans('review::front.errors.already_replied'));

    expect(ProductReview::query()->findOrFail($review->id)->seller_reply)->toBe('Sorry about that! Please raise a return from your orders page.');

    $this->get(frontRoute('storefront.product.reviews', [$review->product()->firstOrFail()->slug.'-'.$review->product_id]))
        ->assertSee('data-seller-reply', false)
        ->assertSee('Please raise a return from your orders page.');
});
