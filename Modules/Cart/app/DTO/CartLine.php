<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * One revalidated cart line, ready for the cart page, the mini-cart, the API and checkout.
 * Prices are the live variant prices; price_at_add only feeds the "price changed" issue.
 */
final readonly class CartLine
{
    /**
     * @param  list<LineIssue>  $issues
     */
    public function __construct(
        public int $itemId,
        public int $productId,
        public int $variantId,
        public int $sellerId,
        public string $name,
        public string $variantLabel,
        public string $sku,
        public ?string $imageUrl,
        public ?string $productUrl,
        public int $quantity,
        public int $maxQuantity,
        public Money $unitPrice,
        public Money $unitMrp,
        public int $available,
        public bool $codAllowed,
        public bool $savedForLater,
        public array $issues = [],
    ) {}

    public function lineTotal(): Money
    {
        return $this->unitPrice->times($this->quantity);
    }

    public function lineMrp(): Money
    {
        $mrp = $this->unitMrp->compare($this->unitPrice) > 0 ? $this->unitMrp : $this->unitPrice;

        return $mrp->times($this->quantity);
    }

    /**
     * In stock, live and from an active seller: counts towards the totals.
     */
    public function isPurchasable(): bool
    {
        return ! $this->hasBlockingIssue();
    }

    public function hasBlockingIssue(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->blocking) {
                return true;
            }
        }

        return false;
    }

    public function hasIssue(string $type): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->itemId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'seller_id' => $this->sellerId,
            'name' => $this->name,
            'variant_label' => $this->variantLabel,
            'sku' => $this->sku,
            'image' => $this->imageUrl,
            'url' => $this->productUrl,
            'quantity' => $this->quantity,
            'max_quantity' => $this->maxQuantity,
            'price' => $this->unitPrice->toDecimal(),
            'mrp' => $this->unitMrp->toDecimal(),
            'line_total' => $this->lineTotal()->toDecimal(),
            'cod_allowed' => $this->codAllowed,
            'saved_for_later' => $this->savedForLater,
            'purchasable' => $this->isPurchasable(),
            'issues' => array_map(fn (LineIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
