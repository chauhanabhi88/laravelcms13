<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Core\Repositories\BaseRepository;

interface CatalogBrandRepository extends BaseRepository
{
    /**
     * @param  array<string|int, string>  $statusOptions
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $statusOptions): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<CatalogBrand>
     */
    public function filter(Request $request): Builder;

    /**
     * Enabled brands whose name (any locale) or slug contains $term, by name.
     *
     * @return Collection<int, CatalogBrand>
     */
    public function searchEnabled(?string $term, int $limit): Collection;
}
