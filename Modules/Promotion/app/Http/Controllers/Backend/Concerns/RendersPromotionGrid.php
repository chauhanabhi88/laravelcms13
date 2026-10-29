<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Backend\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Promotion\Repositories\PromotionGridRepository;
use Modules\Promotion\Support\PromotionOptions;

/**
 * The admin grid pattern (index + AJAX filters → partials/grid) for the promotion screens. The
 * using controller defines GRID (promotion.cache key, route and blade folder).
 */
trait RendersPromotionGrid
{
    protected function gridRepository(): PromotionGridRepository
    {
        return app(PromotionGridRepository::class, ['grid' => static::GRID]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('promotion.cache.'.static::GRID), $request->input('per_page'))]);

            return view('promotion::backend.'.static::GRID.'.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('promotion.cache.'.static::GRID), $request->input('per_page'))]);
            setFilterSession(config('promotion.cache.'.static::GRID), $request);

            $content = view('promotion::backend.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.'.static::GRID.'.index')));

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
    protected function gridData(Request $request, mixed $menuId): array
    {
        $repository = $this->gridRepository();

        return [
            'grid' => static::GRID,
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $repository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $repository->getFilters($request, PromotionOptions::filterOptions(static::GRID)),
        ];
    }
}
