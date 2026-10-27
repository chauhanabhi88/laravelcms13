<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Support\DuplicateDetector;
use Modules\Product\Support\ProductPresenter;
use Modules\User\Models\User;

/**
 * Shared grid / detail plumbing of the product and QC admin screens.
 */
abstract class ProductAdminController extends BackendController
{
    public function __construct(
        protected ProductRepository $products,
        protected ProductPresenter $presenter,
        protected DuplicateDetector $duplicates,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function gridData(Request $request, string $grid, mixed $menuId): array
    {
        $collection = $this->products->pagination($request, $grid);

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $collection,
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->products->getFilters($request, $grid),
            'duplicates' => $grid === ProductRepository::GRID_QC ? $this->duplicates->forProducts($collection->getCollection()) : [],
        ];
    }

    /**
     * Everything the detail / review pages show: labels, specs, the pending diff, possible
     * duplicates and status history with admin names.
     *
     * @return array<string, mixed>
     */
    protected function detailData(Product $product): array
    {
        $labels = $this->presenter->labels($product);
        $adminIds = $product->statusHistories->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->all();
        $duplicateIds = $this->duplicates->forProducts(collect([$product]))[$product->id] ?? [];

        return [
            'product' => $product,
            'labels' => $labels,
            'presenter' => $this->presenter,
            'specRows' => $this->presenter->currentSpecRows($product, $labels),
            'pendingDiff' => $this->presenter->pendingDiff($product, $labels),
            'categoryLabel' => $this->presenter->categoryLabel($product->catalog_category_id),
            'duplicateProducts' => $duplicateIds === [] ? collect() : Product::query()->with(['translations', 'seller:id,code,store_name,legal_name'])->whereIn('id', $duplicateIds)->get(),
            'adminNames' => $adminIds === [] ? [] : User::query()->whereIn('id', $adminIds)->pluck('name', 'id')->all(),
            'reasonTemplates' => collect((array) config('product.qc_reason_templates'))
                ->mapWithKeys(fn (string $code): array => [$code => trans('product::product.reason_templates.'.$code)])
                ->all(),
        ];
    }

    protected function findOrFail(mixed $id): Product
    {
        $product = $id ? $this->products->findWithDetails((int) $id) : null;

        if (! $product instanceof Product) {
            throw new \RuntimeException(trans('product::product.messages.not_found'));
        }

        return $product;
    }

    protected function admin(): User
    {
        $admin = Auth::guard('web')->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
