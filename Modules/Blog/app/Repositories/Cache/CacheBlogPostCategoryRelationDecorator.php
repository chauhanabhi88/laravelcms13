<?php

namespace Modules\Blog\Repositories\Cache;

use Modules\Blog\Repositories\BlogPostCategoryRelationRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlogPostCategoryRelationDecorator extends BaseCacheDecorator implements BlogPostCategoryRelationRepository
{
    public function __construct(BlogPostCategoryRelationRepository $blog_post)
    {
        parent::__construct();
        $this->entityName = \config("blog.cache.blog_post_category_name");
        $this->repository = $blog_post;
    }

    public function getAllPostCategories($id)
    {
        return $this->remember(function() use ($id) {
            return $this->repository->getAllPostCategories($id);
        });
    }

    public function checkCategories($postId, $newCategories)
    {
        return $this->repository->checkCategories($postId, $newCategories);
    }
}