<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Review\Events\ReviewStatusChanged;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ProfanityFilter;
use Modules\Review\Support\ReviewNotifier;
use Modules\Review\Support\ReviewPhotoStore;
use Modules\Review\Support\ReviewSettings;
use Modules\Review\Support\ReviewSummaryCache;
use Modules\User\Models\User;

/**
 * Keeps reviews clean (FR-REV-002, plan M16 §5): the automatic checks run on every new or
 * edited review (profanity, links, phone numbers / e-mail addresses, a too-short text, the
 * same text in another review of the customer), then the moderators' queue actions —
 * publish (also "keep" for a reported review), reject with a reason (the customer is told),
 * hide and delete.
 */
class ReviewModerationService
{
    /**
     * Statuses each action may start from.
     */
    private const FROM = [
        'publish' => [ProductReview::STATUS_PENDING, ProductReview::STATUS_REJECTED, ProductReview::STATUS_HIDDEN, ProductReview::STATUS_PUBLISHED],
        'reject' => [ProductReview::STATUS_PENDING, ProductReview::STATUS_PUBLISHED, ProductReview::STATUS_HIDDEN],
        'hide' => [ProductReview::STATUS_PENDING, ProductReview::STATUS_PUBLISHED],
    ];

    public function __construct(
        private readonly ProfanityFilter $profanity,
        private readonly ReviewPhotoStore $photos,
        private readonly ReviewNotifier $notifier,
    ) {}

    /**
     * Findings of the automatic checks on a review's text (empty = clean).
     *
     * @return list<string> ProductReview::FLAG_* values
     */
    public function autoCheck(int $customerId, ?string $title, ?string $body, ?int $ignoreReviewId = null): array
    {
        $flags = $this->textFlags(trim($title.' '.$body));

        if ($body !== null && $body !== '' && mb_strlen($body) < ReviewSettings::minBodyLength()) {
            $flags[] = ProductReview::FLAG_TOO_SHORT;
        }

        if ($body !== null && $this->isDuplicate($customerId, $body, $ignoreReviewId)) {
            $flags[] = ProductReview::FLAG_DUPLICATE;
        }

        return array_values(array_unique($flags));
    }

    /**
     * Profanity, links and contact details in any text (reviews and seller replies).
     *
     * @return list<string>
     */
    public function textFlags(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        $flags = [];
        $patterns = (array) config('review.patterns', []);

        if ($this->profanity->contains($text)) {
            $flags[] = ProductReview::FLAG_PROFANITY;
        }

        if (isset($patterns['link']) && preg_match((string) $patterns['link'], $text) === 1) {
            $flags[] = ProductReview::FLAG_LINK;
        }

        foreach (['phone', 'email'] as $pattern) {
            if (isset($patterns[$pattern]) && preg_match((string) $patterns[$pattern], $text) === 1) {
                $flags[] = ProductReview::FLAG_CONTACT;
                break;
            }
        }

        return $flags;
    }

    /**
     * Status of a review after the checks: published when clean and auto-publishing is on.
     *
     * @param  list<string>  $flags
     */
    public function statusFor(array $flags): string
    {
        return $flags === [] && ReviewSettings::autoPublish() ? ProductReview::STATUS_PUBLISHED : ProductReview::STATUS_PENDING;
    }

    /**
     * Publish a waiting, rejected or hidden review; on a published (reported) review this
     * keeps it and clears its report count, taking it out of the queue.
     */
    public function publish(ProductReview $review, ?User $admin = null): ProductReview
    {
        return $this->moderate($review, 'publish', $admin, function (ProductReview $locked): void {
            $locked->forceFill([
                'status' => ProductReview::STATUS_PUBLISHED,
                'rejection_reason' => null,
                'report_count' => 0,
                'published_at' => $locked->isPublished() ? $locked->published_at : now(),
            ]);
        });
    }

    public function reject(ProductReview $review, string $reason, ?User $admin = null): ProductReview
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ReviewException::field('rejection_reason', 'reason_required');
        }

        $review = $this->moderate($review, 'reject', $admin, function (ProductReview $locked) use ($reason): void {
            $locked->forceFill(['status' => ProductReview::STATUS_REJECTED, 'rejection_reason' => Str::limit($reason, 250, '')]);
        });

        DB::afterCommit(fn () => $this->notifier->rejected($review));

        return $review;
    }

    public function hide(ProductReview $review, ?string $reason = null, ?User $admin = null): ProductReview
    {
        $reason = trim((string) $reason);

        return $this->moderate($review, 'hide', $admin, function (ProductReview $locked) use ($reason): void {
            $locked->forceFill(['status' => ProductReview::STATUS_HIDDEN, 'rejection_reason' => $reason === '' ? null : Str::limit($reason, 250, '')]);
        });
    }

    /**
     * Remove the review, its photos, votes and reports for good.
     */
    public function delete(ProductReview $review): void
    {
        $from = $review->status;

        DB::transaction(function () use ($review): void {
            ProductReview::query()->whereKey($review->id)->lockForUpdate()->first();
            $review->delete();
        });

        $this->photos->deleteFolder($review->id);
        ReviewSummaryCache::forget($review->product_id);

        event(new ReviewStatusChanged($review->id, $review->product_id, $from, ProductReview::DELETED));
    }

    /**
     * @param  callable(ProductReview): void  $change  fills the locked row
     */
    protected function moderate(ProductReview $review, string $action, ?User $admin, callable $change): ProductReview
    {
        [$locked, $from] = DB::transaction(function () use ($review, $action, $admin, $change): array {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $from = $locked->status;

            if (! in_array($from, self::FROM[$action], true)) {
                throw ReviewException::because('cannot_'.$action);
            }

            $change($locked);
            $locked->forceFill(['moderated_by' => $admin?->id, 'moderated_at' => now()])->save();

            return [$locked, $from];
        });

        ReviewSummaryCache::forget($locked->product_id);

        if ($from !== $locked->status) {
            event(new ReviewStatusChanged($locked->id, $locked->product_id, $from, $locked->status));
        }

        return $locked;
    }

    /**
     * The same text (case, spacing and punctuation ignored) in another review by the customer.
     */
    protected function isDuplicate(int $customerId, string $body, ?int $ignoreReviewId): bool
    {
        $normalised = $this->normalise($body);

        if (mb_strlen($normalised) < 3) {
            return false;
        }

        return ProductReview::query()
            ->forCustomer($customerId)
            ->whereNotNull('body')
            ->when($ignoreReviewId !== null, fn ($query) => $query->whereKeyNot($ignoreReviewId))
            ->latest('id')
            ->limit(200)
            ->pluck('body')
            ->contains(fn (?string $other): bool => $this->normalise((string) $other) === $normalised);
    }

    protected function normalise(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', Str::lower($text));
    }
}
