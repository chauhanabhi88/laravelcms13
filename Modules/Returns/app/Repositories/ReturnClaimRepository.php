<?php

namespace Modules\Returns\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Returns\Models\ReturnClaim;

interface ReturnClaimRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  status and reason select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<ReturnClaim>
     */
    public function filter(Request $request): Builder;
}
