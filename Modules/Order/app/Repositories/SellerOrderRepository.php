<?php

namespace Modules\Order\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Order\Models\SellerOrder;

interface SellerOrderRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  status and SLA select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<SellerOrder>
     */
    public function filter(Request $request): Builder;
}
