<?php

namespace Modules\Storefront\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\BaseRepository;
use Modules\Storefront\Models\HomeSection;

interface HomeSectionRepository extends BaseRepository
{
    /**
     * @param  array<string|int, string>  $statusOptions
     * @param  array<string, string>  $typeOptions
     * @return array<int, array<string, mixed>>
     */
    public function getFilters(Request $request, array $statusOptions, array $typeOptions): array;

    public function pagination(Request $request): LengthAwarePaginator;

    /**
     * @return Builder<HomeSection>
     */
    public function filter(Request $request): Builder;

    /**
     * Swap a section with its neighbour above ("up") or below ("down") in sort order.
     */
    public function move(HomeSection $section, string $direction): void;

    /**
     * The next free sort order (after the last section).
     */
    public function nextSortOrder(): int;
}
