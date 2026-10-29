<?php

declare(strict_types=1);

namespace Modules\Promotion\Support;

use Modules\Product\Models\Product;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;
use Modules\Storefront\Contracts\HomeSectionProvider;
use Modules\Storefront\Services\ProductCardPresenter;

/**
 * Home section "flash_deal" (FR-PRM-003): the running deal that ends first, its products as
 * cards (at their deal prices) and a countdown to the end. Hidden when no deal runs. The
 * fragment is cached like every home section; saving a deal flushes it and the countdown
 * script hides the section once the deal is over.
 */
class FlashDealHomeSection implements HomeSectionProvider
{
    public const TYPE = 'flash_deal';

    public function __construct(private readonly ProductCardPresenter $cards) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function view(): string
    {
        return 'promotion::frontend.sections.flash_deal';
    }

    public function data(array $config): ?array
    {
        $deal = FlashDeal::query()
            ->running()
            ->whereHas('items')
            ->orderBy('ends_at')
            ->orderBy('id')
            ->first();

        if ($deal === null) {
            return null;
        }

        $limit = max(1, min(40, (int) ($config['limit'] ?? config('promotion.flash_deal_section_limit', 12))));
        $productIds = FlashDealItem::query()
            ->where('flash_deal_id', $deal->id)
            ->where(fn ($query) => $query->whereNull('stock_cap')->orWhereColumn('sold', '<', 'stock_cap'))
            ->orderBy('id')
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $visible = Product::query()->visibleOnStorefront()->whereIn('id', $productIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $ordered = array_slice(array_values(array_filter($productIds, fn (int $id): bool => in_array($id, $visible, true))), 0, $limit);

        if ($ordered === []) {
            return null;
        }

        return [
            'deal' => [
                'id' => $deal->id,
                'name' => $deal->name,
                'ends_at' => $deal->ends_at?->toIso8601String(),
                'banner' => $deal->banner,
            ],
            'cards' => $this->cards->present($ordered),
        ];
    }
}
