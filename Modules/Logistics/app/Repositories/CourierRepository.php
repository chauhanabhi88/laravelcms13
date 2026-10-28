<?php

namespace Modules\Logistics\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Logistics\Models\Courier;

interface CourierRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string|int, string>>  $options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<Courier>
     */
    public function filter(Request $request): Builder;

    /**
     * Couriers for a select box (id => name), active ones only unless $all.
     *
     * @return array<int|string, string>
     */
    public function getOptions(bool $withPlaceholder = false, bool $all = false): array;
}
