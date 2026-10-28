<?php

namespace Modules\Cart\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Cart\Models\Cart;
use Modules\Core\Repositories\BaseRepository;

interface CartRepository extends BaseRepository
{
    /**
     * @param  array<string, array<string, string>>  $options  owner, type and state select options
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $options): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<Cart>
     */
    public function filter(Request $request): Builder;
}
