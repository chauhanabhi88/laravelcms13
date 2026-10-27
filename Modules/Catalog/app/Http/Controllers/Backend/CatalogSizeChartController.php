<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Http\Requests\CatalogSizeChartRequest;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Core\Http\Controllers\BackendController;

/**
 * Size charts: a measurement grid (sizes x measurements) edited in the browser.
 */
class CatalogSizeChartController extends BackendController
{
    public function __construct(private CatalogSizeChartRepository $sizeCharts)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.size_chart'), $request->input('per_page'))]);

            return view('catalog::backend.catalog_size_chart.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.size_chart'), $request->input('per_page'))]);
            setFilterSession(config('catalog.cache.size_chart'), $request);

            $content = view('catalog::backend.catalog_size_chart.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.catalog_size_chart.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            return view('catalog::backend.catalog_size_chart.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(CatalogSizeChartRequest $request): RedirectResponse
    {
        try {
            $data = $this->payload($request);
            if ($request->hasFile('image')) {
                $data['image'] = $this->storeImage($request, new CatalogSizeChart);
            }

            $sizeChart = $this->sizeCharts->create($data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_size_chart.edit', updateUrlParams([$sizeChart->id]))->with('success', trans('catalog::catalog_size_chart.messages.created_success'));
            }

            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('success', trans('catalog::catalog_size_chart.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.create', updateUrlParams())->withInput($request->except('image'))->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('catalog::backend.catalog_size_chart.edit', $this->formData() + ['catalogSizeChart' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(CatalogSizeChartRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $sizeChart = $this->findOrFail($id);
            $data = $this->payload($request);

            if ($request->hasFile('image')) {
                $data['image'] = $this->storeImage($request, $sizeChart);
            } elseif ($request->boolean('remove_image') && $sizeChart->image) {
                $this->sizeCharts->setUploadParams($this->uploadParams())->setModel($sizeChart)->removeFile();
                $this->sizeCharts->setModel(new CatalogSizeChart);
                $data['image'] = null;
            }

            $this->sizeCharts->update($sizeChart, $data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_size_chart.edit', updateUrlParams([$id]))->with('success', trans('catalog::catalog_size_chart.messages.updated_success'));
            }

            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('success', trans('catalog::catalog_size_chart.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.edit', updateUrlParams([$id]))->withInput($request->except('image'))->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($sizeChart = $this->sizeCharts->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->sizeCharts->update($sizeChart, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    /**
     * Categories and brands linked to a deleted chart fall back to "inherit" (FK set null),
     * so the derived category defaults are flushed too.
     */
    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->sizeCharts->deleteRecord($request, $this->uploadParams());
            event(new CatalogTreeChanged(null, 'size_chart_deleted'));

            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('success', trans('catalog::catalog_size_chart.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $this->sizeCharts->setUploadParams($this->uploadParams())->destroyMultiple($request, true);
            event(new CatalogTreeChanged(null, 'size_chart_deleted'));

            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('success', trans('catalog::catalog_size_chart.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_size_chart.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->sizeCharts->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->sizeCharts->getFilters($request, $this->sizeCharts->getStatusOptions(true)),
            'statusOptions' => $this->sizeCharts->getStatusOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $this->getAssetManager()->addAsset('modules/catalog/css/catalog.css');

        $unitOptions = [];
        foreach ((array) config('catalog.size_chart_units') as $unit) {
            $unitOptions[$unit] = trans('catalog::catalog_size_chart.units.'.$unit);
        }

        return [
            'unitOptions' => $unitOptions,
            'statusOptions' => $this->sizeCharts->getStatusOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogSizeChartRequest $request): array
    {
        return $request->grid() + [
            'name' => $request->validated('name'),
            'unit' => $request->validated('unit'),
            'status' => $request->validated('status'),
        ];
    }

    /**
     * @return array{module_name: string, dbfield: string}
     */
    private function uploadParams(): array
    {
        return ['module_name' => (string) config('catalog.upload.size_chart'), 'dbfield' => 'image'];
    }

    private function storeImage(Request $request, CatalogSizeChart $sizeChart): string
    {
        $stored = $this->sizeCharts->setUploadParams($this->uploadParams())->setModel($sizeChart)->uploadImage($request);
        $this->sizeCharts->setModel(new CatalogSizeChart);

        return (string) $stored['image'];
    }

    private function findOrFail(mixed $id): CatalogSizeChart
    {
        $sizeChart = $id ? $this->sizeCharts->find((int) $id) : null;

        if (! $sizeChart instanceof CatalogSizeChart) {
            throw new \RuntimeException(trans('catalog::catalog_size_chart.messages.data_invalid'));
        }

        return $sizeChart;
    }
}
