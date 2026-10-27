<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Http\Requests\CategoryAttributeRequest;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryAttributeService;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Core\Http\Controllers\BackendController;

/**
 * The attribute mapping tab of a category: its own rows (editable) and the rows it
 * inherits from its ancestors (read-only, with an "override" shortcut).
 */
class CategoryAttributeController extends BackendController
{
    public function __construct(
        private CatalogCategoryRepository $categories,
        private CategoryAttributeService $mappings,
        private CategoryAttributeResolver $resolver,
        private CategoryTreeService $tree,
    ) {
        parent::__construct();
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            $category = $this->findOrFail($request->route('id'));
            $ownRows = $this->mappings->ownRows($category);

            $inherited = $category->parent_id
                ? $this->resolver->for($category->parent_id)->map(fn (ResolvedAttribute $attribute): array => $attribute->toArray() + [
                    'source_name' => $this->tree->breadcrumb($attribute->sourceCategoryId),
                    'overridden' => isset($ownRows[$attribute->attributeId]),
                ])->all()
                : [];

            $attributes = $this->attributeCatalogue();

            return view('catalog::backend.catalog_category.attributes', [
                'catalogCategory' => $category,
                'breadcrumb' => $this->tree->breadcrumb($category->id),
                'ownRows' => $ownRows,
                'inherited' => $inherited,
                'attributes' => $attributes,
                'maxAxes' => CatalogSettings::maxVariantAxes(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function save(CategoryAttributeRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->mappings->sync($this->findOrFail($id), $request->mappings());

            return redirect()->route('admin.catalog_category.attributes', updateUrlParams([$id]))->with('success', trans('catalog::catalog_category.messages.attributes_saved'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.catalog_category.attributes', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Every product attribute with its options, for the "add attribute" picker and the
     * allowed-options lists. The module generator's own lookup attributes are left out.
     *
     * @return array<int, array{id: int, code: string, name: string, input_type: string, options: array<int, string>}>
     */
    private function attributeCatalogue(): array
    {
        return Attribute::query()
            ->with(['translations', 'attributeOption' => fn ($query) => $query->with('translations')->orderBy('sort_order')->orderBy('id')])
            ->whereNotIn('code', (array) config('catalog.hidden_attribute_codes', []))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Attribute $attribute): array => [(int) $attribute->id => [
                'id' => (int) $attribute->id,
                'code' => (string) $attribute->code,
                'name' => (string) ($attribute->name ?? $attribute->code),
                'input_type' => (string) $attribute->input_type,
                'options' => $attribute->attributeOption
                    ->mapWithKeys(fn (AttributeOption $option): array => [(int) $option->id => (string) ($option->name ?? $option->custom_option)])
                    ->all(),
            ]])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
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
