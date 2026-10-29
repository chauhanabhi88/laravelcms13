<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Finance\Repositories\FinanceGridRepository;
use Modules\Finance\Support\FinanceOptions;

/**
 * The admin grid pattern (index + AJAX filters → partials/grid) for the finance console.
 * The using controller defines GRID (finance.cache key) and VIEW (blade folder).
 */
trait RendersFinanceGrid
{
    protected function gridRepository(): FinanceGridRepository
    {
        return app(FinanceGridRepository::class, ['grid' => static::GRID]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function renderIndex(Request $request, array $extra = []): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('finance.cache.'.static::GRID), $request->input('per_page'))]);

            return view('finance::backend.'.static::VIEW.'.index', $this->gridData($request, getActiveMenuId($request)) + $extra);
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('finance.cache.'.static::GRID), $request->input('per_page'))]);
            setFilterSession(config('finance.cache.'.static::GRID), $request);

            $content = view('finance::backend.'.static::VIEW.'.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.'.static::VIEW.'.index')));

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
        $options = FinanceOptions::filterOptions(static::GRID);

        return [
            'grid' => static::GRID,
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $repository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $repository->getFilters($request, $options),
        ];
    }
}
