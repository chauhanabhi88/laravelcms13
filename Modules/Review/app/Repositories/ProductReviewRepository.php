<?php

namespace Modules\Review\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Review\Models\ProductReview;

interface ProductReviewRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string|int, string>>  $options  queue, status, rating and flag select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<ProductReview>
     */
    public function filter(Request $request): Builder;
}
