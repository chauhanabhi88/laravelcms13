<?php

namespace Modules\Payment\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Payment\Models\Payment;

interface PaymentRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  method and status select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<Payment>
     */
    public function filter(Request $request): Builder;
}
