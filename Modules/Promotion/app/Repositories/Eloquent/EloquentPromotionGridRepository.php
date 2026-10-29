<?php

namespace Modules\Promotion\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Promotion\Repositories\PromotionGridRepository;

class EloquentPromotionGridRepository extends EloquentBaseRepository implements PromotionGridRepository
{
    use BuildsGridFilters;

    /**
     * Per grid: select filters, eager loads / counts, sortable columns.
     */
    private const GRIDS = [
        'coupon' => ['selects' => ['type', 'kind', 'funded_by', 'status'], 'with' => ['seller'], 'counts' => [], 'sortable' => ['id', 'code', 'type', 'value', 'used_count', 'starts_at', 'ends_at', 'status', 'created_at']],
        'flash_deal' => ['selects' => ['state', 'status'], 'with' => [], 'counts' => ['items'], 'sortable' => ['id', 'name', 'starts_at', 'ends_at', 'status', 'created_at']],
    ];

    public function __construct(Model $model, private readonly string $gridKey)
    {
        parent::__construct($model);
    }

    public function grid(): string
    {
        return $this->gridKey;
    }

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = $this->sessionKey();
        $filter = $this->filterReader($request, $sessionKey);
        $filters = [];

        foreach (self::GRIDS[$this->gridKey]['selects'] as $name) {
            $filters[] = $this->selectFilter($name, trans('promotion::promotion.filters.'.$name), $filter($name), $options[$name] ?? []);
        }

        $filters[] = $this->textFilter('search', trans('promotion::promotion.filters.search_'.$this->gridKey), $filter('search'));
        $filters[] = $this->filterActions($sessionKey);

        return $filters;
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        $config = self::GRIDS[$this->gridKey];

        $query = $this->filter($request)->with($config['with'])->when($config['counts'] !== [], fn (Builder $query) => $query->withCount($config['counts']));

        return $this->paginateGrid($query, $request, $this->sessionKey(), $config['sortable']);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, $this->sessionKey());
        $query = $this->model->newQuery();
        $now = now();

        foreach (self::GRIDS[$this->gridKey]['selects'] as $name) {
            $value = (string) $filter($name);

            if ($value === '') {
                continue;
            }

            match ($name) {
                'kind' => match ($value) {
                    'auto' => $query->where('is_auto', config('core.yes')),
                    'seller' => $query->whereNotNull('seller_id'),
                    'personal' => $query->whereNotNull('customer_id'),
                    default => $query->where('is_auto', config('core.no'))->whereNull('seller_id')->whereNull('customer_id'),
                },
                'state' => match ($value) {
                    'running' => $query->where('status', config('core.enabled'))->where('starts_at', '<=', $now)->where('ends_at', '>', $now),
                    'scheduled' => $query->where('starts_at', '>', $now),
                    default => $query->where('ends_at', '<=', $now),
                },
                default => $query->where($name, $value),
            };
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $like = '%'.$search.'%';

            $this->gridKey === 'coupon'
                ? $query->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('batch', 'like', $like))
                : $query->where('name', 'like', $like);
        }

        return $query;
    }

    protected function sessionKey(): string
    {
        return (string) config('promotion.cache.'.$this->gridKey);
    }
}
