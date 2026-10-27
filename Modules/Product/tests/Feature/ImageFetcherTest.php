<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Support\Import\ImageFetcher;

require_once __DIR__.'/../SellerCatalogHelpers.php';

beforeEach(function () {
    Storage::fake('public');
});

function fetcherResolving(array $addresses): ImageFetcher
{
    return new ImageFetcher(app(ProductImageService::class), fn (string $host): array => $addresses);
}

function fetchError(ImageFetcher $fetcher, string $url): ?string
{
    try {
        $fetcher->fetch($url);
    } catch (ProductException $e) {
        return $e->getMessage();
    }

    return null;
}

test('a public image URL is downloaded, checked and stored', function () {
    Http::fake(['images.example.test/*' => Http::response(jpegBytes(600, 800), 200, ['Content-Type' => 'image/jpeg'])]);

    $image = fetcherResolving(['93.184.216.34'])->fetch('https://images.example.test/a.jpg');

    expect($image->width)->toBe(600)->and($image->height)->toBe(800)->and($image->contentHash)->not->toBeNull();
    Storage::disk('public')->assertExists('product/'.$image->path);
});

test('private, loopback, link-local and reserved addresses are refused before any request', function (string $url, array $resolved) {
    Http::fake();

    expect(fetchError(fetcherResolving($resolved), $url))->toContain('private or blocked');
    Http::assertNothingSent();
})->with([
    'loopback literal' => ['http://127.0.0.1/a.jpg', []],
    'private literal' => ['http://10.0.0.5/a.jpg', []],
    'metadata service' => ['http://169.254.169.254/latest/meta-data', []],
    'host resolving to private' => ['https://cdn.example.test/a.jpg', ['192.168.1.20']],
    'one private address among public ones' => ['https://cdn.example.test/a.jpg', ['93.184.216.34', '10.1.1.1']],
    'ipv6 loopback' => ['http://[::1]/a.jpg', []],
    'ipv4-mapped ipv6' => ['http://cdn.example.test/a.jpg', ['::ffff:10.0.0.1']],
    'unusual port' => ['http://cdn.example.test:22/a.jpg', ['93.184.216.34']],
]);

test('non-http schemes are refused', function () {
    expect(fetchError(fetcherResolving(['93.184.216.34']), 'file:///etc/passwd'))->toContain('not a valid http(s) address')
        ->and(fetchError(fetcherResolving(['93.184.216.34']), 'ftp://example.test/a.jpg'))->toContain('not a valid http(s) address');
});

test('a response that is not an image is refused', function () {
    Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

    expect(fetchError(fetcherResolving(['93.184.216.34']), 'https://cdn.example.test/page'))->toContain('not a JPG, PNG or WebP');
});

test('an image larger than the cap is refused', function () {
    config(['product.image_fetch_max_kb' => 5120]);
    Http::fake(['*' => Http::response(str_repeat('x', 5 * 1024 * 1024 + 10), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(fetchError(fetcherResolving(['93.184.216.34']), 'https://cdn.example.test/big.jpg'))->toContain('larger than allowed');
});

test('an image below the minimum size is refused', function () {
    Http::fake(['*' => Http::response(jpegBytes(200, 200), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(fetchError(fetcherResolving(['93.184.216.34']), 'https://cdn.example.test/small.jpg'))->toContain('500');
});

test('a redirect to a private address is refused', function () {
    Http::fake(['public.example.test/*' => Http::response('', 302, ['Location' => 'http://10.0.0.8/a.jpg'])]);

    expect(fetchError(fetcherResolving(['93.184.216.34']), 'https://public.example.test/a.jpg'))->toContain('private or blocked');
});
