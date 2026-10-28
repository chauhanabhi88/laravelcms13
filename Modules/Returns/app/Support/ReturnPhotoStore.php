<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Returns\Exceptions\ReturnException;

/**
 * Return and claim photos on the private disk (NFR-SEC-07): decoded and encoded again as JPEG
 * (drops EXIF data and anything appended to the file), scaled down to the configured longest
 * side, random file names. Customer drafts live under drafts/ until the return is created.
 */
class ReturnPhotoStore
{
    /**
     * @return array{path: string, width: int|null, height: int|null}
     *
     * @throws ReturnException when the file is not a readable image
     */
    public function store(UploadedFile $file, string $folder): array
    {
        try {
            $image = (new ImageManager(new Driver))->decodePath((string) $file->getRealPath());
            $max = max(200, (int) config('returns.uploads.max_dimension', 2000));

            if ($image->width() > $max || $image->height() > $max) {
                $image = $image->scaleDown($max, $max);
            }

            $bytes = $image->encodeUsingMediaType('image/jpeg', quality: 85)->toString();
            [$width, $height] = [$image->width(), $image->height()];
        } catch (\Throwable) {
            throw new ReturnException(trans('returns::front.errors.bad_image'), 'bad_image', ['photos' => [trans('returns::front.errors.bad_image')]]);
        }

        $path = trim($this->root().'/'.trim($folder, '/'), '/').'/'.Str::random(40).'.jpg';
        $this->disk()->put($path, $bytes);

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, width: int|null, height: int|null}>
     */
    public function storeMany(array $files, string $folder): array
    {
        return array_map(fn (UploadedFile $file): array => $this->store($file, $folder), $files);
    }

    /**
     * Move a draft photo next to its return; returns the new path (the old one when missing).
     */
    public function moveTo(string $path, string $folder): string
    {
        $target = trim($this->root().'/'.trim($folder, '/'), '/').'/'.basename($path);

        if ($path === $target || ! $this->disk()->exists($path)) {
            return $path;
        }

        $this->disk()->move($path, $target);

        return $target;
    }

    /**
     * Whether $path is one of this customer's draft photos (never trust paths from a form).
     */
    public function isDraftOf(string $path, int $customerId): bool
    {
        return str_starts_with($path, $this->draftFolder($customerId).'/') && ! str_contains($path, '..') && $this->disk()->exists($path);
    }

    public function draftFolder(int $customerId): string
    {
        return trim($this->root().'/drafts/'.$customerId, '/');
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('returns.uploads.disk', 'private'));
    }

    protected function root(): string
    {
        return trim((string) config('returns.uploads.folder', 'returns'), '/');
    }
}
