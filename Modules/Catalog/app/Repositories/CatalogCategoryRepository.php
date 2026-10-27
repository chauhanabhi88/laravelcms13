<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Repositories\BaseRepository;

interface CatalogCategoryRepository extends BaseRepository
{
    /**
     * @param  array<string|int, string>  $statusOptions
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $statusOptions): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * Grid query with the request / session filters applied. Never cached (it is a Builder).
     *
     * @return Builder<CatalogCategory>
     */
    public function filter(Request $request): Builder;

    /**
     * An enabled category by slug, with translations; null when missing or disabled.
     */
    public function findEnabledBySlug(string $slug): ?CatalogCategory;
}
