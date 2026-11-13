<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Seo;

use Carbon\CarbonInterface;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Enums\ProductStatus;
use Modules\Seller\Enums\SellerStatus;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Writes the XML sitemaps (plan M21 E1, NFR-SEO-01): an index plus chunks of up to
 * config('storefront.seo.sitemap_chunk') URLs per section - home + CMS pages, categories,
 * live products, active stores and collections.
 *
 * Files live on the local disk under config('storefront.seo.sitemap_path') and are served
 * by SitemapController. Chunks from an earlier, larger run are deleted.
 */
class SitemapGenerator
{
    public const INDEX = 'sitemap.xml';

    /**
     * @return array<string, int> section => URL count
     */
    public function generate(): array
    {
        $disk = self::disk();
        $directory = self::directory();
        $chunkSize = max(1, (int) config('storefront.seo.sitemap_chunk', 50000));
        $written = [];
        $counts = [];

        foreach ($this->sections() as $section => $entries) {
            $counts[$section] = 0;
            $buffer = [];
            $part = 1;

            foreach ($entries as $entry) {
                $buffer[] = $entry;
                $counts[$section]++;

                if (count($buffer) === $chunkSize) {
                    $written[] = $this->writeChunk($disk, $directory, $section, $part++, $buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $written[] = $this->writeChunk($disk, $directory, $section, $part, $buffer);
            }
        }

        $disk->put($directory.'/'.self::INDEX, $this->indexXml($written));

        foreach ($disk->files($directory) as $path) {
            $name = basename($path);

            if ($name !== self::INDEX && ! in_array($name, array_column($written, 'name'), true)) {
                $disk->delete($path);
            }
        }

        return $counts;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public static function directory(): string
    {
        return trim((string) config('storefront.seo.sitemap_path', 'sitemaps'), '/');
    }

    /**
     * Only names the generator writes may be served.
     */
    public static function isSitemapFile(string $name): bool
    {
        return $name === self::INDEX || preg_match('/^sitemap-[a-z]+-\d+\.xml$/', $name) === 1;
    }

    /**
     * @return array<string, iterable<array{loc: string, lastmod: ?CarbonInterface}>>
     */
    protected function sections(): array
    {
        return [
            'pages' => $this->pages(),
            'categories' => $this->categories(),
            'products' => $this->products(),
            'stores' => $this->stores(),
            'collections' => $this->collections(),
        ];
    }

    /**
     * @return Generator<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function pages(): Generator
    {
        yield ['loc' => frontRoute('homepage'), 'lastmod' => null];

        if (Route::has('seller.landing')) {
            yield ['loc' => frontRoute('seller.landing'), 'lastmod' => null];
        }

        if (! Route::has('page')) {
            return;
        }

        foreach (DB::table('pages')->where('status', config('core.enabled'))->whereNull('deleted_at')->orderBy('id')->cursor() as $page) {
            yield ['loc' => frontRoute('page', [$page->slug]), 'lastmod' => $this->date($page->updated_at)];
        }
    }

    /**
     * Enabled categories whose ancestors are all enabled (the PLP 404s otherwise).
     *
     * @return Generator<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function categories(): Generator
    {
        $categories = DB::table('catalog_category')->whereNull('deleted_at')
            ->orderBy('depth')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'parent_id', 'slug', 'status', 'updated_at'])
            ->keyBy('id');

        $enabled = (int) config('core.enabled');
        $visible = function (object $category) use ($categories, $enabled): bool {
            for ($node = $category; $node !== null; $node = $node->parent_id ? $categories->get($node->parent_id) : null) {
                if ((int) $node->status !== $enabled) {
                    return false;
                }
            }

            return true;
        };

        foreach ($categories as $category) {
            if ($visible($category)) {
                yield ['loc' => StorefrontUrls::category((string) $category->slug), 'lastmod' => $this->date($category->updated_at)];
            }
        }
    }

    /**
     * Live products of active sellers.
     *
     * @return Generator<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function products(): Generator
    {
        $query = DB::table('product')
            ->where('status', ProductStatus::Live->value)
            ->where('seller_active', config('core.yes'))
            ->whereNull('deleted_at')
            ->whereNotNull('slug')
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at']);

        foreach ($query->lazyById(2000) as $product) {
            yield ['loc' => StorefrontUrls::product((string) $product->slug, (int) $product->id), 'lastmod' => $this->date($product->updated_at)];
        }
    }

    /**
     * @return Generator<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function stores(): Generator
    {
        $query = DB::table('seller')
            ->where('status', SellerStatus::Active->value)
            ->whereNull('deleted_at')
            ->whereNotNull('store_slug')
            ->orderBy('id')
            ->select(['id', 'store_slug', 'updated_at']);

        foreach ($query->lazyById(2000) as $seller) {
            yield ['loc' => StorefrontUrls::store((string) $seller->store_slug), 'lastmod' => $this->date($seller->updated_at)];
        }
    }

    /**
     * @return Generator<int, array{loc: string, lastmod: ?CarbonInterface}>
     */
    protected function collections(): Generator
    {
        foreach (DB::table('product_collection')->where('status', config('core.enabled'))->orderBy('id')->get(['slug', 'updated_at']) as $collection) {
            yield ['loc' => StorefrontUrls::collection((string) $collection->slug), 'lastmod' => $this->date($collection->updated_at)];
        }
    }

    /**
     * @param  list<array{loc: string, lastmod: ?CarbonInterface}>  $entries
     * @return array{name: string, lastmod: CarbonInterface}
     */
    protected function writeChunk(Filesystem $disk, string $directory, string $section, int $part, array $entries): array
    {
        $name = "sitemap-{$section}-{$part}.xml";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= '  <url><loc>'.$this->escape($entry['loc']).'</loc>'
                .($entry['lastmod'] ? '<lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>' : '')
                .'</url>'."\n";
        }

        $disk->put($directory.'/'.$name, $xml.'</urlset>'."\n");

        return ['name' => $name, 'lastmod' => now()];
    }

    /**
     * @param  list<array{name: string, lastmod: CarbonInterface}>  $files
     */
    protected function indexXml(array $files): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($files as $file) {
            $xml .= '  <sitemap><loc>'.$this->escape(route('storefront.sitemap.file', [$file['name']])).'</loc>'
                .'<lastmod>'.$file['lastmod']->toAtomString().'</lastmod></sitemap>'."\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function date(mixed $value): ?CarbonInterface
    {
        return $value ? Carbon::parse($value) : null;
    }
}
