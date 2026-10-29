<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrderItem;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Events\ReviewStatusChanged;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewImage;
use Modules\Review\Support\ReviewPhotoStore;
use Modules\Review\Support\ReviewSettings;
use Modules\Review\Support\ReviewSummaryCache;

/**
 * Writing and editing reviews (FR-REV-001/002, plan M16 §5). Photos are decoded before
 * anything is written; the automatic checks decide between published and pending; the
 * product and seller aggregates follow through ReviewStatusChanged.
 *
 * Editing (within the edit window, not when hidden) keeps the status for a rating-only
 * change; changed text or new photos — or any edit of a rejected review — run the checks
 * again.
 */
class ReviewService
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly ReviewModerationService $moderation,
        private readonly ReviewPhotoStore $photos,
    ) {}

    /**
     * @throws ReviewException
     */
    public function create(Customer $customer, SalesOrderItem $item, ReviewSubmission $submission): ProductReview
    {
        $this->eligibility->assertCanReview($item, $customer);
        $this->validate($submission, count($submission->photos));

        $prepared = $this->photos->prepareMany($submission->photos);
        $flags = $this->moderation->autoCheck((int) $customer->id, $submission->title, $submission->body);
        $status = $this->moderation->statusFor($flags);
        $reviewId = null;

        try {
            $review = DB::transaction(function () use ($customer, $item, $submission, $prepared, $flags, $status, &$reviewId): ProductReview {
                $review = ProductReview::query()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'sales_order_item_id' => $item->id,
                    'sales_order_id' => $item->sales_order_id,
                    'customer_id' => $customer->id,
                    'seller_id' => (int) $item->sellerOrder?->seller_id,
                    'rating' => $submission->rating,
                    'title' => $submission->title,
                    'body' => $submission->body,
                    'author_name' => $this->authorName($customer),
                    'variant_label' => $item->variant_label,
                    'status' => $status,
                    'moderation_flags' => $flags === [] ? null : $flags,
                    'has_images' => $prepared === [] ? config('core.no') : config('core.yes'),
                    'is_returned' => $this->eligibility->isReturned($item) ? config('core.yes') : config('core.no'),
                    'published_at' => $status === ProductReview::STATUS_PUBLISHED ? now() : null,
                ]);
                $reviewId = $review->id;

                $this->storePhotos($review, $prepared, 0);

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            $this->cleanUp($reviewId);

            throw ReviewException::because('already_reviewed');
        } catch (\Throwable $e) {
            $this->cleanUp($reviewId);

            throw $e;
        }

        event(new ReviewStatusChanged($review->id, $review->product_id, null, $review->status));

        return $review;
    }

    /**
     * @param  list<int>  $removeImageIds  photos of the review to drop
     *
     * @throws ReviewException
     */
    public function update(ProductReview $review, Customer $customer, ReviewSubmission $submission, array $removeImageIds = []): ProductReview
    {
        if (($reason = $this->eligibility->editReason($review, $customer)) !== null) {
            throw ReviewException::because($reason);
        }

        $review->loadMissing('images');
        $removed = $review->images->whereIn('id', array_map('intval', $removeImageIds));
        $kept = $review->images->count() - $removed->count();
        $this->validate($submission, $kept + count($submission->photos));

        $prepared = $this->photos->prepareMany($submission->photos);
        $textChanged = $submission->title !== $review->title || $submission->body !== $review->body;
        $recheck = $textChanged || $prepared !== [] || $review->isRejected();
        $written = [];

        try {
            [$locked, $from] = DB::transaction(function () use ($review, $submission, $prepared, $removed, $kept, $recheck, &$written): array {
                /** @var ProductReview $locked */
                $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
                $from = $locked->status;

                if ($recheck) {
                    $flags = $this->moderation->autoCheck($locked->customer_id, $submission->title, $submission->body, $locked->id);
                    $status = $this->moderation->statusFor($flags);
                    $locked->forceFill([
                        'status' => $status,
                        'moderation_flags' => $flags === [] ? null : $flags,
                        'rejection_reason' => null,
                        'published_at' => $status === ProductReview::STATUS_PUBLISHED && $from !== ProductReview::STATUS_PUBLISHED ? now() : $locked->published_at,
                    ]);
                }

                ProductReviewImage::query()->where('product_review_id', $locked->id)->whereIn('id', $removed->modelKeys())->delete();
                $position = (int) ProductReviewImage::query()->where('product_review_id', $locked->id)->max('position');
                $written = $this->storePhotos($locked, $prepared, $position + 1);

                $locked->forceFill([
                    'rating' => $submission->rating,
                    'title' => $submission->title,
                    'body' => $submission->body,
                    'has_images' => $kept + count($prepared) > 0 ? config('core.yes') : config('core.no'),
                    'edited_at' => now(),
                ])->save();

                return [$locked, $from];
            });
        } catch (\Throwable $e) {
            foreach ($written as $photo) {
                $this->photos->delete($photo['path'], $photo['thumb_path']);
            }

            throw $e;
        }

        foreach ($removed as $image) {
            $this->photos->delete($image->path, $image->thumb_path);
        }

        ReviewSummaryCache::forget($locked->product_id);

        if ($from !== $locked->status || ($locked->isPublished() && $review->rating !== $locked->rating)) {
            event(new ReviewStatusChanged($locked->id, $locked->product_id, $from, $locked->status));
        }

        return $locked->load('images');
    }

    /**
     * "Priya S." — the first name and the last name's initial, never the e-mail or phone.
     */
    public function authorName(Customer $customer): string
    {
        $first = trim((string) $customer->first_name);
        $last = trim((string) $customer->last_name);

        if ($first === '') {
            return trans('review::front.card.anonymous');
        }

        return mb_substr($first, 0, 60).($last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : '');
    }

    /**
     * Rules the form requests check too, so service callers cannot skip them.
     *
     * @throws ReviewException
     */
    protected function validate(ReviewSubmission $submission, int $photoCount): void
    {
        if ($submission->rating < 1 || $submission->rating > 5) {
            throw ReviewException::field('rating', 'rating_required');
        }

        if ($submission->title !== null && mb_strlen($submission->title) > (int) config('review.title_max_length', 120)) {
            throw ReviewException::field('title', 'title_too_long', ['max' => (int) config('review.title_max_length', 120)]);
        }

        if ($submission->body !== null && mb_strlen($submission->body) > (int) config('review.body_max_length', 2000)) {
            throw ReviewException::field('body', 'body_too_long', ['max' => (int) config('review.body_max_length', 2000)]);
        }

        if ($submission->body !== null && mb_strlen($submission->body) < ReviewSettings::minBodyLength()) {
            throw ReviewException::field('body', 'body_too_short', ['min' => ReviewSettings::minBodyLength()]);
        }

        if ($photoCount > ReviewSettings::maxImages()) {
            throw ReviewException::field('photos', 'too_many_photos', ['max' => ReviewSettings::maxImages()]);
        }
    }

    /**
     * @param  list<array{image: string, thumb: string, width: int, height: int}>  $prepared
     * @return list<array{path: string, thumb_path: string, width: int, height: int}>
     */
    protected function storePhotos(ProductReview $review, array $prepared, int $firstPosition): array
    {
        $written = [];

        foreach ($prepared as $index => $photo) {
            $stored = $this->photos->put($photo, $review->id);
            $written[] = $stored;

            ProductReviewImage::query()->create($stored + ['product_review_id' => $review->id, 'position' => $firstPosition + $index]);
        }

        return $written;
    }

    protected function cleanUp(?int $reviewId): void
    {
        if ($reviewId !== null) {
            $this->photos->deleteFolder($reviewId);
        }
    }
}
