<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Support\DuplicateDetector;
use Modules\Seller\Models\Seller;

/**
 * A product whose primary image carries $hash.
 */
function productWithImage(string $name, string $hash, string $state = 'live', ?Seller $seller = null): Product
{
    $factory = Product::factory()->{$state}()->withVariants();
    $product = ($seller ? $factory->forSeller($seller) : $factory)->create(['name' => $name]);
    ProductImage::query()->where('product_id', $product->id)->update(['content_hash' => $hash]);

    return $product->fresh()->load('translations');
}

/**
 * A 600×600 PNG gradient (deterministic pixels) at $path.
 */
function gradientPng(string $path, int $shift = 0): string
{
    $image = imagecreatetruecolor(600, 600);

    for ($x = 0; $x < 600; $x += 20) {
        $colour = imagecolorallocate($image, ($x + $shift) % 256, 120, 255 - ($x % 256));
        imagefilledrectangle($image, $x, 0, $x + 19, 599, $colour);
    }

    imagepng($image, $path);

    return $path;
}

test('another seller\'s live product with the same image and a similar name is flagged', function () {
    $hash = md5('same-photo');
    $original = productWithImage('Pure Cotton Printed Kurti', $hash);
    $copy = productWithImage('Pure Cotton Printed Kurti Blue', $hash, 'pendingQc');
    $renamed = productWithImage('Steel Water Bottle 1 Litre', $hash, 'pendingQc');
    $sameSeller = productWithImage('Pure Cotton Printed Kurti', $hash, 'pendingQc', Seller::query()->find($original->seller_id));
    $differentPhoto = productWithImage('Pure Cotton Printed Kurti', md5('other-photo'), 'pendingQc');

    $flags = app(DuplicateDetector::class)->forProducts(collect([$copy, $renamed, $sameSeller, $differentPhoto]));

    expect($flags)->toBe([$copy->id => [$original->id]]);
});

test('the content hash is stable for an image and tells different images apart', function () {
    $service = app(ProductImageService::class);
    $directory = storage_path('framework/testing');
    @mkdir($directory, 0777, true);

    $original = gradientPng($directory.'/hash-a.png');
    $sameAgain = gradientPng($directory.'/hash-a-copy.png');
    $different = gradientPng($directory.'/hash-b.png', 90);

    $hash = $service->contentHash($original);

    expect($hash)->toMatch('/^[0-9a-f]{32}$/')
        ->and($service->contentHash($sameAgain))->toBe($hash)
        ->and($service->contentHash($different))->not->toBe($hash)
        ->and($service->contentHash($directory.'/missing.png'))->toBeNull();

    @unlink($original);
    @unlink($sameAgain);
    @unlink($different);
});

test('uploads are checked for type and minimum size and stored with their hash', function () {
    Storage::fake('public');
    $service = app(ProductImageService::class);

    expect(fn () => $service->store(UploadedFile::fake()->image('small.jpg', 300, 300)))->toThrow(ProductValidationException::class)
        ->and(fn () => $service->store(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')))->toThrow(ProductValidationException::class);

    $image = $service->store(UploadedFile::fake()->image('kurti.jpg', 600, 800), null, 'Front view');

    expect($image)
        ->width->toBe(600)
        ->height->toBe(800)
        ->alt->toBe('Front view')
        ->contentHash->toMatch('/^[0-9a-f]{32}$/');

    Storage::disk('public')->assertExists('product/'.$image->path);

    $service->deleteUnreferenced([$image->path]);
    Storage::disk('public')->assertMissing('product/'.$image->path);
});
