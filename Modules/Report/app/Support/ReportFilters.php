<?php

declare(strict_types=1);

namespace Modules\Report\Support;

/**
 * The one dimension a report is narrowed to: a seller, an L1 category, a shipping state or a
 * payment type. Aggregates are stored per single dimension (plan M19 §3.1), so when several
 * are given the first in that order wins and a warning is shown.
 */
final class ReportFilters
{
    /**
     * @param  list<string>  $warnings  lang keys under report::report.warnings
     */
    public function __construct(
        public readonly ?int $sellerId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $stateId = null,
        public readonly ?string $payment = null,
        public readonly array $warnings = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function forSeller(int $sellerId): self
    {
        return new self(sellerId: $sellerId);
    }

    /**
     * @param  array<string, mixed>  $input  seller_id, category_id, state_id, payment
     */
    public static function fromInput(array $input): self
    {
        $seller = self::id($input['seller_id'] ?? null);
        $category = self::id($input['category_id'] ?? null);
        $state = self::id($input['state_id'] ?? null);
        $payment = in_array($input['payment'] ?? null, Grain::PAYMENTS, true) ? (string) $input['payment'] : null;

        $given = array_filter(['seller' => $seller, 'category' => $category, 'state' => $state, 'payment' => $payment], fn ($value): bool => $value !== null);

        if (count($given) <= 1) {
            return new self($seller, $category, $state, $payment);
        }

        $first = array_key_first($given);

        return new self(
            $first === 'seller' ? $seller : null,
            $first === 'category' ? $category : null,
            $first === 'state' ? $state : null,
            $first === 'payment' ? $payment : null,
            ['one_filter'],
        );
    }

    public function isEmpty(): bool
    {
        return $this->dimension() === null;
    }

    public function dimension(): ?string
    {
        return match (true) {
            $this->sellerId !== null => Grain::DIMENSION_SELLER,
            $this->categoryId !== null => Grain::DIMENSION_CATEGORY,
            $this->stateId !== null => Grain::DIMENSION_STATE,
            $this->payment !== null => Grain::DIMENSION_PAYMENT,
            default => null,
        };
    }

    public function grainKey(): string
    {
        return Grain::key($this->sellerId ?? 0, $this->categoryId ?? 0, $this->stateId ?? 0, $this->payment ?? Grain::ALL);
    }

    /**
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'seller_id' => $this->sellerId,
            'category_id' => $this->categoryId,
            'state_id' => $this->stateId,
            'payment' => $this->payment,
        ], fn ($value): bool => $value !== null);
    }

    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
