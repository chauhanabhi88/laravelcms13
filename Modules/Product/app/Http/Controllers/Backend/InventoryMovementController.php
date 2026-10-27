<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Product\Repositories\InventoryMovementRepository;
use Modules\User\Models\User;

/**
 * Read-only audit grid of the inventory ledger (FR-PRD-008).
 */
class InventoryMovementController extends BackendController
{
    public function __construct(private readonly InventoryMovementRepository $movements)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.inventory_movement'), $request->input('per_page'))]);

            return view('product::backend.inventory_movement.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('product.cache.inventory_movement'), $request->input('per_page'))]);
            setFilterSession(config('product.cache.inventory_movement'), $request);

            $content = view('product::backend.inventory_movement.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.inventory_movement.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $collection = $this->movements->pagination($request);
        $adminIds = $collection->getCollection()->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->all();

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $collection,
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->movements->getFilters($request),
            'adminNames' => $adminIds === [] ? [] : User::query()->whereIn('id', $adminIds)->pluck('name', 'id')->all(),
        ];
    }
}
