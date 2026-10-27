<?php

declare(strict_types=1);

namespace Modules\Product\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Repositories\SellerCatalogRepository;

class EloquentSellerCatalogRepository extends EloquentBaseRepository implements SellerCatalogRepository
{
    public function products(int $sellerId, string $tab, ?string $search, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->forSeller($sellerId)
            ->with(['translations'])
            ->withCount('variants');

        $this->applyTab($query, $tab);

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $query) use ($like, $search): void {
                $query->whereHas('translations', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('variants', fn (Builder $query) => $query->where('seller_sku', 'like', $like))
                    ->when(ctype_digit($search), fn (Builder $query) => $query->orWhere('product.id', (int) $search));
            });
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function tabCounts(int $sellerId): array
    {
        $byStatus = Product::query()
            ->forSeller($sellerId)
            ->toBase()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $counts = [self::TAB_ALL => array_sum($byStatus)];

        foreach (ProductStatus::cases() as $status) {
            $counts[$status->value] = $byStatus[$status->value] ?? 0;
        }

        $outOfStock = Product::query()->forSeller($sellerId);
        $this->applyTab($outOfStock, self::TAB_OUT_OF_STOCK);
        $counts[self::TAB_OUT_OF_STOCK] = $outOfStock->count();

        return $counts;
    }

    public function inventory(int $sellerId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->inventoryQuery($sellerId, $filters)->paginate($perPage)->withQueryString();
    }

    public function inventoryExport(int $sellerId, array $filters): LazyCollection
    {
        return $this->inventoryQuery($sellerId, $filters)->reorder()->lazyById(500, 'product_variant.id', 'id');
    }

    public function lowStockCount(int $sellerId): int
    {
        return $this->inventoryQuery($sellerId, ['stock' => 'low'])->count();
    }

    public function uploads(int $sellerId, ?string $type, int $perPage): LengthAwarePaginator
    {
        return CatalogUpload::query()
            ->where('seller_id', $sellerId)
            ->when($type !== null && $type !== '', fn (Builder $query) => $query->where('type', $type))
            ->with(['uploader'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function priceStockRows(int $sellerId): array
    {
        return ProductVariant::query()
            ->where('seller_id', $sellerId)
            ->whereHas('product')
            ->orderBy('product_id')
            ->orderBy('position')
            ->get(['seller_sku', 'mrp', 'price', 'stock'])
            ->map(fn (ProductVariant $variant): array => [
                'seller_sku' => $variant->seller_sku,
                'mrp' => $variant->mrp->toDecimal(),
                'price' => $variant->price->toDecimal(),
                'stock' => $variant->stock,
            ])
            ->all();
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function applyTab(Builder $query, string $tab): void
    {
        if ($tab === self::TAB_OUT_OF_STOCK) {
            $query->whereIn('product.status', [ProductStatus::Live->value, ProductStatus::Paused->value])
                ->where('product.total_stock', '<=', 0);

            return;
        }

        if (ProductStatus::tryFrom($tab) !== null) {
            $query->where('product.status', $tab);
        }
    }

    /**
     * @param  array{q?: string|null, stock?: string|null, category?: int|string|null}  $filters
     * @return Builder<ProductVariant>
     */
    protected function inventoryQuery(int $sellerId, array $filters): Builder
    {
        $query = ProductVariant::query()
            ->select('product_variant.*')
            ->join('product', 'product.id', '=', 'product_variant.product_id')
            ->whereNull('product.deleted_at')
            ->where('product_variant.seller_id', $sellerId)
            ->with(['product.translations', 'attributeValues'])
            ->orderByDesc('product_variant.product_id')
            ->orderBy('product_variant.position')
            ->orderBy('product_variant.id');

        $available = '(product_variant.stock - product_variant.reserved)';

        match ($filters['stock'] ?? null) {
            'low' => $query->where('product_variant.status', config('core.enabled'))->whereRaw("{$available} <= product_variant.low_stock_threshold"),
            'out' => $query->whereRaw("{$available} <= 0"),
            default => null,
        };

        if (! empty($filters['category'])) {
            $query->where('product.catalog_category_id', (int) $filters['category']);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('product_variant.seller_sku', 'like', $like)
                    ->orWhere('product_variant.sku', 'like', $like)
                    ->orWhereIn('product_variant.product_id', fn ($sub) => $sub->select('product_id')->from('product_translation')->where('name', 'like', $like));
            });
        }

        return $query;
    }
}
