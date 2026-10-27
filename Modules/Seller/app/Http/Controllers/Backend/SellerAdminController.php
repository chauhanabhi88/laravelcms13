<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Seller\Services\SellerOnboardingService;
use Modules\User\Models\User;

/**
 * Shared grid / detail plumbing of the two seller admin screens.
 */
abstract class SellerAdminController extends BackendController
{
    public function __construct(protected SellerRepository $sellers)
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function gridData(Request $request, string $grid, mixed $menuId): array
    {
        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->sellers->pagination($request, $grid),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->sellers->getFilters($request, $grid),
            'gstStatusOptions' => $this->sellers->getGstStatusOptions(),
        ];
    }

    /**
     * Everything the detail page shows: KYC fields, documents, bank (with the penny-drop
     * name next to the legal name), warehouses, logins and status history with admin names.
     *
     * @return array<string, mixed>
     */
    protected function detailData(Seller $seller, string $context): array
    {
        $adminIds = $seller->statusHistories->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->all();

        return [
            'seller' => $seller,
            'context' => $context,
            'primaryBank' => $seller->bankAccounts->first(fn (SellerBankAccount $account): bool => $account->is_primary === (int) config('core.yes')),
            'completed' => app(SellerOnboardingService::class)->completedSteps($seller),
            'adminNames' => $adminIds === [] ? [] : User::query()->whereIn('id', $adminIds)->pluck('name', 'id')->all(),
            'reasonTemplates' => collect((array) config('seller.reason_templates'))
                ->map(fn (array $keys): array => array_map(fn (string $key): string => trans('seller::seller.reason_templates.'.$key), $keys))
                ->all(),
        ];
    }

    protected function findOrFail(mixed $id): Seller
    {
        $seller = $id ? $this->sellers->findWithDetails((int) $id) : null;

        if (! $seller instanceof Seller) {
            throw new \RuntimeException(trans('seller::seller.messages.not_found'));
        }

        return $seller;
    }

    protected function admin(): User
    {
        $admin = Auth::guard('web')->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
