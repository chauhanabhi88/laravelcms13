<?php

declare(strict_types=1);

namespace Modules\Storefront\DTO;

/**
 * Ratings summary of a product (FR-PDP-008).
 *
 * @phpstan-type Photo array{url: string, thumb: string|null, alt: string|null}
 * @phpstan-type Review array{author: string, rating: int, title: string|null, body: string, date: string|null, photos: list<Photo>}
 */
final readonly class ReviewSummary
{
    /**
     * @param  array<int, int>  $histogram  stars (5 … 1) => number of ratings
     * @param  list<Photo>  $photos  at most 10
     * @param  list<Review>  $top  at most 3
     */
    public function __construct(
        public float $average = 0.0,
        public int $count = 0,
        public array $histogram = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        public array $photos = [],
        public array $top = [],
        public ?string $allReviewsUrl = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function hasRatings(): bool
    {
        return $this->count > 0;
    }

    /**
     * Share of ratings per star (5 … 1), 0–100.
     *
     * @return array<int, int>
     */
    public function percentages(): array
    {
        $percentages = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $percentages[$stars] = $this->count > 0 ? (int) round(($this->histogram[$stars] ?? 0) * 100 / $this->count) : 0;
        }

        return $percentages;
    }

    /**
     * @return array{avg: float, count: int, histogram: array<int, int>, photos: list<Photo>, top: list<Review>}
     */
    public function toArray(): array
    {
        return [
            'avg' => round($this->average, 1),
            'count' => $this->count,
            'histogram' => $this->histogram,
            'photos' => $this->photos,
            'top' => $this->top,
        ];
    }
}
