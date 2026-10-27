<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Seller\Models\Seller;
use Modules\Storefront\Http\Requests\ProductCollectionRequest;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Repositories\ProductCollectionRepository;

/**
 * Product collections: rule based (category / price / discount / rating / brand / seller
 * filters) or a manual product list, shown at /collection/{slug} and in home sections.
 */
class ProductCollectionController extends BackendController
{
    public function __construct(
        private ProductCollectionRepository $collections,
        private CategoryTreeService $tree,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('storefront.cache.product_collection'), $request->input('per_page'))]);

            return view('storefront::backend.product_collection.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('storefront.cache.product_collection'), $request->input('per_page'))]);
            setFilterSession(config('storefront.cache.product_collection'), $request);

            $content = view('storefront::backend.product_collection.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.product_collection.index')));

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
            return view('storefront::backend.product_collection.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(ProductCollectionRequest $request): RedirectResponse
    {
        try {
            $data = $this->payload($request);
            if ($request->hasFile('banner_image')) {
                $data['banner_image'] = $this->storeBanner($request, new ProductCollection);
            }

            $collection = $this->collections->create($data);

            if ($request->input('snc')) {
                return redirect()->route('admin.product_collection.edit', updateUrlParams([$collection->id]))->with('success', trans('storefront::product_collection.messages.created_success'));
            }

            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('success', trans('storefront::product_collection.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.create', updateUrlParams())->withInput($request->except('banner_image'))->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('storefront::backend.product_collection.edit', $this->formData() + ['productCollection' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(ProductCollectionRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $collection = $this->findOrFail($id);
            $data = $this->payload($request);

            if ($request->hasFile('banner_image')) {
                $data['banner_image'] = $this->storeBanner($request, $collection);
            } elseif ($request->boolean('remove_banner_image') && $collection->banner_image) {
                $this->collections->setUploadParams($this->uploadParams())->setModel($collection)->removeFile();
                $this->collections->setModel(new ProductCollection);
                $data['banner_image'] = null;
            }

            $this->collections->update($collection, $data);

            if ($request->input('snc')) {
                return redirect()->route('admin.product_collection.edit', updateUrlParams([$id]))->with('success', trans('storefront::product_collection.messages.updated_success'));
            }

            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('success', trans('storefront::product_collection.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.edit', updateUrlParams([$id]))->withInput($request->except('banner_image'))->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($collection = $this->collections->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->collections->update($collection, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->collections->deleteRecord($request, $this->uploadParams());

            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('success', trans('storefront::product_collection.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $this->collections->setUploadParams($this->uploadParams())->destroyMultiple($request, true);
            HomeSection::flushHomeCache();

            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('success', trans('storefront::product_collection.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_collection.index', updateUrlParams())->with('error', $e->getMessage());
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
            'collection' => $this->collections->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->collections->getFilters($request, $this->collections->getStatusOptions(true)),
            'statusOptions' => $this->collections->getStatusOptions(),
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
            'statusOptions' => $this->collections->getStatusOptions(),
            'categoryOptions' => $this->tree->options(),
            'brandOptions' => CatalogBrand::query()->with('translations')->enabled()->orderBy('slug')->get()
                ->mapWithKeys(fn (CatalogBrand $brand): array => [$brand->id => (string) ($brand->name ?? $brand->slug)])->all(),
            'sellerOptions' => Seller::query()->active()->orderBy('store_name')->get(['id', 'store_name', 'legal_name', 'code'])
                ->mapWithKeys(fn (Seller $seller): array => [$seller->id => $seller->displayName()])->all(),
            'sortOptions' => collect(array_keys(ProductCollection::SORTS))
                ->mapWithKeys(fn (string $sort): array => [$sort => trans('storefront::product_collection.sorts.'.$sort)])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ProductCollectionRequest $request): array
    {
        $validated = $request->validated();
        $data = Arr::only($validated, ['slug', 'type', 'sort', 'status']);
        $data['rules'] = $request->collectionRules();
        $data['product_ids'] = $validated['type'] === ProductCollection::TYPE_MANUAL ? $request->productIds() : null;

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
        return ['module_name' => (string) config('storefront.upload.collection'), 'dbfield' => 'banner_image'];
    }

    private function storeBanner(Request $request, ProductCollection $collection): string
    {
        $stored = $this->collections->setUploadParams($this->uploadParams())->setModel($collection)->uploadImage($request);
        $this->collections->setModel(new ProductCollection);

        return (string) $stored['banner_image'];
    }

    private function findOrFail(mixed $id): ProductCollection
    {
        $collection = $id ? $this->collections->find((int) $id) : null;

        if (! $collection instanceof ProductCollection) {
            throw new \RuntimeException(trans('storefront::product_collection.messages.data_invalid'));
        }

        return $collection;
    }
}
