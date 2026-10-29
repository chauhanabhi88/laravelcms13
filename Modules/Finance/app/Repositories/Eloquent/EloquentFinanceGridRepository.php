<?php

namespace Modules\Finance\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Finance\Repositories\FinanceGridRepository;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;

class EloquentFinanceGridRepository extends EloquentBaseRepository implements FinanceGridRepository
{
    use BuildsGridFilters;

    /**
     * Per grid: select filters (name => label key), eager loads, sortable columns, search.
     */
    private const GRIDS = [
        'commission_rule' => ['selects' => ['scope', 'status'], 'with' => ['category.translations', 'seller'], 'sortable' => ['id', 'scope', 'value', 'effective_from', 'effective_to', 'status']],
        'fee_rule' => ['selects' => ['code', 'charged_to', 'status'], 'with' => ['category.translations', 'seller'], 'sortable' => ['id', 'code', 'value', 'effective_from', 'status']],
        'ledger' => ['selects' => ['type', 'status', 'direction'], 'with' => ['seller', 'sellerOrder'], 'sortable' => ['id', 'type', 'amount', 'status', 'eligible_on', 'posted_at']],
        'payout_batch' => ['selects' => ['status'], 'with' => [], 'sortable' => ['id', 'batch_no', 'cycle_end', 'status', 'total_amount', 'created_at']],
        'payout_hold' => ['selects' => ['state'], 'with' => ['seller', 'placer'], 'sortable' => ['id', 'placed_at', 'released_at']],
        'tax_document' => ['selects' => ['type'], 'with' => ['seller'], 'sortable' => ['id', 'number', 'document_date', 'total']],
        'cod_remittance' => ['selects' => ['status'], 'with' => [], 'sortable' => ['id', 'remittance_date', 'amount', 'status']],
        'reseller_withdrawal' => ['selects' => ['status'], 'with' => ['customer'], 'sortable' => ['id', 'amount', 'status', 'created_at']],
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
            $filters[] = $this->selectFilter($name, trans('finance::finance.filters.'.$name), $filter($name), $options[$name] ?? []);
        }

        $filters[] = $this->textFilter('search', trans('finance::finance.filters.search_'.$this->gridKey), $filter('search'));
        $filters[] = $this->filterActions($sessionKey);

        return $filters;
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request)->with(self::GRIDS[$this->gridKey]['with']), $request, $this->sessionKey(), self::GRIDS[$this->gridKey]['sortable']);
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, $this->sessionKey());
        $query = $this->model->newQuery();

        foreach (self::GRIDS[$this->gridKey]['selects'] as $name) {
            $value = (string) $filter($name);

            if ($value === '') {
                continue;
            }

            if ($name === 'state') {
                $value === 'active' ? $query->whereNull('released_at') : $query->whereNotNull('released_at');
            } else {
                $query->where($name, $value);
            }
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $this->search($query, $search);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';
        $seller = fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('store_name', 'like', $like)->orWhere('legal_name', 'like', $like);

        match ($this->gridKey) {
            'ledger' => $query->where(fn (Builder $q) => $q->whereHas('seller', $seller)->orWhereHas('sellerOrder', fn (Builder $o) => $o->where('sub_order_no', 'like', $like))->orWhere('description', 'like', $like)),
            'payout_batch' => $query->where('batch_no', 'like', $like),
            'tax_document' => $query->where(fn (Builder $q) => $q->where('number', 'like', $like)->orWhereHas('seller', $seller)),
            'cod_remittance' => $query->where(fn (Builder $q) => $q->where('remittance_ref', 'like', $like)->orWhere('utr', 'like', $like)),
            'reseller_withdrawal' => $query->where('withdrawal_no', 'like', $like),
            default => $query->whereHas('seller', $seller),
        };
    }

    protected function sessionKey(): string
    {
        return (string) config('finance.cache.'.$this->gridKey);
    }
}
