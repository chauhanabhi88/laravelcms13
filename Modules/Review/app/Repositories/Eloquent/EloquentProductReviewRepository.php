<?php

namespace Modules\Review\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Review\Repositories\ProductReviewRepository;
use Modules\Review\Support\ReviewSettings;

/**
 * Admin moderation grid (plan M16 §5). The "queue" filter (default) shows reviews waiting for
 * a moderator plus published ones reported report_threshold times or more.
 */
class EloquentProductReviewRepository extends EloquentBaseRepository implements ProductReviewRepository
{
    use BuildsGridFilters;

    public const QUEUE_MODERATION = 'moderation';

    public const QUEUE_ALL = 'all';

    private const SORTABLE = ['id', 'rating', 'status', 'report_count', 'helpful_count', 'created_at', 'published_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('review.cache.product_review');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('queue', trans('review::product_review.labels.queue'), $filter('queue') ?: self::QUEUE_MODERATION, $options['queues']),
            $this->selectFilter('status', trans('review::product_review.labels.status'), $filter('status'), $options['statuses']),
            $this->selectFilter('rating', trans('review::product_review.labels.rating'), $filter('rating'), $options['ratings']),
            $this->selectFilter('flag', trans('review::product_review.labels.flag'), $filter('flag'), $options['flags']),
            $this->textFilter('search', trans('review::product_review.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with(['orderItem', 'customer', 'seller']), $request, config('review.cache.product_review'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('review.cache.product_review'));
        $collection = $this->model->newQuery();

        if (((string) $filter('queue') ?: self::QUEUE_MODERATION) === self::QUEUE_MODERATION) {
            $collection->needsModeration(ReviewSettings::reportThreshold());
        }

        if (($status = (string) $filter('status')) !== '') {
            $collection->where('status', $status);
        }

        if (($rating = (int) $filter('rating')) >= 1 && $rating <= 5) {
            $collection->where('rating', $rating);
        }

        if (($flag = (string) $filter('flag')) !== '') {
            $collection->whereJsonContains('moderation_flags', $flag);
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('body', 'like', '%'.$search.'%')
                ->orWhere('author_name', 'like', '%'.$search.'%')
                ->orWhereHas('orderItem', fn (Builder $item) => $item->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')));
        }

        return $collection;
    }
}
