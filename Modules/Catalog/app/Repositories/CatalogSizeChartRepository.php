<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Core\Repositories\BaseRepository;

interface CatalogSizeChartRepository extends BaseRepository
{
    /**
     * @param  array<string|int, string>  $statusOptions
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $statusOptions): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<CatalogSizeChart>
     */
    public function filter(Request $request): Builder;

    /**
     * Enabled size charts for a select box, by name.
     *
     * @return array<int|string, string>
     */
    public function getOptions(bool $withPlaceholder = false): array;
}
