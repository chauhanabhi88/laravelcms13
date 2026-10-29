<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewReport;
use Modules\Review\Models\ProductReviewVote;
use Modules\Review\Support\ReviewNotifier;
use Modules\Review\Support\ReviewSettings;

/**
 * Shoppers' feedback on published reviews (FR-REV-004): "Helpful" votes (toggle, one per
 * customer) and abuse reports (one per customer). Nobody votes on or reports their own
 * review. A review reaching the report threshold returns to the moderation queue and the
 * moderators are told; it stays visible until they decide.
 */
class ReviewFeedbackService
{
    public function __construct(private readonly ReviewNotifier $notifier) {}

    /**
     * @return array{voted: bool, helpful_count: int}
     *
     * @throws ReviewException
     */
    public function toggleVote(ProductReview $review, Customer $customer): array
    {
        $this->assertCanGiveFeedback($review, $customer);

        return DB::transaction(function () use ($review, $customer): array {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $vote = ProductReviewVote::query()->where('product_review_id', $locked->id)->where('customer_id', $customer->id);

            if ($vote->exists()) {
                $vote->delete();
                $count = max(0, $locked->helpful_count - 1);
                $voted = false;
            } else {
                DB::table('product_review_vote')->insert(['product_review_id' => $locked->id, 'customer_id' => $customer->id, 'created_at' => now()]);
                $count = $locked->helpful_count + 1;
                $voted = true;
            }

            ProductReview::query()->whereKey($locked->id)->toBase()->update(['helpful_count' => $count]);

            return ['voted' => $voted, 'helpful_count' => $count];
        });
    }

    /**
     * @throws ReviewException
     */
    public function report(ProductReview $review, Customer $customer, string $reason, ?string $comment = null): ProductReview
    {
        $this->assertCanGiveFeedback($review, $customer);

        if (! in_array($reason, (array) config('review.report_reasons', []), true)) {
            throw ReviewException::field('reason', 'report_reason');
        }

        [$locked, $reachedThreshold] = DB::transaction(function () use ($review, $customer, $reason, $comment): array {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);

            if (ProductReviewReport::query()->where('product_review_id', $locked->id)->where('customer_id', $customer->id)->exists()) {
                throw ReviewException::because('already_reported');
            }

            ProductReviewReport::query()->create([
                'product_review_id' => $locked->id,
                'customer_id' => $customer->id,
                'reason' => $reason,
                'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 255),
            ]);

            $threshold = ReviewSettings::reportThreshold();
            $count = $locked->report_count + 1;
            ProductReview::query()->whereKey($locked->id)->toBase()->update(['report_count' => $count]);
            $locked->report_count = $count;

            return [$locked, $count === $threshold];
        });

        if ($reachedThreshold) {
            DB::afterCommit(fn () => $this->notifier->reported($locked));
        }

        return $locked;
    }

    public function hasVoted(ProductReview $review, ?Customer $customer): bool
    {
        return $customer !== null && ProductReviewVote::query()->where('product_review_id', $review->id)->where('customer_id', $customer->id)->exists();
    }

    /**
     * @throws ReviewException
     */
    protected function assertCanGiveFeedback(ProductReview $review, Customer $customer): void
    {
        if (! $review->isPublished()) {
            throw ReviewException::because('not_found');
        }

        if ($review->customer_id === (int) $customer->id) {
            throw ReviewException::because('own_review');
        }
    }
}
