<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Catalog\Transformers\CategoryDetailResource;
use Modules\Catalog\Transformers\CategoryNodeResource;
use Modules\Core\Http\Controllers\Api\ApiController;

/**
 * Public category API: the enabled tree, and one category by slug with its breadcrumbs,
 * children and filterable attributes. Disabled categories (or ones under a disabled
 * ancestor) are a 404.
 */
class CategoryController extends ApiController
{
    public function __construct(
        protected CategoryTreeService $tree,
        protected CatalogCategoryRepository $categories,
        protected CategoryAttributeResolver $attributes,
        protected CategoryDefaults $defaults,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $depth = (int) $request->query('depth', (string) config('catalog.api.default_depth', 3));
        $depth = max(1, min($depth, CatalogSettings::maxCategoryDepth()));

        return $this->ok(CategoryNodeResource::collection($this->tree->tree($depth))->resolve($request));
    }

    public function show(string $slug): JsonResponse|CategoryDetailResource
    {
        $category = $this->categories->findEnabledBySlug($slug);
        $ancestors = $category ? $this->tree->ancestors($category) : [];

        $hiddenByAncestor = collect($ancestors)->contains(fn (array $node): bool => $node['status'] !== (int) config('core.enabled'));

        if ($category === null || $hiddenByAncestor) {
            return $this->fail(404, trans('catalog::catalog.errors.not_found'));
        }

        $children = $this->findBranch($this->tree->tree(), $category->id)['children'] ?? [];

        return (new CategoryDetailResource($category))
            ->withContext(
                ancestors: $ancestors,
                children: $children,
                filterableAttributes: $this->attributes->filterable($category)->all(),
                defaults: $this->defaults->for($category),
            )
            ->additional(['success' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    protected function findBranch(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }

            if (($found = $this->findBranch($node['children'], $id)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
