<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

/**
 * One page of a listing: product ids in display order, their card DTOs and, on first pages,
 * the total. $textMode records which full-text strategy matched (natural, boolean, like,
 * none without a query).
 */
final readonly class ListingResult
{
    /**
     * @param  list<int>  $productIds
     * @param  list<array<string, mixed>>  $cards
     */
    public function __construct(
        public array $productIds,
        public array $cards,
        public ?int $total,
        public int $page,
        public int $perPage,
        public bool $hasMore,
        public string $textMode = 'none',
    ) {}

    public function isEmpty(): bool
    {
        return $this->productIds === [];
    }

    public function firstItem(): int
    {
        return $this->isEmpty() ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastItem(): int
    {
        return $this->isEmpty() ? 0 : $this->firstItem() + count($this->productIds) - 1;
    }

    public function lastPage(): ?int
    {
        return $this->total === null ? null : max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
