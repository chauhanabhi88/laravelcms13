<?php

namespace Modules\Payment\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Payment\Repositories\WebhookEventRepository;

class EloquentWebhookEventRepository extends EloquentBaseRepository implements WebhookEventRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'provider', 'event_type', 'signature_valid', 'status', 'attempts', 'created_at', 'processed_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('payment.cache.webhook_event');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('provider', trans('payment::webhook_event.labels.provider'), $filter('provider'), $options['providers']),
            $this->selectFilter('status', trans('payment::webhook_event.labels.status'), $filter('status'), $options['statuses']),
            $this->textFilter('search', trans('payment::webhook_event.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        // The payload can be large: the grid never needs it.
        $columns = ['id', 'provider', 'event_id', 'event_type', 'signature_valid', 'status', 'attempts', 'error', 'processed_at', 'created_at'];

        return $this->paginateGrid($this->filter($request)->select($columns), $request, config('payment.cache.webhook_event'), self::SORTABLE);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('payment.cache.webhook_event'));
        $collection = $this->model->newQuery();

        foreach (['provider', 'status'] as $column) {
            if (($value = (string) $filter($column)) !== '') {
                $collection->where($column, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query
                ->where('event_id', $search)
                ->orWhere('event_type', 'like', '%'.$search.'%'));
        }

        return $collection;
    }
}
