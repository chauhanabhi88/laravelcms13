<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogBrandTranslation;
use Modules\Seller\Models\Seller;

/**
 * "Request a new brand" from the listing wizard (FR-CAT-005): a pending catalog_brand row
 * (status 2, requested_by_seller_id) that the requesting seller may already list under and
 * an admin approves in Catalog › Brands.
 */
class BrandRequestService
{
    /**
     * Brands the seller can pick: enabled ones and their own pending requests.
     *
     * @return array<int, string> id => name (pending requests marked)
     */
    public function options(Seller $seller): array
    {
        return CatalogBrand::query()
            ->where(fn ($query) => $query->where('status', config('core.enabled'))->orWhere('requested_by_seller_id', $seller->id))
            ->with('translations')
            ->orderBy('id')
            ->limit(3000)
            ->get()
            ->mapWithKeys(fn (CatalogBrand $brand): array => [
                $brand->id => (string) $brand->name.($brand->isPendingRequest() ? ' ('.trans('product::seller.wizard.details.brand_pending').')' : ''),
            ])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    /**
     * The brand called $name that the seller may use, created as a pending request when none
     * exists yet.
     */
    public function request(Seller $seller, string $name): CatalogBrand
    {
        $name = Str::limit(trim((string) preg_replace('/\s+/', ' ', $name)), 120, '');

        $existing = CatalogBrand::query()
            ->where(fn ($query) => $query->where('status', config('core.enabled'))->orWhere('requested_by_seller_id', $seller->id))
            ->whereHas('translations', fn ($query) => $query->where('name', $name))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($seller, $name): CatalogBrand {
            $base = Str::slug($name) ?: 'brand';
            $slug = $base;

            while (CatalogBrand::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.Str::lower(Str::random(5));
            }

            $brand = CatalogBrand::query()->create([
                'slug' => Str::limit($slug, 160, ''),
                'is_verified' => config('core.no'),
                'requested_by_seller_id' => $seller->id,
                'status' => config('core.disabled'),
            ]);

            CatalogBrandTranslation::query()->create([
                'catalog_brand_id' => $brand->id,
                'locale' => (string) (config('translatable.fallback_locale') ?: config('app.fallback_locale', 'en')),
                'name' => $name,
            ]);

            return $brand;
        });
    }
}
