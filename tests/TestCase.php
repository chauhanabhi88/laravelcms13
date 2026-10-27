<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // nwidart loads module.json "files" with include_once, so the view composers in
        // Modules/Core/composers.php only reach the first application of the test process.
        // Later tests would render the layouts without $cssFiles / $jsFiles.
        require base_path('Modules/Core/composers.php');
    }
}
