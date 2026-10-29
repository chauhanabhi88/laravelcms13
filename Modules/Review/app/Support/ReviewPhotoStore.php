<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Review\Exceptions\ReviewException;

/**
 * Review photos on the public disk (NFR-SEC-07): decoded and encoded again as JPEG (drops
 * EXIF data such as the GPS position, and anything appended to the file), scaled down to
 * the configured longest side, plus a thumbnail, under random names.
 *
 * Decoding happens in prepare(), before anything is written, so a bad file fails the whole
 * submission without leaving files or rows behind.
 */
class ReviewPhotoStore
{
    /**
     * @return array{image: string, thumb: string, width: int, height: int}
     *
     * @throws ReviewException when the file is not a readable image
     */
    public function prepare(UploadedFile $file): array
    {
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath((string) $file->getRealPath());
            $max = max(200, (int) config('review.uploads.max_dimension', 1600));

            if ($image->width() > $max || $image->height() > $max) {
                $image = $image->scaleDown($max, $max);
            }

            $bytes = $image->encodeUsingMediaType('image/jpeg', quality: 85)->toString();
            [$width, $height] = [$image->width(), $image->height()];

            $thumbSize = max(80, (int) config('review.uploads.thumb_dimension', 320));
            $thumb = $manager->decodeBinary($bytes)->cover($thumbSize, $thumbSize)->encodeUsingMediaType('image/jpeg', quality: 80)->toString();
        } catch (\Throwable) {
            throw new ReviewException(trans('review::front.errors.bad_image'), 'bad_image', ['photos' => [trans('review::front.errors.bad_image')]]);
        }

        return ['image' => $bytes, 'thumb' => $thumb, 'width' => $width, 'height' => $height];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{image: string, thumb: string, width: int, height: int}>
     */
    public function prepareMany(array $files): array
    {
        return array_map(fn (UploadedFile $file): array => $this->prepare($file), $files);
    }

    /**
     * Write a prepared photo of the review.
     *
     * @param  array{image: string, thumb: string, width: int, height: int}  $prepared
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function put(array $prepared, int $reviewId): array
    {
        $folder = $this->folder($reviewId);
        $name = Str::random(40);
        $path = $folder.'/'.$name.'.jpg';
        $thumbPath = $folder.'/thumbs/'.$name.'.jpg';

        $this->disk()->put($path, $prepared['image']);
        $this->disk()->put($thumbPath, $prepared['thumb']);

        return ['path' => $path, 'thumb_path' => $thumbPath, 'width' => $prepared['width'], 'height' => $prepared['height']];
    }

    public function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths, fn (?string $path): bool => $path !== null && $path !== ''));

        if ($paths !== []) {
            $this->disk()->delete($paths);
        }
    }

    public function deleteFolder(int $reviewId): void
    {
        $this->disk()->deleteDirectory($this->folder($reviewId));
    }

    /**
     * Public URL of a stored photo, built like getImageUrl() (the request's host), so it works
     * whatever APP_URL says.
     */
    public static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : URL::to('/').'/storage/'.ltrim($path, '/');
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('review.uploads.disk', 'public'));
    }

    protected function folder(int $reviewId): string
    {
        return trim((string) config('review.uploads.folder', 'reviews'), '/').'/'.$reviewId;
    }
}
