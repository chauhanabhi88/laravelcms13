<?php

namespace Modules\Order\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Order\Models\SalesOrder;

interface SalesOrderRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  status, payment method and reseller select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<SalesOrder>
     */
    public function filter(Request $request): Builder;
}
