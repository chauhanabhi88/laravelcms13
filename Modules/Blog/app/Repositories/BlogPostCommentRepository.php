<?php

namespace Modules\Blog\Repositories;

use Illuminate\Support\Collection;
use Modules\Blog\Models\BlogPostComment;
use Modules\Core\Repositories\BaseRepository;

interface BlogPostCommentRepository extends BaseRepository
{
    /**
     * Approved comments for a post, oldest first, with their customer.
     *
     * @return Collection<int, BlogPostComment>
     */
    public function getApprovedForPost(int $postId): Collection;

    /**
     * Store a customer comment awaiting moderation.
     *
     * @param  array{subject: string, comment: string}  $data
     */
    public function createPendingComment(int $postId, int $customerId, array $data): BlogPostComment;
}
