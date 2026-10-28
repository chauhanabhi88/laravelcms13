<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * The lines of one seller (FR-CRT-002): each group ships as its own package with its own
 * estimated delivery date.
 */
final readonly class CartGroup
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public int $sellerId,
        public string $sellerName,
        public ?string $sellerUrl,
        public array $lines,
        public ?string $eddText = null,
    ) {}

    public function subtotal(): Money
    {
        return array_reduce($this->lines, fn (Money $sum, CartLine $line): Money => $sum->plus($line->lineTotal()), Money::zero());
    }

    public function quantity(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'seller_id' => $this->sellerId,
            'seller_name' => $this->sellerName,
            'seller_url' => $this->sellerUrl,
            'edd_text' => $this->eddText,
            'subtotal' => $this->subtotal()->toDecimal(),
            'lines' => array_map(fn (CartLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}
