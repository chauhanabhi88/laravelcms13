<?php

namespace Modules\Payment\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Payment\Models\PaymentReconItem;

interface PaymentReconRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  issue and state select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<PaymentReconItem>
     */
    public function filter(Request $request): Builder;

    /**
     * Mark a mismatch as handled by Finance.
     */
    public function resolve(PaymentReconItem $item, int $adminId, ?string $note): PaymentReconItem;
}
