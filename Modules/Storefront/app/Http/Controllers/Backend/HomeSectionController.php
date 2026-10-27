<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Banner\Models\BannerGroup;
use Modules\Block\Models\Block;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Storefront\Http\Requests\HomeSectionRequest;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Repositories\HomeSectionRepository;
use Modules\Storefront\Repositories\ProductCollectionRepository;

/**
 * Home page sections: grid, form (type-specific config), schedule, status and ordering.
 * Every save flushes the home page cache (model events in the service provider).
 */
class HomeSectionController extends BackendController
{
    public function __construct(
        private HomeSectionRepository $sections,
        private ProductCollectionRepository $collections,
        private CategoryTreeService $tree,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('storefront.cache.home_section'), $request->input('per_page'))]);

            return view('storefront::backend.home_section.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('storefront.cache.home_section'), $request->input('per_page'))]);
            setFilterSession(config('storefront.cache.home_section'), $request);

            $content = view('storefront::backend.home_section.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.home_section.index')));

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
            return view('storefront::backend.home_section.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(HomeSectionRequest $request): RedirectResponse
    {
        try {
            $section = $this->sections->create($this->payload($request));

            if ($request->input('snc')) {
                return redirect()->route('admin.home_section.edit', updateUrlParams([$section->id]))->with('success', trans('storefront::home_section.messages.created_success'));
            }

            return redirect()->route('admin.home_section.index', updateUrlParams())->with('success', trans('storefront::home_section.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.create', updateUrlParams())->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('storefront::backend.home_section.edit', $this->formData() + ['homeSection' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(HomeSectionRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->sections->update($this->findOrFail($id), $this->payload($request));

            if ($request->input('snc')) {
                return redirect()->route('admin.home_section.edit', updateUrlParams([$id]))->with('success', trans('storefront::home_section.messages.updated_success'));
            }

            return redirect()->route('admin.home_section.index', updateUrlParams())->with('success', trans('storefront::home_section.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.edit', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($section = $this->sections->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->sections->update($section, ['status' => $status]);
        }

        return $this->refreshGrid($request, trans('core::core.messages.status_change_success'));
    }

    /**
     * Move a section one place up or down.
     */
    public function reorder(Request $request): JsonResponse
    {
        $section = $request->input('id') ? $this->sections->find((int) $request->input('id')) : null;

        if ($section instanceof HomeSection && in_array($request->input('direction'), ['up', 'down'], true)) {
            $this->sections->move($section, (string) $request->input('direction'));
            HomeSection::flushHomeCache();
        }

        return $this->refreshGrid($request, trans('storefront::home_section.messages.reordered_success'));
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->sections->deleteRecord($request);

            return redirect()->route('admin.home_section.index', updateUrlParams())->with('success', trans('storefront::home_section.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function massDelete(Request $request): RedirectResponse
    {
        try {
            $this->sections->destroyMultiple($request);
            HomeSection::flushHomeCache();

            return redirect()->route('admin.home_section.index', updateUrlParams())->with('success', trans('storefront::home_section.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.home_section.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    private function refreshGrid(Request $request, string $message): JsonResponse
    {
        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => $message]);

        return $this->filters($refresh);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->sections->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->sections->getFilters($request, $this->sections->getStatusOptions(true), $this->typeOptions()),
            'statusOptions' => $this->sections->getStatusOptions(),
            'typeOptions' => $this->typeOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'languageOptions' => getLanguageOptions() ?: [config('app.locale') => config('app.locale')],
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->sections->getStatusOptions(),
            'bannerGroupOptions' => ['' => ' -- '.trans('core::core.labels.select').' -- '] + BannerGroup::query()->orderBy('code')->pluck('code', 'code')->all(),
            'blockOptions' => ['' => ' -- '.trans('core::core.labels.select').' -- '] + Block::query()->orderBy('slug')->pluck('slug', 'slug')->all(),
            'collectionOptions' => $this->collections->getOptions(true),
            'categoryOptions' => $this->tree->options(),
            'nextSortOrder' => $this->sections->nextSortOrder(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        $options = [];
        foreach ((array) config('storefront.home_section_types') as $type) {
            $options[$type] = trans('storefront::home_section.types.'.$type);
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HomeSectionRequest $request): array
    {
        $validated = $request->validated();
        $format = (string) config('core.encrypt.php_datepicker_format', 'd-m-Y');

        $data = [
            'type' => $validated['type'],
            'config' => $request->sectionConfig(),
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $this->sections->nextSortOrder(),
            'status' => $validated['status'],
            'starts_at' => ! empty($validated['starts_at']) ? Carbon::createFromFormat($format, $validated['starts_at'])->startOfDay() : null,
            'ends_at' => ! empty($validated['ends_at']) ? Carbon::createFromFormat($format, $validated['ends_at'])->endOfDay() : null,
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $data[$locale] = [
                'title' => $validated[$locale]['title'] ?? null,
                'subtitle' => $validated[$locale]['subtitle'] ?? null,
            ];
        }

        return $data;
    }

    private function findOrFail(mixed $id): HomeSection
    {
        $section = $id ? $this->sections->find((int) $id) : null;

        if (! $section instanceof HomeSection) {
            throw new \RuntimeException(trans('storefront::home_section.messages.data_invalid'));
        }

        return $section;
    }
}
