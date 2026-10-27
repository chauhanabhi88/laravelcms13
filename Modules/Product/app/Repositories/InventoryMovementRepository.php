<?php

namespace Modules\Product\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Product\Models\InventoryMovement;

interface InventoryMovementRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<InventoryMovement>
     */
    public function filter(Request $request): Builder;
}
