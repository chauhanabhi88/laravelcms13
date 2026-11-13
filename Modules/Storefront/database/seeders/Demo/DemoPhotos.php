<?php

declare(strict_types=1);

namespace Modules\Storefront\Database\Seeders\Demo;

use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo photos: downloads DemoData::PHOTOS once into storage/app/demo-photos (re-runs reuse
 * the files), prepares product shots for the 3:4 cards and composes text-free banners and
 * category tiles from them.
 */
final class DemoPhotos
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

    private string $cacheDir;

    private string $workDir;

    private bool $useSystemCurl = false;

    public function __construct()
    {
        $this->cacheDir = storage_path('app/demo-photos');
        $this->workDir = $this->cacheDir.DIRECTORY_SEPARATOR.'work';
        File::ensureDirectoryExists($this->workDir);
    }

    /**
     * Local copies of a photo key's images, in order.
     *
     * @return list<string>
     */
    public function files(string $key): array
    {
        [$urls] = DemoData::PHOTOS[$key] ?? throw new RuntimeException("Unknown demo photo [{$key}].");

        return array_map(fn (string $url): string => $this->download($url), $urls);
    }

    /**
     * A product shot ready for upload: studio shots on white are padded to 3:4 (the card
     * ratio) so nothing is cropped; other photos are used as they are.
     */
    public function productShot(string $file): string
    {
        $image = $this->open($file);
        [$width, $height] = [imagesx($image), imagesy($image)];

        if ($height / $width >= 1.3 || ! $this->hasWhiteCorners($image)) {
            return $file;
        }

        $targetWidth = max($width, (int) ceil($height * 3 / 4));
        $targetHeight = max($height, (int) ceil($targetWidth * 4 / 3));
        $canvas = $this->canvas($targetWidth, $targetHeight, [255, 255, 255]);
        imagecopy($canvas, $image, intdiv($targetWidth - $width, 2), intdiv($targetHeight - $height, 2), 0, 0, $width, $height);

        return $this->save($canvas, 'shot-'.md5($file));
    }

    /**
     * A square tile (category circles): the photo centre-cropped on a white ground.
     */
    public function tile(string $file, int $size = 600): string
    {
        $image = $this->open($file);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $side = min($width, $height);
        $canvas = $this->canvas($size, $size, [255, 255, 255]);
        imagecopyresampled($canvas, $image, 0, 0, intdiv($width - $side, 2), (int) (($height - $side) * 0.25), $size, $size, $side, $side);

        return $this->save($canvas, 'tile-'.md5($file.$size));
    }

    /**
     * A text-free banner: a soft two-tone gradient with the product photos as rounded cards
     * on the right half (the storefront overlays the title on the left).
     *
     * @param  list<string>  $files
     * @param  array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}  $colours
     */
    public function banner(string $name, array $files, array $colours, int $width = 1800, int $height = 600): string
    {
        $canvas = $this->canvas($width, $height, $colours[0]);

        for ($x = 0; $x < $width; $x++) {
            $mix = $x / $width;
            $line = imagecolorallocate($canvas, ...array_map(fn (int $a, int $b): int => (int) round($a + ($b - $a) * $mix), $colours[0], $colours[1]));
            imageline($canvas, $x, 0, $x, $height, $line);
        }

        $count = count($files);
        $gap = (int) ($height * 0.06);
        $cardHeight = (int) ($height * 0.78);
        $cardWidth = (int) ($cardHeight * 3 / 4);
        $right = $width - (int) ($height * 0.12);
        $top = intdiv($height - $cardHeight, 2);

        foreach (array_reverse($files) as $index => $file) {
            $x = $right - ($index + 1) * $cardWidth - $index * $gap;
            $y = $top + ($index % 2 === 0 ? 0 : (int) ($height * 0.04));
            $this->card($canvas, $this->open($file), $x, $y, $cardWidth, $cardHeight);

            if ($index + 1 >= $count) {
                break;
            }
        }

        return $this->save($canvas, 'banner-'.Str::slug($name));
    }

    /**
     * The source credit of a photo key.
     */
    public function credit(string $key): string
    {
        return DemoData::PHOTOS[$key][1] ?? '';
    }

    private function download(string $url): string
    {
        $path = $this->cacheDir.DIRECTORY_SEPARATOR.sha1($url).'.'.(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg');

        if (is_file($path) && @getimagesize($path) !== false) {
            return $path;
        }

        $url = str_replace(' ', '%20', $url);
        $body = '';

        if (! $this->useSystemCurl) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(60)->get($url);
                $body = $response->successful() ? $response->body() : '';
            } catch (ConnectionException) {
                // PHP without a CA bundle (curl.cainfo unset) cannot verify TLS; the system
                // curl uses the OS certificate store, so it takes over from here.
                $this->useSystemCurl = true;
            }
        }

        if ($this->useSystemCurl) {
            $result = Process::timeout(90)->run(['curl', '-sSL', '--fail', '--retry', '2', '-A', self::USER_AGENT, '-o', $path, $url]);
            $body = $result->successful() && is_file($path) ? (string) file_get_contents($path) : '';
        }

        if ($body === '' || @getimagesizefromstring($body) === false) {
            File::delete($path);

            throw new RuntimeException("Could not download demo photo {$url}.");
        }

        File::put($path, $body);

        return $path;
    }

    private function open(string $file): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Unreadable demo photo {$file}.");
        }

        imagepalettetotruecolor($image);

        return $image;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function canvas(int $width, int $height, array $rgb): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...$rgb));

        return $canvas;
    }

    /**
     * A photo centre-cropped into a white rounded card with a hairline border.
     */
    private function card(GdImage $canvas, GdImage $photo, int $x, int $y, int $width, int $height): void
    {
        $radius = (int) ($width * 0.06);
        $this->roundedRectangle($canvas, $x - 2, $y - 2, $width + 4, $height + 4, $radius + 2, imagecolorallocatealpha($canvas, 255, 255, 255, 70));
        $this->roundedRectangle($canvas, $x, $y, $width, $height, $radius, imagecolorallocate($canvas, 255, 255, 255));

        $inset = (int) ($width * 0.04);
        [$sourceWidth, $sourceHeight] = [imagesx($photo), imagesy($photo)];
        $innerWidth = $width - 2 * $inset;
        $innerHeight = $height - 2 * $inset;
        $scale = max($innerWidth / $sourceWidth, $innerHeight / $sourceHeight);
        $cropWidth = (int) ($innerWidth / $scale);
        $cropHeight = (int) ($innerHeight / $scale);
        imagecopyresampled($canvas, $photo, $x + $inset, $y + $inset, intdiv($sourceWidth - $cropWidth, 2), (int) (($sourceHeight - $cropHeight) * 0.3), $innerWidth, $innerHeight, $cropWidth, $cropHeight);
    }

    private function roundedRectangle(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $colour): void
    {
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $colour);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $colour);

        foreach ([[$x + $radius, $y + $radius], [$x + $width - $radius, $y + $radius], [$x + $radius, $y + $height - $radius], [$x + $width - $radius, $y + $height - $radius]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, 2 * $radius, 2 * $radius, $colour);
        }
    }

    private function hasWhiteCorners(GdImage $image): bool
    {
        [$width, $height] = [imagesx($image), imagesy($image)];

        foreach ([[2, 2], [$width - 3, 2], [2, $height - 3], [$width - 3, $height - 3]] as [$x, $y]) {
            $rgb = imagecolorat($image, $x, $y);

            if ((($rgb >> 16) & 0xFF) < 235 || (($rgb >> 8) & 0xFF) < 235 || ($rgb & 0xFF) < 235) {
                return false;
            }
        }

        return true;
    }

    private function save(GdImage $image, string $name): string
    {
        $path = $this->workDir.DIRECTORY_SEPARATOR.$name.'.jpg';
        imagejpeg($image, $path, 88);

        return $path;
    }
}
