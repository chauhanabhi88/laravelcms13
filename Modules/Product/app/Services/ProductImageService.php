<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Modules\Product\DTO\ImageData;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

/**
 * Product image files: upload checks (type, size, minimum pixels), storage on the
 * configured disk, resized copies, the duplicate-detection hash, and removal of files
 * no product or pending change refers to any more.
 */
class ProductImageService
{
    /**
     * Store an uploaded image and describe it for ProductData.
     *
     * @throws ProductValidationException
     */
    public function store(UploadedFile $file, ?int $attributeOptionId = null, ?string $alt = null, string $field = 'image'): ImageData
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowed = explode(',', (string) config('product.upload.image_types'));

        if (! $file->isValid() || ! in_array($extension, $allowed, true) || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw new ProductValidationException([$field => trans('product::product.validation.image_type', ['types' => implode(', ', $allowed)])]);
        }

        if ($file->getSize() > (int) config('product.upload.max_size_kb') * 1024) {
            throw new ProductValidationException([$field => trans('product::product.validation.image_size', ['kb' => config('product.upload.max_size_kb')])]);
        }

        $dimensions = @getimagesize((string) $file->getRealPath()) ?: [0, 0];
        $minimum = (int) config('product.min_image_px');

        if ($dimensions[0] < $minimum || $dimensions[1] < $minimum) {
            throw new ProductValidationException([$field => trans('product::product.validation.image_pixels', ['px' => $minimum])]);
        }

        $name = Str::random(40).'.'.$extension;
        $encoded = $this->reencode((string) $file->getRealPath(), $extension);

        if ($encoded !== null) {
            Storage::disk(getStorageDisk())->put($this->folder().'/'.$name, $encoded);
        } else {
            Storage::disk(getStorageDisk())->putFileAs($this->folder(), $file, $name);
        }

        $this->makeResizedCopies($name);

        return new ImageData(
            path: $name,
            attributeOptionId: $attributeOptionId,
            alt: $alt,
            width: (int) $dimensions[0],
            height: (int) $dimensions[1],
            contentHash: $this->contentHash((string) $file->getRealPath()),
        );
    }

    /**
     * md5 of a 64×64 greyscale PNG of the image: survives re-encoding and small resizes,
     * so the same photo uploaded by two sellers hashes alike. Null when unreadable.
     */
    public function contentHash(string $path): ?string
    {
        try {
            $image = (new ImageManager(new Driver))->decodePath($path);
            $image->resize(64, 64)->grayscale();

            return md5($image->encodeUsingFormat(Format::PNG)->toString());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Delete stored files (and their resized copies) that no product image row and no
     * pending change references. Call after the transaction that dropped them commits.
     *
     * @param  list<string>  $paths
     */
    public function deleteUnreferenced(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($path): bool => is_string($path) && $path !== '' && basename($path) === $path)));

        if ($paths === []) {
            return;
        }

        $inUse = ProductImage::query()->whereIn('path', $paths)->pluck('path')->all();

        foreach (array_diff($paths, $inUse) as $path) {
            $pending = Product::query()->withTrashed()
                ->where('has_pending_changes', config('core.yes'))
                ->where('pending_changes', 'like', '%'.addcslashes($path, '%_\\').'%')
                ->exists();

            if ($pending) {
                continue;
            }

            $disk = Storage::disk(getStorageDisk());
            $disk->delete($this->folder().'/'.$path);

            foreach (array_keys((array) config('product.image_sizes')) as $size) {
                $disk->delete($this->folder().'/'.$size.'/'.$path);
            }
        }
    }

    public function folder(): string
    {
        return (string) config('product.upload.folder', 'product');
    }

    /**
     * Decode and encode the image again in its own format (NFR-SEC-07): drops EXIF data and
     * anything appended to the file. Null when the image cannot be re-encoded, in which case
     * the original (already type- and size-checked) is stored.
     */
    protected function reencode(string $path, string $extension): ?string
    {
        $format = match ($extension) {
            'jpg', 'jpeg' => Format::JPEG,
            'png' => Format::PNG,
            'webp' => Format::WEBP,
            default => null,
        };

        if ($format === null) {
            return null;
        }

        try {
            $image = (new ImageManager(new Driver))->decodePath($path);

            return $format === Format::PNG
                ? $image->encodeUsingFormat($format)->toString()
                : $image->encodeUsingFormat($format, quality: 88)->toString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Card / PDP / zoom / thumbnail copies next to the original (local public disk only;
     * imageResize() works on public/storage).
     */
    protected function makeResizedCopies(string $name): void
    {
        if (getStorageDisk() !== 'public' || ! File::exists(public_path('storage/'.$this->folder().'/'.$name))) {
            return;
        }

        foreach ((array) config('product.image_sizes') as $size => [$width, $height]) {
            imageResize($name, $this->folder(), $width, $height, true, true, false, null, null, $size);
        }
    }
}
