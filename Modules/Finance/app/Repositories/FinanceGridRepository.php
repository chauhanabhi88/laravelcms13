<?php

namespace Modules\Finance\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;

/**
 * The admin finance console grids (commission and fee rules, ledger, payout batches, holds,
 * tax documents, COD remittances, reseller withdrawals). Resolved per grid:
 * app(FinanceGridRepository::class, ['grid' => 'ledger']). Writes go through the finance
 * services, never through this repository.
 */
interface FinanceGridRepository extends BaseRepository
{
    public function grid(): string;

    /**
     * @param  array<string, array<string, string>>  $options  select options by filter name
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<Model>
     */
    public function filter(Request $request): Builder;
}
