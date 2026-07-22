<?php

namespace Modules\Blog\Repositories\Eloquent;

use Modules\Blog\Repositories\BlogPostCategoryRelationRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentBlogPostCategoryRelationRepository extends EloquentBaseRepository implements BlogPostCategoryRelationRepository
{
    public function getAllPostCategories($id) {
        $collection = $this->allWithBuilder();
        $data = $collection->where('post_id', $id)->pluck('category_id')->toArray();
        return $data;
    }

    public function checkCategories($postId, $newCategories)
    {
        $collection = $this->allWithBuilder();
        $oldCategories = $collection->where('post_id', $postId)->pluck('category_id')->toArray();
        sort($newCategories);
        sort($oldCategories);
        $result = [];
        if ($newCategories === $oldCategories) {
            return $result;
            exit();
        }
        if ($new = array_diff($newCategories, $oldCategories)) {
            foreach ($new as $value) {
                $result[] = [
                    'post_id' => $postId,
                    'category_id' => $value
                ];
            }
        }
        if ($old = array_diff($oldCategories, $newCategories)) {
            $collection->where('post_id', $postId)->whereIn('category_id', $old)->delete();
        }
        return $result;
    }
}