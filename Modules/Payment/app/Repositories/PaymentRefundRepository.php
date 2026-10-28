<?php

namespace Modules\Payment\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Payment\Models\PaymentRefund;

interface PaymentRefundRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  status and mode select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<PaymentRefund>
     */
    public function filter(Request $request): Builder;
}
