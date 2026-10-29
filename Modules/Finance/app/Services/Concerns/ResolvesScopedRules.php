<?php

declare(strict_types=1);

namespace Modules\Finance\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\CatalogCategory;

/**
 * Picks the most specific rule in force (plan M17 §5.1): seller_category > seller > category >
 * global. Category scopes match the category or an ancestor; the nearest one wins. Ties go to
 * the latest effective_from, then the newest rule.
 */
trait ResolvesScopedRules
{
    /**
     * @param  Builder<Model>  $query  rules in force on the date (already filtered by code etc.)
     */
    protected function pickRule(Builder $query, ?int $sellerId, ?CatalogCategory $category): ?Model
    {
        $pathIds = $category?->pathIds() ?? [];

        if ($category !== null && $pathIds === []) {
            $pathIds = [$category->id];
        }

        /** @var Collection<int, Model> $rules */
        $rules = $query->where(function (Builder $scopes) use ($sellerId, $pathIds): void {
            $scopes->where('scope', 'global');

            if ($pathIds !== []) {
                $scopes->orWhere(fn (Builder $q) => $q->where('scope', 'category')->whereIn('catalog_category_id', $pathIds));
            }

            if ($sellerId !== null) {
                $scopes->orWhere(fn (Builder $q) => $q->where('scope', 'seller')->where('seller_id', $sellerId));

                if ($pathIds !== []) {
                    $scopes->orWhere(fn (Builder $q) => $q->where('scope', 'seller_category')->where('seller_id', $sellerId)->whereIn('catalog_category_id', $pathIds));
                }
            }
        })->get();

        $depth = array_flip($pathIds);
        $specificity = ['seller_category' => 4, 'seller' => 3, 'category' => 2, 'global' => 1];

        return $rules->sort(function (Model $a, Model $b) use ($depth, $specificity): int {
            return [
                $specificity[$b->getAttribute('scope')] ?? 0,
                $depth[(int) $b->getAttribute('catalog_category_id')] ?? -1,
                $b->getAttribute('effective_from')?->toDateString(),
                $b->getKey(),
            ] <=> [
                $specificity[$a->getAttribute('scope')] ?? 0,
                $depth[(int) $a->getAttribute('catalog_category_id')] ?? -1,
                $a->getAttribute('effective_from')?->toDateString(),
                $a->getKey(),
            ];
        })->first();
    }

    protected function on(?Carbon $on): Carbon
    {
        return ($on ?? Carbon::now())->copy()->startOfDay();
    }
}
