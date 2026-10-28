<?php

namespace Modules\Logistics\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Logistics\Models\Shipment;

interface ShipmentRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string|int, string>>  $options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * Grid query: status, carrier, courier, payment mode, AWB / order number search, stuck in
     * transit, needs attention.
     *
     * @return Builder<Shipment>
     */
    public function filter(Request $request): Builder;

    /**
     * A shipment with everything the detail screen shows.
     */
    public function findForShow(int $id): ?Shipment;
}
