<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Cache\CacheClassPolicy;
use Modules\Core\Cache\FileStore;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\NullHandler;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/core-cache-classes-'.uniqid();
    $this->files = new Filesystem;
    // Laravel 13's default: config('cache.serializable_classes') = false.
    $this->store = new FileStore($this->files, $this->directory, null, false);
});

afterEach(function () {
    $this->files->deleteDirectory($this->directory);
});

test('cached models come back as models with serializable_classes = false', function () {
    $category = (new CatalogCategory)->forceFill(['id' => 7, 'slug' => 'silk-sarees', 'created_at' => Carbon::parse('2026-10-01 10:00:00')]);
    $category->setRelation('children', new Collection([(new CatalogCategory)->forceFill(['id' => 8, 'slug' => 'banarasi'])]));

    $this->store->put('category', $category, 60);
    $cached = $this->store->get('category');

    expect($cached)->toBeInstanceOf(CatalogCategory::class)
        ->and($cached->slug)->toBe('silk-sarees')
        ->and($cached->children)->toBeInstanceOf(Collection::class)
        ->and($cached->children->first())->toBeInstanceOf(CatalogCategory::class);
});

test('plain data and dates survive the cache', function () {
    $this->store->put('mixed', ['when' => Carbon::parse('2026-10-27'), 'row' => (object) ['id' => 1]], 60);

    $cached = $this->store->get('mixed');

    expect($cached['when'])->toBeInstanceOf(Carbon::class)
        ->and($cached['row'])->toBeInstanceOf(stdClass::class);
});

test('vendor classes stay blocked', function () {
    $this->store->put('gadget', new BufferHandler(new NullHandler), 60);

    expect($this->store->get('gadget'))->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

test('the policy accepts module data namespaces only', function (string $class, bool $allowed) {
    expect(CacheClassPolicy::isAllowed($class))->toBe($allowed);
})->with([
    'model' => ['Modules\Catalog\Models\CatalogCategory', true],
    'enum' => ['Modules\Seller\Enums\SellerStatus', true],
    'dto' => ['Modules\Storefront\DTO\ReviewSummary', true],
    'value object' => ['Modules\Catalog\Support\ResolvedAttribute', true],
    'collection' => ['Illuminate\Database\Eloquent\Collection', true],
    'service' => ['Modules\Catalog\Services\CategoryTreeService', false],
    'vendor' => ['GuzzleHttp\Cookie\FileCookieJar', false],
    'broadcast gadget' => ['Illuminate\Broadcasting\PendingBroadcast', false],
]);

test('an explicit allow list and true are still honoured', function () {
    expect(CacheClassPolicy::allowedClasses('O:8:"stdClass":0:{}', ['App\Models\User']))->toBe(['App\Models\User', 'stdClass'])
        ->and(CacheClassPolicy::allowedClasses('anything', true))->toBeTrue();
});
