<?php

declare(strict_types=1);

namespace Modules\Returns\DTO;

use Illuminate\Support\Carbon;
use Modules\Order\Models\SalesOrderItem;
use Modules\Returns\Models\ReturnRequest;

/**
 * An order item the customer may return or exchange now (plan M15 §5.2).
 */
final readonly class ReturnableItem
{
    /**
     * @param  list<string>  $allowedTypes  return and/or exchange
     * @param  list<array{id: int, label: string, available: int}>  $exchangeVariants  other in-stock variants of the same product
     */
    public function __construct(
        public SalesOrderItem $item,
        public int $maxQty,
        public Carbon $windowEndsAt,
        public array $allowedTypes,
        public array $exchangeVariants,
    ) {}

    public function allows(string $type): bool
    {
        return in_array($type, $this->allowedTypes, true);
    }

    public function allowsExchangeTo(int $variantId): bool
    {
        return $this->allows(ReturnRequest::TYPE_EXCHANGE)
            && in_array($variantId, array_column($this->exchangeVariants, 'id'), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->item->id,
            'name' => $this->item->name,
            'variant_label' => $this->item->variant_label,
            'max_qty' => $this->maxQty,
            'window_ends_at' => $this->windowEndsAt->toIso8601String(),
            'allowed_types' => $this->allowedTypes,
            'exchange_variants' => $this->exchangeVariants,
        ];
    }
}
