<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

/**
 * Review state of one order item on the customer's order pages (filled by the Review module).
 * $createUrl takes ?rating=1…5 to prefill the stars.
 */
final readonly class ItemReviewState
{
    public function __construct(
        public int $itemId,
        public ?string $createUrl = null,
        public ?int $rating = null,
        public ?string $editUrl = null,
        public ?string $statusLabel = null,
    ) {}

    public function canReview(): bool
    {
        return $this->createUrl !== null;
    }

    public function isReviewed(): bool
    {
        return $this->rating !== null;
    }

    public function createUrlFor(int $stars): ?string
    {
        if ($this->createUrl === null) {
            return null;
        }

        return $this->createUrl.(str_contains($this->createUrl, '?') ? '&' : '?').'rating='.max(1, min(5, $stars));
    }
}
