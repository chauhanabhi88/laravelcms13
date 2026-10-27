<?php

use Modules\Theme\Http\Controllers\StyleguideController;

test('the styleguide is not available outside the local environment', function () {
    expect(app()->isLocal())->toBeFalse();

    $this->get('/styleguide')->assertNotFound();
    $this->get('/styleguide/seller')->assertNotFound();
});

test('the styleguide renders every component with sample data', function () {
    $this->withViewErrors([]);

    $html = app(StyleguideController::class)->index()->render();

    expect($html)
        ->toContain('Styleguide')
        ->toContain('75% off')
        ->toContain('role="tablist"')
        ->toContain('Why shop with us')
        ->toContain('qtyStepper(')
        ->toContain('dropzone(')
        ->toContain('priceRange(')
        ->toContain('pincodeChecker(');
});
