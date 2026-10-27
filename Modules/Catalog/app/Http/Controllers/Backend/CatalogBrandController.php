<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Modules\Catalog\Http\Requests\CatalogBrandRequest;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Repositories\CatalogBrandRepository;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Core\Http\Controllers\BackendController;

/**
 * Brands grid and form. Seller brand requests (status 2 + requested_by_seller_id) are
 * listed with the "Pending Requests" filter and approved from the grid or the form.
 */
class CatalogBrandController extends BackendController
{
    public function __construct(
        private CatalogBrandRepository $brands,
        private CatalogSizeChartRepository $sizeCharts,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.brand'), $request->input('per_page'))]);

            return view('catalog::backend.catalog_brand.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.brand'), $request->input('per_page'))]);
            setFilterSession(config('catalog.cache.brand'), $request);

            $content = view('catalog::backend.catalog_brand.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.catalog_brand.index')));

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
            return view('catalog::backend.catalog_brand.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(CatalogBrandRequest $request): RedirectResponse
    {
        try {
            $data = $this->payload($request);
            if ($request->hasFile('logo')) {
                $data['logo'] = $this->storeLogo($request, new CatalogBrand);
            }

            $brand = $this->brands->create($data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_brand.edit', updateUrlParams([$brand->id]))->with('success', trans('catalog::catalog_brand.messages.created_success'));
            }

            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('success', trans('catalog::catalog_brand.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.create', updateUrlParams())->withInput($request->except('logo'))->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('catalog::backend.catalog_brand.edit', $this->formData() + ['catalogBrand' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(CatalogBrandRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $brand = $this->findOrFail($id);
            $data = $this->payload($request);

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->storeLogo($request, $brand);
            } elseif ($request->boolean('remove_logo') && $brand->logo) {
                $this->brands->setUploadParams($this->uploadParams())->setModel($brand)->removeFile();
                $this->brands->setModel(new CatalogBrand);
                $data['logo'] = null;
            }

            $this->brands->update($brand, $data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_brand.edit', updateUrlParams([$id]))->with('success', trans('catalog::catalog_brand.messages.updated_success'));
            }

            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('success', trans('catalog::catalog_brand.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.edit', updateUrlParams([$id]))->withInput($request->except('logo'))->with('error', $e->getMessage());
        }
    }

    /**
     * Approve a seller's brand request: the brand becomes selectable for listings.
     */
    public function approve(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $brand = $this->findOrFail($request->route('id'));
            $this->brands->update($brand, ['status' => config('core.enabled')]);

            if ($request->expectsJson()) {
                $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
                $refresh->merge(['message' => trans('catalog::catalog_brand.messages.approved_success')]);

                return $this->filters($refresh);
            }

            return redirect()->back()->with('success', trans('catalog::catalog_brand.messages.approved_success'));
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? response()->json(['type' => 'error', 'message' => $e->getMessage()])
                : redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($brand = $this->brands->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->brands->update($brand, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->brands->deleteRecord($request, $this->uploadParams());

            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('success', trans('catalog::catalog_brand.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $this->brands->setUploadParams($this->uploadParams())->destroyMultiple($request, true);

            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('success', trans('catalog::catalog_brand.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_brand.index', updateUrlParams())->with('error', $e->getMessage());
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
            'collection' => $this->brands->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->brands->getFilters($request, $this->brands->getStatusOptions(true)),
            'statusOptions' => $this->brands->getStatusOptions(),
            'yesNoOptions' => $this->brands->getYesNoOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $this->getAssetManager()->addAsset('modules/theme/backend/js/jquery.slug.js');

        return [
            'languageOptions' => getLanguageOptions() ?: [config('app.locale') => config('app.locale')],
            'sizeChartOptions' => $this->sizeCharts->getOptions(true),
            'statusOptions' => $this->brands->getStatusOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogBrandRequest $request): array
    {
        $validated = $request->validated();
        $data = Arr::only($validated, ['slug', 'size_chart_id', 'is_verified', 'status']);

        foreach (CatalogSettings::contentLocales() as $locale) {
            $data[$locale] = (array) ($validated[$locale] ?? []);
        }

        return $data;
    }

    /**
     * @return array{module_name: string, dbfield: string}
     */
    private function uploadParams(): array
    {
        return ['module_name' => (string) config('catalog.upload.brand'), 'dbfield' => 'logo'];
    }

    private function storeLogo(Request $request, CatalogBrand $brand): string
    {
        $stored = $this->brands->setUploadParams($this->uploadParams())->setModel($brand)->uploadImage($request);
        $this->brands->setModel(new CatalogBrand);

        return (string) $stored['logo'];
    }

    private function findOrFail(mixed $id): CatalogBrand
    {
        $brand = $id ? $this->brands->find((int) $id) : null;

        if (! $brand instanceof CatalogBrand) {
            throw new \RuntimeException(trans('catalog::catalog_brand.messages.data_invalid'));
        }

        return $brand;
    }
}
