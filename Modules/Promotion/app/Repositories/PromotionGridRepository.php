<?php

namespace Modules\Promotion\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;

/**
 * The admin promotion grids (coupons, flash deals). Resolved per grid:
 * app(PromotionGridRepository::class, ['grid' => 'flash_deal']). Writes go through CouponService
 * and FlashDealService, never through this repository.
 */
interface PromotionGridRepository extends BaseRepository
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
