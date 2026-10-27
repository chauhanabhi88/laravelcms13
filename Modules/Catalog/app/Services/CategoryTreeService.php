<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Support\CatalogCache;
use Modules\Catalog\Support\CatalogSettings;

/**
 * The only writer of the category tree: keeps path, depth and is_leaf consistent, guards
 * against cycles, over-deep trees and categories that hold products, and announces every
 * change (CatalogTreeChanged) so the tree, defaults, attribute and mega-menu caches drop.
 *
 * Read side: nodes() is the whole tree as flat arrays (cached per locale); tree() nests it.
 *
 * @phpstan-type Node array{id: int, parent_id: int|null, path: string, depth: int, slug: string, name: string, image: string|null, icon: string|null, banner_image: string|null, is_leaf: int, sort_order: int, show_in_menu: int, status: int}
 */
class CategoryTreeService
{
    public function __construct(
        protected CatalogCategoryRepository $categories,
        protected CategoryInUse $inUse,
        protected CatalogCache $cache,
    ) {}

    /**
     * Create a category (translations included in $data as locale => fields) under
     * $data['parent_id'], placed last among its siblings unless sort_order is given.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CatalogCategory
    {
        return DB::transaction(function () use ($data): CatalogCategory {
            $parent = $this->lockParent($data['parent_id'] ?? null);
            $this->assertCanReceiveChild($parent);

            if (($data['sort_order'] ?? null) === null || $data['sort_order'] === '') {
                $data['sort_order'] = $this->nextSortOrder($parent?->id);
            }

            $data['parent_id'] = $parent?->id;

            /** @var CatalogCategory $category */
            $category = $this->categories->create($data);

            $category->newQuery()->whereKey($category->id)->update([
                'path' => ($parent ? $parent->path : '/').$category->id.'/',
                'depth' => $parent ? $parent->depth + 1 : 0,
                'is_leaf' => config('core.yes'),
            ]);

            $this->refreshLeafFlag($parent?->id);

            event(new CatalogTreeChanged($category->id, 'created'));

            return $this->fresh($category->id);
        });
    }

    /**
     * Update a category's own fields; a changed parent_id moves it (with its subtree).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CatalogCategory $category, array $data): CatalogCategory
    {
        return DB::transaction(function () use ($category, $data): CatalogCategory {
            $category = $this->lock($category->id);

            if (array_key_exists('parent_id', $data)) {
                $newParentId = $this->normaliseId($data['parent_id']);

                if ($newParentId !== $category->parent_id) {
                    $this->moveLocked($category, $newParentId, null);
                    $category = $this->lock($category->id);
                }
            }

            $this->categories->update($category, Arr::except($data, ['parent_id', 'path', 'depth', 'is_leaf']));

            event(new CatalogTreeChanged($category->id, 'updated'));

            return $this->fresh($category->id);
        });
    }

    /**
     * Re-parent $category under $parentId (null = root) at the 0-based sibling $position
     * (null = last). Descendant paths and depths are rewritten in one statement.
     */
    public function move(CatalogCategory $category, ?int $parentId, ?int $position = null): CatalogCategory
    {
        return DB::transaction(function () use ($category, $parentId, $position): CatalogCategory {
            $this->moveLocked($this->lock($category->id), $parentId, $position);

            event(new CatalogTreeChanged($category->id, 'moved'));

            return $this->fresh($category->id);
        });
    }

    public function setStatus(CatalogCategory $category, int $status): CatalogCategory
    {
        $this->categories->update($category, ['status' => $status]);

        event(new CatalogTreeChanged($category->id, 'status'));

        return $category;
    }

    /**
     * Soft-delete a category that has neither children nor products.
     */
    public function delete(CatalogCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $category = $this->lock($category->id);

            if ($category->newQuery()->where('parent_id', $category->id)->exists()) {
                throw CatalogException::because('has_children', ['name' => $category->name]);
            }

            if ($this->inUse->isInUse($category->id)) {
                throw CatalogException::because('has_products', ['name' => $category->name]);
            }

            $this->categories->destroy($category);
            $this->refreshLeafFlag($category->parent_id);

            event(new CatalogTreeChanged($category->id, 'deleted'));
        });
    }

    /**
     * Delete several categories, deepest first, so a parent selected together with its
     * children goes too. Categories that still have children or products are skipped.
     *
     * @param  list<int>  $ids
     * @return array{deleted: list<int>, skipped: array<int, string>}
     */
    public function deleteMany(array $ids): array
    {
        $categories = CatalogCategory::query()->with('translations')->whereIn('id', $ids)->orderByDesc('depth')->get();
        $result = ['deleted' => [], 'skipped' => []];

        foreach ($categories as $category) {
            try {
                $this->delete($category);
                $result['deleted'][] = $category->id;
            } catch (CatalogException $e) {
                $result['skipped'][$category->id] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Recompute every path, depth and leaf flag from parent_id (repair / bulk import).
     */
    public function rebuildPaths(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('catalog_category')->orderBy('id')->get(['id', 'parent_id', 'deleted_at']);
            $byParent = $rows->groupBy(fn (object $row): int => (int) $row->parent_id);
            $queue = [[0, '/', -1]];

            while ($queue !== []) {
                [$parentId, $parentPath, $parentDepth] = array_shift($queue);

                foreach ($byParent->get($parentId, []) as $row) {
                    $path = $parentPath.$row->id.'/';
                    $hasChildren = $byParent->get((int) $row->id, collect())->contains(fn (object $child): bool => $child->deleted_at === null);

                    DB::table('catalog_category')->where('id', $row->id)->update([
                        'path' => $path,
                        'depth' => $parentDepth + 1,
                        'is_leaf' => $hasChildren ? config('core.no') : config('core.yes'),
                    ]);

                    $queue[] = [(int) $row->id, $path, $parentDepth + 1];
                }
            }
        });

        event(new CatalogTreeChanged(null, 'rebuilt'));
    }

    /**
     * Ids of the category's descendants (not including itself), via the materialised path.
     *
     * @return list<int>
     */
    public function descendantIds(CatalogCategory $category): array
    {
        return $category->newQuery()
            ->where('path', 'like', $category->path.'%')
            ->whereKeyNot($category->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Ancestors as nodes, root first (excludes the category itself).
     *
     * @return list<Node>
     */
    public function ancestors(CatalogCategory $category): array
    {
        $nodes = $this->nodesById();

        return array_values(array_filter(array_map(fn (int $id): ?array => $nodes[$id] ?? null, $category->ancestorIds())));
    }

    /**
     * Every category (any status) as flat nodes in tree order, names in the current locale
     * (falling back to the default locale). Cached per locale until the tree changes.
     *
     * @return list<Node>
     */
    public function nodes(): array
    {
        $locale = app()->getLocale();
        $fallback = (string) config('translatable.fallback_locale', config('app.fallback_locale', 'en'));

        return $this->cache->remember('nodes.'.$locale, function () use ($locale, $fallback): array {
            $rows = DB::table('catalog_category as c')
                ->leftJoin('catalog_category_translation as t', fn ($join) => $join->on('t.catalog_category_id', '=', 'c.id')->where('t.locale', $locale))
                ->leftJoin('catalog_category_translation as f', fn ($join) => $join->on('f.catalog_category_id', '=', 'c.id')->where('f.locale', $fallback))
                ->whereNull('c.deleted_at')
                ->orderBy('c.depth')
                ->orderBy('c.sort_order')
                ->orderBy('c.id')
                ->get(['c.id', 'c.parent_id', 'c.path', 'c.depth', 'c.slug', 'c.image', 'c.icon', 'c.banner_image', 'c.is_leaf', 'c.sort_order', 'c.show_in_menu', 'c.status', 't.name as name', 'f.name as fallback_name']);

            $nodes = $rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'parent_id' => $row->parent_id === null ? null : (int) $row->parent_id,
                'path' => (string) $row->path,
                'depth' => (int) $row->depth,
                'slug' => (string) $row->slug,
                'name' => (string) ($row->name ?? $row->fallback_name ?? $row->slug),
                'image' => $row->image,
                'icon' => $row->icon,
                'banner_image' => $row->banner_image,
                'is_leaf' => (int) $row->is_leaf,
                'sort_order' => (int) $row->sort_order,
                'show_in_menu' => (int) $row->show_in_menu,
                'status' => (int) $row->status,
            ])->all();

            return $this->inTreeOrder($nodes);
        });
    }

    /**
     * The tree as nested nodes (each with "children"), down to $depth levels (null = all).
     * $enabledOnly drops disabled categories together with everything below them.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(?int $depth = null, bool $enabledOnly = true, bool $menuOnly = false): array
    {
        $children = [];
        foreach ($this->nodes() as $node) {
            if ($enabledOnly && $node['status'] !== (int) config('core.enabled')) {
                continue;
            }
            if ($menuOnly && $node['show_in_menu'] !== (int) config('core.yes')) {
                continue;
            }
            if ($depth !== null && $node['depth'] >= $depth) {
                continue;
            }
            $children[$node['parent_id'] ?? 0][] = $node;
        }

        $build = function (int $parentId) use (&$build, $children): array {
            return array_map(function (array $node) use ($build): array {
                $node['children'] = $build($node['id']);

                return $node;
            }, $children[$parentId] ?? []);
        };

        return $build(0);
    }

    /**
     * Select options "Ethnic Wear › Kurtis", in tree order.
     *
     * @param  int|null  $excludeSubtreeOf  hide this category and its descendants (a parent picker)
     * @param  bool  $parentsOnly  only categories that may still receive a child (depth limit)
     * @return array<int|string, string>
     */
    public function options(bool $withPlaceholder = false, ?int $excludeSubtreeOf = null, bool $parentsOnly = false): array
    {
        $nodes = $this->nodesById();
        $excludedPath = $excludeSubtreeOf !== null ? ($nodes[$excludeSubtreeOf]['path'] ?? null) : null;
        $maxDepth = CatalogSettings::maxCategoryDepth();
        $options = $withPlaceholder ? ['' => ' -- '.trans('catalog::catalog_category.labels.root').' -- '] : [];

        foreach ($nodes as $node) {
            if ($excludedPath !== null && str_starts_with($node['path'], $excludedPath)) {
                continue;
            }
            if ($parentsOnly && $maxDepth <= $node['depth'] + 1) {
                continue;
            }
            $options[$node['id']] = $this->breadcrumbLabel($node, $nodes);
        }

        return $options;
    }

    /**
     * "Ethnic Wear › Kurtis › Anarkali Kurtis" for a category id.
     */
    public function breadcrumb(int $categoryId): string
    {
        $nodes = $this->nodesById();

        return isset($nodes[$categoryId]) ? $this->breadcrumbLabel($nodes[$categoryId], $nodes) : '';
    }

    /**
     * @return array<int, Node>
     */
    public function nodesById(): array
    {
        return array_column($this->nodes(), null, 'id');
    }

    /**
     * Set is_leaf on a category from whether it still has (non-deleted) children.
     */
    public function refreshLeafFlag(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $hasChildren = CatalogCategory::query()->where('parent_id', $categoryId)->exists();

        CatalogCategory::query()->whereKey($categoryId)->update([
            'is_leaf' => $hasChildren ? config('core.no') : config('core.yes'),
        ]);
    }

    protected function moveLocked(CatalogCategory $category, ?int $parentId, ?int $position): void
    {
        $oldParentId = $category->parent_id;
        $parent = $this->lockParent($parentId);

        if ($parent !== null && ($parent->id === $category->id || str_starts_with($parent->path, $category->path))) {
            throw CatalogException::because('cycle');
        }

        if ($parent?->id !== $oldParentId) {
            $this->assertCanReceiveChild($parent);
            $this->assertSubtreeFits($category, $parent);

            $oldPath = $category->path;
            $newPath = ($parent ? $parent->path : '/').$category->id.'/';
            $depthShift = ($parent ? $parent->depth + 1 : 0) - $category->depth;

            $category->newQuery()->whereKey($category->id)->update(['parent_id' => $parent?->id]);

            // One statement for the whole subtree (trashed rows included, so restores stay consistent).
            $category->newQuery()->withTrashed()
                ->where('path', 'like', $oldPath.'%')
                ->update([
                    'path' => DB::raw('CONCAT('.DB::getPdo()->quote($newPath).', SUBSTRING(path, '.(strlen($oldPath) + 1).'))'),
                    'depth' => DB::raw('depth + ('.$depthShift.')'),
                ]);
        }

        $this->placeAmongSiblings($category->id, $parent?->id, $position);

        $this->refreshLeafFlag($oldParentId);
        $this->refreshLeafFlag($parent?->id);
    }

    /**
     * A parent must be shallow enough for one more level, and must not hold products.
     */
    protected function assertCanReceiveChild(?CatalogCategory $parent): void
    {
        if ($parent === null) {
            return;
        }

        $maxDepth = CatalogSettings::maxCategoryDepth();
        if ($parent->depth + 1 >= $maxDepth) {
            throw CatalogException::because('too_deep', ['max' => $maxDepth]);
        }

        if ($parent->isLeaf() && $this->inUse->isInUse($parent->id)) {
            throw CatalogException::because('parent_has_products', ['name' => $parent->name]);
        }
    }

    protected function assertSubtreeFits(CatalogCategory $category, ?CatalogCategory $parent): void
    {
        $deepest = (int) $category->newQuery()->where('path', 'like', $category->path.'%')->max('depth');
        $newDepth = $parent ? $parent->depth + 1 : 0;
        $maxDepth = CatalogSettings::maxCategoryDepth();

        if ($deepest - $category->depth + $newDepth >= $maxDepth) {
            throw CatalogException::because('too_deep', ['max' => $maxDepth]);
        }
    }

    /**
     * Renumber the siblings (0, 1, 2...) with the category at $position (null = keep its
     * current sort order relative to the others, i.e. append when it just arrived).
     */
    protected function placeAmongSiblings(int $categoryId, ?int $parentId, ?int $position): void
    {
        if ($position === null) {
            return;
        }

        $siblings = CatalogCategory::query()
            ->where(fn ($query) => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId))
            ->whereKeyNot($categoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        array_splice($siblings, max(0, min($position, count($siblings))), 0, [$categoryId]);

        foreach ($siblings as $index => $id) {
            CatalogCategory::query()->whereKey($id)->where('sort_order', '!=', $index)->update(['sort_order' => $index]);
        }
    }

    protected function nextSortOrder(?int $parentId): int
    {
        $max = CatalogCategory::query()
            ->where(fn ($query) => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId))
            ->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    protected function lockParent(mixed $parentId): ?CatalogCategory
    {
        $parentId = $this->normaliseId($parentId);

        if ($parentId === null) {
            return null;
        }

        $parent = CatalogCategory::query()->with('translations')->lockForUpdate()->find($parentId);

        if ($parent === null) {
            throw CatalogException::because('parent_missing');
        }

        return $parent;
    }

    protected function lock(int $id): CatalogCategory
    {
        return CatalogCategory::query()->with('translations')->lockForUpdate()->findOrFail($id);
    }

    protected function fresh(int $id): CatalogCategory
    {
        return CatalogCategory::query()->with('translations')->findOrFail($id);
    }

    protected function normaliseId(mixed $id): ?int
    {
        return $id === null || $id === '' || (int) $id === 0 ? null : (int) $id;
    }

    /**
     * Depth-first order (root, its children by sort order, ...), from depth-sorted nodes.
     *
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    protected function inTreeOrder(array $nodes): array
    {
        $children = [];
        foreach ($nodes as $node) {
            $children[$node['parent_id'] ?? 0][] = $node;
        }

        $ordered = [];
        $walk = function (int $parentId) use (&$walk, &$ordered, $children): void {
            foreach ($children[$parentId] ?? [] as $node) {
                $ordered[] = $node;
                $walk($node['id']);
            }
        };
        $walk(0);

        return $ordered;
    }

    /**
     * @param  Node  $node
     * @param  array<int, Node>  $nodes
     */
    protected function breadcrumbLabel(array $node, array $nodes): string
    {
        $names = [];
        foreach (array_filter(explode('/', $node['path']), 'strlen') as $id) {
            $names[] = $nodes[(int) $id]['name'] ?? '';
        }

        return implode(' › ', array_filter($names));
    }
}
