<?php

namespace Modules\Product\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Product\Models\Product;

interface ProductRepository extends BaseRepository
{
    public const GRID_ALL = 'product';

    public const GRID_QC = 'product_qc';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, string $grid): array;

    public function pagination(Request $request, string $grid): LengthAwarePaginator;

    /**
     * @return Builder<Product>
     */
    public function filter(Request $request, string $grid): Builder;

    /**
     * A product with everything the admin detail and QC review screens show.
     */
    public function findWithDetails(int $id): ?Product;

    /**
     * The oldest queued products, for the review screen's queue pane and J / K navigation.
     *
     * @return Collection<int, Product>
     */
    public function qcQueue(int $limit): Collection;

    /**
     * Rows of the filtered product grid for the CSV export.
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(Request $request): array;
}
