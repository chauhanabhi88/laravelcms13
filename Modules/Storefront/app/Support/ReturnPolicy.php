<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Product\Models\Product;

/**
 * The effective return policy of a product: its own override, else the category default
 * (CategoryDefaults, inherited along the tree).
 */
final readonly class ReturnPolicy
{
    public const NONE = 'none';

    public const EXCHANGE_ONLY = 'exchange_only';

    public const RETURN_EXCHANGE = 'return_exchange';

    public function __construct(public int $days, public string $type) {}

    /**
     * @param  array{return_window_days: int, return_type: string}  $categoryDefaults  CategoryDefaults::for()
     */
    public static function for(Product $product, array $categoryDefaults): self
    {
        $days = $product->return_window_days ?? (int) $categoryDefaults['return_window_days'];
        $type = $product->return_type !== null && $product->return_type !== '' ? $product->return_type : (string) $categoryDefaults['return_type'];

        return new self(max(0, (int) $days), $type);
    }

    public function allowsReturns(): bool
    {
        return $this->days > 0 && $this->type !== self::NONE;
    }

    /**
     * "7-day easy return & exchange", "7-day exchange only" or "No returns on this item".
     */
    public function summary(): string
    {
        if (! $this->allowsReturns()) {
            return trans('storefront::product.returns.none');
        }

        return trans($this->type === self::EXCHANGE_ONLY ? 'storefront::product.returns.exchange_only' : 'storefront::product.returns.return_exchange', ['days' => $this->days]);
    }
}
