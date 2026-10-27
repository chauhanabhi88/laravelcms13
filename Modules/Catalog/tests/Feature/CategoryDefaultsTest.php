<?php

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Services\CategoryTreeService;

function defaultsService(): CategoryDefaults
{
    return app(CategoryDefaults::class);
}

test('a category inherits blank defaults from its nearest ancestor', function () {
    $root = CatalogCategory::factory()->create(['gst_percent' => 5, 'hsn_code' => '6204', 'return_window_days' => 10, 'dispatch_sla_hours' => 24]);
    $middle = CatalogCategory::factory()->child($root)->create(['hsn_code' => '6206']);
    $leaf = CatalogCategory::factory()->child($middle)->create();

    expect(defaultsService()->for($leaf->fresh()))
        ->gst_percent->toBe('5.00')
        ->hsn_code->toBe('6206')
        ->return_window_days->toBe(10)
        ->dispatch_sla_hours->toBe(24)
        ->return_type->toBe('return_exchange');
});

test('the category own value wins over its ancestors', function () {
    $root = CatalogCategory::factory()->create(['gst_percent' => 5, 'return_type' => 'exchange_only']);
    $leaf = CatalogCategory::factory()->child($root)->create(['gst_percent' => 18, 'return_type' => 'none']);

    expect(defaultsService()->for($leaf->id))
        ->gst_percent->toBe('18.00')
        ->return_type->toBe('none');
});

test('without any value the marketplace defaults apply', function () {
    config([
        'catalog.defaults.default_return_window' => 7,
        'catalog.defaults.default_dispatch_sla_hours' => 48,
        'catalog.defaults.default_gst_percent' => 5,
    ]);

    $leaf = CatalogCategory::factory()->create();

    expect(defaultsService()->for($leaf))
        ->gst_percent->toBe('5.00')
        ->hsn_code->toBeNull()
        ->return_window_days->toBe(7)
        ->dispatch_sla_hours->toBe(48)
        ->and(defaultsService()->inheritedFrom(null)['return_window_days'])->toBe(7);
});

test('inherited defaults follow a change of the ancestor', function () {
    $root = CatalogCategory::factory()->create(['return_window_days' => 10]);
    $leaf = CatalogCategory::factory()->child($root)->create();

    expect(defaultsService()->for($leaf)['return_window_days'])->toBe(10);

    app(CategoryTreeService::class)->update($root, ['return_window_days' => 15]);

    expect(defaultsService()->for($leaf)['return_window_days'])->toBe(15);
});
