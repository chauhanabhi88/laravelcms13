<?php

namespace Modules\Blog\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Blog\Models\BlogPost;
use Modules\Core\Repositories\BaseRepository;

interface BlogPostRepository extends BaseRepository
{
    /**
     * Published posts for the public listing, newest first.
     *
     * The page is an explicit argument (not read from the request) so the
     * cache decorator keys on it.
     *
     * @param  array{q?: string|null, category?: string|null}  $filters
     */
    public function getPublishedPosts(array $filters, int $perPage, int $page = 1): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?BlogPost;

    /**
     * @return Collection<int, BlogPost>
     */
    public function getFeaturedPosts(int $limit): Collection;

    /**
     * Published posts sharing a category with $post, topped up with the latest posts.
     *
     * @return Collection<int, BlogPost>
     */
    public function getRelatedPosts(BlogPost $post, int $limit): Collection;
}
