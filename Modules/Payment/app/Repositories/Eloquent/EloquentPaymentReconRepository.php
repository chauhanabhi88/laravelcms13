<?php

namespace Modules\Payment\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Repositories\Eloquent\Concerns\BuildsGridFilters;
use Modules\Payment\Repositories\PaymentReconRepository;
use Modules\Payment\Support\PaymentOptions;

class EloquentPaymentReconRepository extends EloquentBaseRepository implements PaymentReconRepository
{
    use BuildsGridFilters;

    private const SORTABLE = ['id', 'settlement_date', 'entity_type', 'issue', 'gateway_amount', 'created_at'];

    public function getFilters(Request $request, array $options): array
    {
        $sessionKey = config('payment.cache.payment_recon');
        $filter = $this->filterReader($request, $sessionKey);

        return [
            $this->selectFilter('issue', trans('payment::payment_recon.labels.issue'), $filter('issue'), $options['issues']),
            $this->selectFilter('state', trans('payment::payment_recon.labels.state'), $filter('state'), $options['states']),
            $this->textFilter('search', trans('payment::payment_recon.labels.search'), $filter('search')),
            $this->filterActions($sessionKey),
        ];
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->paginateGrid($this->filter($request), $request, config('payment.cache.payment_recon'), self::SORTABLE, 'settlement_date');
    }

    public function filter(Request $request): Builder
    {
        $filter = $this->filterReader($request, config('payment.cache.payment_recon'));
        $collection = $this->model->newQuery();

        if (($issue = (string) $filter('issue')) !== '') {
            $collection->where('issue', $issue);
        }

        match ((string) $filter('state')) {
            PaymentOptions::RECON_OPEN => $collection->whereNull('resolved_at'),
            PaymentOptions::RECON_RESOLVED => $collection->whereNotNull('resolved_at'),
            default => null,
        };

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(fn (Builder $query) => $query->where('entity_id', $search)->orWhere('settlement_id', $search));
        }

        return $collection;
    }

    public function resolve(PaymentReconItem $item, int $adminId, ?string $note): PaymentReconItem
    {
        $item->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $adminId,
            'note' => $note === null || trim($note) === '' ? $item->note : mb_substr(trim($note), 0, 255),
        ])->save();

        return $item;
    }
}
