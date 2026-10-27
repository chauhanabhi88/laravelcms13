<?php

namespace Modules\Seller\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\Seller;
use Modules\Seller\Repositories\SellerRepository;

class EloquentSellerRepository extends EloquentBaseRepository implements SellerRepository
{
    private const SORTABLE = ['id', 'code', 'store_name', 'legal_name', 'gst_status', 'status', 'submitted_at', 'verified_at', 'created_at'];

    public function getFilters(Request $request, string $grid): array
    {
        $sessionKey = $this->sessionKey($grid);
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $statuses = $grid === self::GRID_APPLICATIONS
            ? array_intersect_key(SellerStatus::options(), array_flip((array) config('seller.application_statuses')))
            : SellerStatus::options();

        return [
            [
                'type' => 'text',
                'name' => 'search',
                'row' => '1',
                'value' => $filter('search'),
                'options' => ['placeholder' => trans('seller::seller.labels.search'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => ['' => trans('core::core.labels.select')] + $statuses,
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'gst_status',
                'row' => '1',
                'value' => $filter('gst_status'),
                'select_options' => $this->getGstStatusOptions(true),
                'options' => ['label' => trans('seller::seller.labels.gst_status'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'action',
                'class' => 'col-action',
                'row' => '2',
                'buttons' => [
                    'submit' => [
                        'name' => 'search',
                        'type' => 'submit',
                        'onclick' => 'searchFilter(); return false;',
                        'class' => 'btn btn-primary btn-fw',
                        'title' => trans('core::core.buttons.search'),
                    ],
                    'reset' => [
                        'name' => 'reset',
                        'type' => 'button',
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([$sessionKey]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];
    }

    public function pagination(Request $request, string $grid): LengthAwarePaginator
    {
        $sessionKey = $this->sessionKey($grid);
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $applications = $grid === self::GRID_APPLICATIONS;

        // The KYC queue is worked oldest first.
        $defaultOrder = $applications ? 'submitted_at' : 'id';
        $orderBy = in_array($request->input('order_by'), self::SORTABLE, true) ? $request->input('order_by') : $defaultOrder;
        $dir = strtoupper((string) $request->input('dir', $applications ? 'ASC' : 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request, $grid)->orderBy($orderBy, $dir)->orderBy('id', $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request, string $grid): Builder
    {
        $sessionKey = $this->sessionKey($grid);
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery()->with('primaryBankAccount');

        if ($grid === self::GRID_APPLICATIONS) {
            $collection->applications();
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(function (Builder $query) use ($search): void {
                foreach (['code', 'store_name', 'legal_name', 'gstin', 'phone', 'email'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        if (($status = (string) $filter('status')) !== '' && SellerStatus::tryFrom($status) !== null) {
            $collection->where('status', $status);
        }

        if (($gstStatus = (string) $filter('gst_status')) !== '') {
            $collection->where('gst_status', $gstStatus);
        }

        return $collection;
    }

    public function findWithDetails(int $id): ?Seller
    {
        return $this->model->newQuery()
            ->with([
                'users',
                'owner',
                'bankAccounts' => fn ($query) => $query->with('chequeDocument')->orderByDesc('is_primary')->orderByDesc('id'),
                'warehouses' => fn ($query) => $query->orderBy('is_default')->orderBy('id'),
                'kycDocuments' => fn ($query) => $query->latest('id'),
                'statusHistories',
                'state.translations',
                'verifier',
            ])
            ->find($id);
    }

    public function findPublicBySlug(string $slug): ?Seller
    {
        return $this->model->newQuery()
            ->where('store_slug', $slug)
            ->whereIn('status', [SellerStatus::Active->value, SellerStatus::Suspended->value])
            ->first();
    }

    public function getGstStatusOptions(bool $withPlaceholder = false): array
    {
        $options = [];

        foreach ([Seller::GST_UNVERIFIED, Seller::GST_VERIFIED, Seller::GST_MISMATCH, Seller::GST_NOT_APPLICABLE] as $status) {
            $options[$status] = trans('seller::seller.gst_statuses.'.$status);
        }

        return $withPlaceholder ? ['' => trans('core::core.labels.select')] + $options : $options;
    }

    private function sessionKey(string $grid): string
    {
        return (string) config($grid === self::GRID_APPLICATIONS ? 'seller.cache.seller_application' : 'seller.cache.seller');
    }
}
