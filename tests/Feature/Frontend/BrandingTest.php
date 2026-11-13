<?php

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Theme\Support\ThemeSettings;

test('without a logo image the header and footer show the site name as a wordmark', function () {
    config(['app.name' => 'Bazaar Demo', 'theme.frontend_logo' => 'modules/theme/frontend/images/missing-logo.png']);

    expect(ThemeSettings::logoUrl())->toBeNull();

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertDontSee('missing-logo.png', false)
        ->assertSeeInOrder(['<header', 'Bazaar Demo', '</header>'], false)
        ->assertSeeInOrder(['<footer', 'Bazaar Demo', '</footer>'], false);
});

test('a configured logo is shown as an image', function () {
    DB::table('settings')->updateOrInsert(['name' => 'theme'], ['value' => json_encode(['logo_url' => 'https://cdn.example.test/logo.svg']), 'created_at' => now(), 'updated_at' => now()]);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('<img src="https://cdn.example.test/logo.svg"', false);
});

test('unmatched URLs answer 404 rather than failing on the storefront layout', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'missing asset' => ['/modules/theme/frontend/images/nope.png'],
    'deep path' => ['/no/such/deep/path'],
]);

test('page titles are escaped once', function () {
    $product = Product::factory()->live()->withVariants(1)->create(['name' => 'Salt & Pepper Grinder Set']);
    $response = $this->get(frontRoute('storefront.product.show', ['slugId' => $product->slug.'-'.$product->id]));

    $response->assertSuccessful();
    expect($response->getContent())
        ->toContain('<title>Salt &amp; Pepper Grinder Set')
        ->not->toContain('&amp;amp;');
});
