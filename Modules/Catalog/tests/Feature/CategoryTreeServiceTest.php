<?php

use Modules\Catalog\Contracts\CategoryInUse;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryTreeService;

function treeService(): CategoryTreeService
{
    return app(CategoryTreeService::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCategory(string $name, ?CatalogCategory $parent = null, array $attributes = []): CatalogCategory
{
    return treeService()->create($attributes + [
        'slug' => str($name)->slug().'-'.uniqid(),
        'parent_id' => $parent?->id,
        'status' => config('core.enabled'),
        'show_in_menu' => config('core.yes'),
        app()->getLocale() => ['name' => $name],
    ]);
}

test('creating categories sets path, depth and leaf flags', function () {
    $root = makeCategory('Ethnic Wear');
    $child = makeCategory('Kurtis', $root);
    $leaf = makeCategory('Anarkali Kurtis', $child);

    expect($root->fresh())
        ->path->toBe("/{$root->id}/")
        ->depth->toBe(0)
        ->is_leaf->toBe(2)
        ->and($child->fresh())
        ->path->toBe("/{$root->id}/{$child->id}/")
        ->depth->toBe(1)
        ->is_leaf->toBe(2)
        ->and($leaf->fresh())
        ->path->toBe("/{$root->id}/{$child->id}/{$leaf->id}/")
        ->depth->toBe(2)
        ->is_leaf->toBe(1)
        ->and($leaf->name)->toBe('Anarkali Kurtis');
});

test('siblings are appended in order', function () {
    $root = makeCategory('Men');
    $first = makeCategory('Shirts', $root);
    $second = makeCategory('Jeans', $root);

    expect($first->sort_order)->toBe(0)->and($second->sort_order)->toBe(1);
});

test('moving a category rewrites the paths and depths of its whole subtree', function () {
    $women = makeCategory('Women');
    $men = makeCategory('Men');
    $ethnic = makeCategory('Ethnic', $women);
    $kurtas = makeCategory('Kurtas', $ethnic);

    treeService()->move($ethnic, $men->id);

    expect($ethnic->fresh())
        ->parent_id->toBe($men->id)
        ->path->toBe("/{$men->id}/{$ethnic->id}/")
        ->depth->toBe(1)
        ->and($kurtas->fresh())
        ->path->toBe("/{$men->id}/{$ethnic->id}/{$kurtas->id}/")
        ->depth->toBe(2)
        ->and($women->fresh()->is_leaf)->toBe(1)
        ->and($men->fresh()->is_leaf)->toBe(2);
});

test('moving to the top level makes a root', function () {
    $root = makeCategory('Home');
    $child = makeCategory('Kitchen', $root);
    $grandChild = makeCategory('Cookware', $child);

    treeService()->move($child, null);

    expect($child->fresh())->parent_id->toBeNull()->path->toBe("/{$child->id}/")->depth->toBe(0)
        ->and($grandChild->fresh())->path->toBe("/{$child->id}/{$grandChild->id}/")->depth->toBe(1);
});

test('a category cannot be moved under itself or its descendants', function () {
    $root = makeCategory('Beauty');
    $child = makeCategory('Makeup', $root);

    expect(fn () => treeService()->move($root, $child->id))->toThrow(CatalogException::class)
        ->and(fn () => treeService()->move($root, $root->id))->toThrow(CatalogException::class)
        ->and($root->fresh()->parent_id)->toBeNull();
});

test('the position reorders siblings', function () {
    $root = makeCategory('Bags');
    $a = makeCategory('A', $root);
    $b = makeCategory('B', $root);
    $c = makeCategory('C', $root);

    treeService()->move($c, $root->id, 0);

    expect(CatalogCategory::query()->where('parent_id', $root->id)->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

test('the tree depth is limited', function () {
    config(['catalog.defaults.max_category_depth' => 2]);

    $root = makeCategory('Gadgets');
    $child = makeCategory('Audio', $root);

    expect(fn () => makeCategory('Earbuds', $child))->toThrow(CatalogException::class);

    $other = makeCategory('Other');
    $otherChild = makeCategory('Other child', $other);

    expect(fn () => treeService()->move($other, $root->id))->toThrow(CatalogException::class)
        ->and($otherChild->fresh()->depth)->toBe(1);
});

test('a category with children or products cannot be deleted', function () {
    $root = makeCategory('Jewellery');
    $child = makeCategory('Earrings', $root);

    expect(fn () => treeService()->delete($root))->toThrow(CatalogException::class);

    app()->instance(CategoryInUse::class, new class implements CategoryInUse
    {
        public function isInUse(int $categoryId): bool
        {
            return true;
        }
    });
    app()->forgetInstance(CategoryTreeService::class);

    expect(fn () => treeService()->delete($child))->toThrow(CatalogException::class)
        ->and(fn () => makeCategory('Jhumkas', $child))->toThrow(CatalogException::class);
});

test('deleting the last child turns the parent back into a leaf', function () {
    $root = makeCategory('Toys');
    $child = makeCategory('Puzzles', $root);

    treeService()->delete($child);

    expect($child->fresh()->trashed())->toBeTrue()
        ->and($root->fresh()->is_leaf)->toBe(1);
});

test('mass delete removes children before parents and skips what is blocked', function () {
    $root = makeCategory('Home');
    $child = makeCategory('Decor', $root);
    $keep = makeCategory('Kitchen');
    makeCategory('Cookware', $keep);

    $result = treeService()->deleteMany([$root->id, $child->id, $keep->id]);

    expect($result['deleted'])->toEqualCanonicalizing([$root->id, $child->id])
        ->and(array_keys($result['skipped']))->toBe([$keep->id]);
});

test('the nested tree hides disabled branches and respects depth', function () {
    $root = makeCategory('Western Wear');
    $tops = makeCategory('Tops', $root);
    makeCategory('Crop Tops', $tops);
    makeCategory('Dresses', $root, ['status' => config('core.disabled')]);

    $tree = collect(treeService()->tree())->firstWhere('id', $root->id);
    $shallow = collect(treeService()->tree(2))->firstWhere('id', $root->id);

    expect(collect($tree['children'])->pluck('name')->all())->toBe(['Tops'])
        ->and($tree['children'][0]['children'][0]['name'])->toBe('Crop Tops')
        ->and($shallow['children'][0]['children'])->toBe([]);
});

test('the tree cache is dropped when the tree changes', function () {
    $root = makeCategory('Sports');
    expect(collect(treeService()->nodes())->firstWhere('id', $root->id)['name'])->toBe('Sports');

    treeService()->update($root, [app()->getLocale() => ['name' => 'Sports & Fitness']]);

    expect(collect(treeService()->nodes())->firstWhere('id', $root->id)['name'])->toBe('Sports & Fitness');
});

test('rebuilding paths repairs a broken tree', function () {
    $root = makeCategory('Books');
    $child = makeCategory('Fiction', $root);
    CatalogCategory::query()->whereKey($child->id)->update(['path' => '/broken/', 'depth' => 7]);

    treeService()->rebuildPaths();

    expect($child->fresh())->path->toBe("/{$root->id}/{$child->id}/")->depth->toBe(1);
});
