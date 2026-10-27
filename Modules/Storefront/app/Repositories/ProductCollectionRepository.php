<?php

namespace Modules\Storefront\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Storefront\Models\ProductCollection;

interface ProductCollectionRepository extends BaseRepository
{
    /**
     * @param  array<string|int, string>  $statusOptions
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $statusOptions): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<ProductCollection>
     */
    public function filter(Request $request): Builder;

    /**
     * Collections as select options (id => name).
     *
     * @return array<int|string, string>
     */
    public function getOptions(bool $withPlaceholder = false): array;
}
