<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Http\Requests\CatalogCategoryRequest;
use Modules\Catalog\Http\Requests\MoveCatalogCategoryRequest;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Core\Http\Controllers\BackendController;

/**
 * Category tree admin: a drag-and-drop tree beside the details of the selected node, the
 * standard flat grid with filters, and the create / edit form with commerce defaults.
 * Every write goes through CategoryTreeService.
 */
class CatalogCategoryController extends BackendController
{
    private const IMAGE_FIELDS = ['image', 'banner_image'];

    public function __construct(
        private CatalogCategoryRepository $categories,
        private CategoryTreeService $tree,
        private CategoryDefaults $defaults,
        private CatalogSizeChartRepository $sizeCharts,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.category'), $request->input('per_page'))]);
            $this->getAssetManager()->addAsset('modules/catalog/css/catalog.css');
            $this->getAssetManager()->addAsset('modules/catalog/js/tree.js');

            return view('catalog::backend.catalog_category.index', $this->gridData($request, getActiveMenuId($request)) + [
                'tree' => $this->tree->tree(null, enabledOnly: false),
                'selectedId' => (int) $request->input('selected'),
                'maxDepth' => CatalogSettings::maxCategoryDepth(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('catalog.cache.category'), $request->input('per_page'))]);
            setFilterSession(config('catalog.cache.category'), $request);

            $content = view('catalog::backend.catalog_category.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.catalog_category.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function create(Request $request): View|RedirectResponse
    {
        try {
            $parentId = $request->integer('parent_id') ?: null;
            $parent = $parentId ? $this->categories->find($parentId) : null;

            return view('catalog::backend.catalog_category.create', $this->formData(null, $parent?->id) + ['parent' => $parent]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(CatalogCategoryRequest $request): RedirectResponse
    {
        try {
            $data = $this->payload($request);
            foreach (self::IMAGE_FIELDS as $field) {
                if ($request->hasFile($field)) {
                    $data[$field] = $this->storeImage($request, $field, new CatalogCategory);
                }
            }

            $category = $this->tree->create($data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_category.edit', updateUrlParams([$category->id]))->with('success', trans('catalog::catalog_category.messages.created_success'));
            }

            return redirect()->route('admin.catalog_category.index', updateUrlParams() + ['selected' => $category->id])->with('success', trans('catalog::catalog_category.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.create', updateUrlParams() + array_filter(['parent_id' => $request->input('parent_id')]))
                ->withInput($request->except(self::IMAGE_FIELDS))
                ->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            $category = $this->findOrFail($request->route('id'));

            return view('catalog::backend.catalog_category.edit', $this->formData($category, $category->parent_id) + [
                'catalogCategory' => $category,
                'breadcrumb' => $this->tree->breadcrumb($category->id),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(CatalogCategoryRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $category = $this->findOrFail($id);
            $data = $this->payload($request);

            foreach (self::IMAGE_FIELDS as $field) {
                if ($request->hasFile($field)) {
                    $data[$field] = $this->storeImage($request, $field, $category);
                } elseif ($request->boolean('remove_'.$field) && $category->{$field}) {
                    $this->removeImage($field, $category);
                    $data[$field] = null;
                }
            }

            $this->tree->update($category, $data);

            if ($request->input('snc')) {
                return redirect()->route('admin.catalog_category.edit', updateUrlParams([$id]))->with('success', trans('catalog::catalog_category.messages.updated_success'));
            }

            return redirect()->route('admin.catalog_category.index', updateUrlParams() + ['selected' => $id])->with('success', trans('catalog::catalog_category.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.edit', updateUrlParams([$id]))
                ->withInput($request->except(self::IMAGE_FIELDS))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Drag and drop from the tree. JSON for the tree script.
     */
    public function move(MoveCatalogCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->findOrFail($request->integer('id'));
            $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;
            $position = $request->filled('position') ? $request->integer('position') : null;

            $this->tree->move($category, $parentId, $position);

            return response()->json(['type' => 'success', 'message' => trans('catalog::catalog_category.messages.moved_success')]);
        } catch (CatalogException $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $category = $request->input('id') ? $this->categories->find((int) $request->input('id')) : null;

        if ($category instanceof CatalogCategory) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->tree->setStatus($category, (int) $status);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->tree->delete($this->findOrFail($request->route('id')));

            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('success', trans('catalog::catalog_category.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Grid mass delete. Categories with subcategories or products are skipped and reported.
     */
    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $result = $this->tree->deleteMany($this->selectedIds($request));
            $redirect = redirect()->route('admin.catalog_category.index', updateUrlParams())
                ->with('success', trans('catalog::catalog_category.messages.mass_deleted', ['count' => count($result['deleted'])]));

            if ($result['skipped'] !== []) {
                $redirect->with('error', trans('catalog::catalog_category.messages.mass_skipped', [
                    'count' => count($result['skipped']),
                    'reasons' => implode(' ', array_unique($result['skipped'])),
                ]));
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $statusOptions = $this->categories->getStatusOptions(true);

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->categories->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->categories->getFilters($request, $statusOptions),
            'statusOptions' => $this->categories->getStatusOptions(),
            'yesNoOptions' => $this->categories->getYesNoOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?CatalogCategory $category, ?int $parentId): array
    {
        $this->getAssetManager()->addAsset('modules/theme/backend/js/jquery.slug.js');

        $inherited = $this->defaults->inheritedFrom($parentId);
        $sizeChartOptions = $this->sizeCharts->getOptions();

        $returnTypeOptions = ['' => trans('catalog::catalog_category.labels.inherited', ['value' => trans('catalog::catalog.return_types.'.$inherited['return_type'])])];
        foreach ((array) config('catalog.return_types') as $type) {
            $returnTypeOptions[$type] = trans('catalog::catalog.return_types.'.$type);
        }

        $inheritedChart = $inherited['size_chart_id'] !== null ? ($sizeChartOptions[$inherited['size_chart_id']] ?? null) : null;

        return [
            'languageOptions' => getLanguageOptions() ?: [config('app.locale') => config('app.locale')],
            'parentOptions' => $this->tree->options(true, $category?->id, parentsOnly: true),
            'sizeChartOptions' => ['' => trans('catalog::catalog_category.labels.inherited', ['value' => $inheritedChart ?? trans('catalog::catalog_category.labels.not_set')])] + $sizeChartOptions,
            'returnTypeOptions' => $returnTypeOptions,
            'inherited' => $inherited,
            'statusOptions' => $this->categories->getStatusOptions(),
        ];
    }

    /**
     * Validated fields plus one translation array per content locale.
     *
     * @return array<string, mixed>
     */
    private function payload(CatalogCategoryRequest $request): array
    {
        $validated = $request->validated();
        $data = Arr::only($validated, [
            'slug', 'parent_id', 'icon', 'gst_percent', 'hsn_code', 'return_window_days', 'return_type',
            'dispatch_sla_hours', 'size_chart_id', 'min_selling_price', 'sort_order', 'show_in_menu', 'status',
        ]);

        foreach (CatalogSettings::contentLocales() as $locale) {
            $data[$locale] = (array) ($validated[$locale] ?? []);
        }

        return $data;
    }

    private function storeImage(Request $request, string $field, CatalogCategory $category): string
    {
        $stored = $this->categories
            ->setUploadParams(['module_name' => config('catalog.upload.category'), 'dbfield' => $field])
            ->setModel($category)
            ->uploadImage($request);

        $this->categories->setModel(new CatalogCategory);

        return (string) $stored[$field];
    }

    private function removeImage(string $field, CatalogCategory $category): void
    {
        $this->categories
            ->setUploadParams(['module_name' => config('catalog.upload.category'), 'dbfield' => $field])
            ->setModel($category)
            ->removeFile();

        $this->categories->setModel(new CatalogCategory);
    }

    /**
     * Ids picked in the grid (the delete modal posts idsToDelete / selectAll / idsNotToDelete).
     *
     * @return list<int>
     */
    private function selectedIds(Request $request): array
    {
        $excluded = array_filter(array_map('intval', explode(',', (string) $request->input('idsNotToDelete'))));

        if ($request->input('selectAll')) {
            $query = $request->input('isFiltered') ? $this->categories->filter($request) : CatalogCategory::query();

            return array_values(array_diff($query->pluck('id')->map(fn ($id): int => (int) $id)->all(), $excluded));
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $request->input('idsToDelete')))));
    }

    private function findOrFail(mixed $id): CatalogCategory
    {
        $category = $id ? $this->categories->find((int) $id) : null;

        if (! $category instanceof CatalogCategory) {
            throw new \RuntimeException(trans('catalog::catalog_category.messages.data_invalid'));
        }

        return $category;
    }
}
