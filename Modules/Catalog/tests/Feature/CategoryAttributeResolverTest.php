<?php

use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryAttributeService;
use Modules\Catalog\Support\ResolvedAttribute;

/**
 * @param  list<string>  $options
 */
function catalogAttribute(string $code, array $options = []): Attribute
{
    $attribute = Attribute::query()->create(['code' => $code.'-'.uniqid(), 'input_type' => 'select', 'custom_value' => 1, 'is_required' => 2]);
    $attribute->translations()->forceCreate(['name' => ucfirst($code), 'locale' => app()->getLocale()]);

    foreach ($options as $index => $option) {
        $row = AttributeOption::query()->create(['attribute_id' => $attribute->id, 'custom_option' => strtolower($option), 'default' => 2, 'sort_order' => $index]);
        $row->translations()->forceCreate(['name' => $option, 'locale' => app()->getLocale()]);
    }

    return $attribute;
}

/**
 * @param  array<string, mixed>  $flags
 */
function mapAttribute(CatalogCategory $category, Attribute $attribute, array $flags = []): CatalogCategoryAttribute
{
    return CatalogCategoryAttribute::query()->create($flags + [
        'catalog_category_id' => $category->id,
        'attribute_id' => $attribute->id,
        'is_variant_axis' => 2,
        'is_filterable' => 2,
        'is_required' => 2,
        'show_on_card' => 2,
        'sort_order' => 0,
    ]);
}

function resolver(): CategoryAttributeResolver
{
    return app(CategoryAttributeResolver::class);
}

test('a leaf gets the union of the mappings along its path', function () {
    $root = CatalogCategory::factory()->create();
    $leaf = CatalogCategory::factory()->child($root)->create();
    $colour = catalogAttribute('colour', ['Red', 'Blue']);
    $fabric = catalogAttribute('fabric', ['Cotton']);

    mapAttribute($root, $colour, ['is_variant_axis' => 1, 'sort_order' => 2]);
    mapAttribute($leaf, $fabric, ['is_filterable' => 1, 'sort_order' => 1]);

    $resolved = resolver()->for($leaf->fresh());

    expect($resolved->map(fn (ResolvedAttribute $attribute) => $attribute->attributeId)->all())->toBe([$fabric->id, $colour->id])
        ->and($resolved[1])->isVariantAxis->toBeTrue()->inherited->toBeTrue()->sourceCategoryId->toBe($root->id)
        ->and($resolved[0])->isFilterable->toBeTrue()->inherited->toBeFalse()
        ->and(resolver()->variantAxes($leaf)->pluck('attributeId')->all())->toBe([$colour->id])
        ->and(resolver()->filterable($leaf)->pluck('attributeId')->all())->toBe([$fabric->id]);
});

test('a mapping on a child overrides the inherited one for the same attribute', function () {
    $root = CatalogCategory::factory()->create();
    $leaf = CatalogCategory::factory()->child($root)->create();
    $size = catalogAttribute('size', ['S', 'M']);

    mapAttribute($root, $size, ['is_variant_axis' => 1, 'is_required' => 1]);
    mapAttribute($leaf, $size, ['is_variant_axis' => 2, 'is_filterable' => 1]);

    $resolved = resolver()->for($leaf);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])
        ->isVariantAxis->toBeFalse()
        ->isFilterable->toBeTrue()
        ->isRequired->toBeFalse()
        ->inherited->toBeFalse();
});

test('allowed options restrict the options of the attribute', function () {
    $leaf = CatalogCategory::factory()->create();
    $size = catalogAttribute('size', ['XS', 'S', 'M', 'L']);
    $allowed = $size->attributeOption()->whereIn('custom_option', ['s', 'm'])->pluck('id')->all();

    mapAttribute($leaf, $size, ['allowed_option_ids' => $allowed]);

    expect(collect(resolver()->for($leaf)[0]->options)->pluck('name')->all())->toBe(['S', 'M']);
});

test('saving a mapping validates the variant axis limit across the subtree', function () {
    config(['catalog.defaults.max_variant_axes' => 2]);

    $root = CatalogCategory::factory()->create();
    $leaf = CatalogCategory::factory()->child($root)->create();
    $size = catalogAttribute('size');
    $colour = catalogAttribute('colour');
    $pattern = catalogAttribute('pattern');

    $service = app(CategoryAttributeService::class);
    $service->sync($leaf, [$size->id => ['is_variant_axis' => 1], $colour->id => ['is_variant_axis' => 1]]);

    expect(fn () => $service->sync($root, [$pattern->id => ['is_variant_axis' => 1]]))->toThrow(CatalogException::class)
        ->and(CatalogCategoryAttribute::query()->where('catalog_category_id', $root->id)->count())->toBe(0);

    $service->sync($root, [$pattern->id => ['is_filterable' => 1]]);

    expect(resolver()->for($leaf)->pluck('attributeId')->sort()->values()->all())
        ->toBe(collect([$size->id, $colour->id, $pattern->id])->sort()->values()->all());
});

test('saving a mapping drops rows that were removed and ignores foreign options', function () {
    $leaf = CatalogCategory::factory()->create();
    $size = catalogAttribute('size', ['S']);
    $colour = catalogAttribute('colour', ['Red']);
    $foreignOption = $colour->attributeOption()->value('id');

    $service = app(CategoryAttributeService::class);
    $service->sync($leaf, [$size->id => ['is_filterable' => 1], $colour->id => []]);
    $service->sync($leaf, [$size->id => ['allowed_option_ids' => [$foreignOption]]]);

    $rows = CatalogCategoryAttribute::query()->where('catalog_category_id', $leaf->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->attribute_id)->toBe($size->id)
        ->and($rows[0]->allowed_option_ids)->toBeNull()
        ->and($rows[0]->is_filterable)->toBe(2);
});
