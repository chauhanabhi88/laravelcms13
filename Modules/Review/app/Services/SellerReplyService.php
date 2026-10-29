<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Support\Facades\DB;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ReviewSummaryCache;
use Modules\Seller\Models\SellerUser;

/**
 * The seller's public reply to a published review of their product (FR-REV-006): one per
 * review, checked by the same profanity / link / contact filter as reviews. A reply that
 * fails the checks is refused (the seller rephrases), so replies never wait for moderation.
 */
class SellerReplyService
{
    public function __construct(private readonly ReviewModerationService $moderation) {}

    /**
     * @throws ReviewException
     */
    public function reply(ProductReview $review, SellerUser $user, string $text): ProductReview
    {
        if ($review->seller_id !== (int) $user->seller_id) {
            throw ReviewException::because('not_found');
        }

        $text = (string) ReviewSubmission::clean($text, false);
        $min = (int) config('review.reply_min_length', 2);
        $max = (int) config('review.reply_max_length', 1000);

        if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            throw ReviewException::field('reply', 'reply_length', ['min' => $min, 'max' => $max]);
        }

        if ($this->moderation->textFlags($text) !== []) {
            throw ReviewException::field('reply', 'reply_flagged');
        }

        $locked = DB::transaction(function () use ($review, $user, $text): ProductReview {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);

            if (! $locked->isPublished()) {
                throw ReviewException::because('not_published');
            }

            if ($locked->hasSellerReply()) {
                throw ReviewException::because('already_replied');
            }

            $locked->forceFill(['seller_reply' => $text, 'seller_replied_at' => now(), 'seller_replied_by' => $user->id])->save();

            return $locked;
        });

        ReviewSummaryCache::forget($locked->product_id);

        return $locked;
    }
}
