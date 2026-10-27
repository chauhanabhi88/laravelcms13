<?php

namespace Modules\Seller\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Seller\Models\Seller;

interface SellerRepository extends BaseRepository
{
    public const GRID_SELLERS = 'seller';

    public const GRID_APPLICATIONS = 'seller_application';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, string $grid): array;

    public function pagination(Request $request, string $grid): LengthAwarePaginator;

    /**
     * @return Builder<Seller>
     */
    public function filter(Request $request, string $grid): Builder;

    /**
     * A seller with everything the admin detail screen shows.
     */
    public function findWithDetails(int $id): ?Seller;

    /**
     * The public store page: active (or suspended, shown without products) sellers only.
     */
    public function findPublicBySlug(string $slug): ?Seller;

    /**
     * @return array<string, string>
     */
    public function getGstStatusOptions(bool $withPlaceholder = false): array;
}
