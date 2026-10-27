<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Settings\Repositories\SettingsRepository;

require_once __DIR__.'/../Helpers.php';

test('the settings screen shows the catalog settings', function () {
    $this->actingAs(catalogAdmin(['admin.settings.getModuleSetting']))
        ->getJson(route('admin.settings.getModuleSetting', ['module' => 'Catalog']))
        ->assertSuccessful()
        ->assertJsonPath('type', 'success')
        ->assertSee('Default Return Window (days)')
        ->assertSee('Maximum Variant Axes per Category');
});

test('saved settings replace the config defaults', function () {
    DB::table('settings')->where('name', 'catalog')->delete();
    DB::table('settings')->insert(['name' => 'catalog', 'value' => json_encode(['default_return_window' => '15', 'default_gst_percent' => '12', 'max_variant_axes' => '3']), 'created_at' => now(), 'updated_at' => now()]);
    app()->forgetInstance(SettingsRepository::class);

    expect(CatalogSettings::defaultReturnWindow())->toBe(15)
        ->and(CatalogSettings::maxVariantAxes())->toBe(3)
        ->and(CatalogSettings::defaultDispatchSlaHours())->toBe(48)
        ->and(app(CategoryDefaults::class)->for(CatalogCategory::factory()->create()))
        ->toMatchArray(['return_window_days' => 15, 'gst_percent' => '12.00']);
});
