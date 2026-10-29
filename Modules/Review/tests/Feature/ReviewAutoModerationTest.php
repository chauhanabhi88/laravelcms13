<?php

use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewModerationService;
use Modules\Review\Support\ProfanityFilter;

require_once __DIR__.'/../Helpers.php';

beforeEach(function () {
    reviewSetup();
});

test('a clean review is published at once', function () {
    [, , $item, $customer] = reviewDeliveredOrder();

    $review = writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.');

    expect($review->status)->toBe(ProductReview::STATUS_PUBLISHED)
        ->and($review->published_at)->not->toBeNull()
        ->and($review->flags())->toBe([]);
});

test('a rating without text is published', function () {
    [, , $item, $customer] = reviewDeliveredOrder();

    expect(writeReview($customer, $item, 4, null)->isPublished())->toBeTrue();
});

test('suspicious text waits for a moderator', function (string $body, string $flag) {
    [, , $item, $customer] = reviewDeliveredOrder();

    $review = writeReview($customer, $item, 1, $body);

    expect($review->status)->toBe(ProductReview::STATUS_PENDING)
        ->and($review->published_at)->toBeNull()
        ->and($review->flags())->toContain($flag);
})->with([
    'profanity' => ['This is total bullshit, do not buy.', ProductReview::FLAG_PROFANITY],
    'obfuscated profanity' => ['Seller is a b.i.t.c.h and the shirt is torn', ProductReview::FLAG_PROFANITY],
    'hindi profanity' => ['Bekar quality, chutiya bana diya', ProductReview::FLAG_PROFANITY],
    'link' => ['Buy it cheaper at www.cheap-kurtis.example instead', ProductReview::FLAG_LINK],
    'domain' => ['Order directly from bestdeals.shop for less', ProductReview::FLAG_LINK],
    'phone number' => ['Call me on 98765 43210 for wholesale rates', ProductReview::FLAG_CONTACT],
    'phone with country code' => ['WhatsApp +91-9876543210 for more colours', ProductReview::FLAG_CONTACT],
    'email' => ['Mail seller.direct@example.com for discounts', ProductReview::FLAG_CONTACT],
]);

test('words that merely contain a listed word are fine', function () {
    $filter = app(ProfanityFilter::class);

    expect($filter->contains('Classic class, Scunthorpe shipping and a cocktail dress'))->toBeFalse()
        ->and($filter->contains('Great assessment of size'))->toBeFalse()
        ->and($filter->contains('what the fuuuuck'))->toBeTrue();
});

test('the same text in another review of the customer waits for a moderator', function () {
    [, , $item, $customer, $variant] = reviewDeliveredOrder();
    [, , $secondItem] = reviewDeliveredOrder([], $customer);

    $first = writeReview($customer, $item, 5, 'Amazing product, must buy!!');
    $second = writeReview($customer, $secondItem, 5, 'amazing product — must buy');

    expect($first->isPublished())->toBeTrue()
        ->and($second->isPending())->toBeTrue()
        ->and($second->flags())->toBe([ProductReview::FLAG_DUPLICATE]);
});

test('every review waits when auto-publishing is off', function () {
    reviewSettings(['auto_publish' => 2]);
    [, , $item, $customer] = reviewDeliveredOrder();

    expect(writeReview($customer, $item, 5, 'Soft cotton, colour exactly as shown.')->isPending())->toBeTrue();
});

test('a too-short text is refused before anything is stored', function () {
    [, , $item, $customer] = reviewDeliveredOrder();

    expect(fn () => writeReview($customer, $item, 5, 'Nice'))->toThrow(ReviewException::class)
        ->and(ProductReview::query()->count())->toBe(0);
});

test('the checks report every finding', function () {
    $flags = app(ReviewModerationService::class)->autoCheck(0, 'Shit quality', 'Call 9876543210 or visit www.example.com');

    expect($flags)->toContain(ProductReview::FLAG_PROFANITY)
        ->toContain(ProductReview::FLAG_CONTACT)
        ->toContain(ProductReview::FLAG_LINK);
});
