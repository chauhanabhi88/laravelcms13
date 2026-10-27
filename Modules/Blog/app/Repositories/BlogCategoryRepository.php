<?php

namespace Modules\Blog\Repositories;

use Illuminate\Support\Collection;
use Modules\Blog\Models\BlogCategory;
use Modules\Core\Repositories\BaseRepository;

interface BlogCategoryRepository extends BaseRepository
{
    /**
     * Enabled categories with a published_posts_count attribute, by sort order.
     *
     * @return Collection<int, BlogCategory>
     */
    public function getActiveCategoriesWithPostCounts(): Collection;

    public function findActiveBySlug(string $slug): ?BlogCategory;
}
