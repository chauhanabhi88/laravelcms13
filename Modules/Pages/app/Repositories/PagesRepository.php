<?php

namespace Modules\Pages\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\BaseRepository;

interface PagesRepository extends BaseRepository
{
    /**
     * Enabled page by slug, with translations, or null.
     */
    public function findPublishedBySlug(string $slug);

    /**
     * Enabled pages for the given slugs, in the given order.
     *
     * @param  array<int, string>  $slugs
     */
    public function getPublishedBySlugs(array $slugs): Collection;
}
