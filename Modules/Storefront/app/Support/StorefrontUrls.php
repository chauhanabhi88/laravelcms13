<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Support\CatalogMegaMenuProvider;

/**
 * Public storefront URLs. The product page belongs to M07: until its route exists, product
 * links use the canonical /p/{slug}-{id} path.
 */
class StorefrontUrls
{
    public static function product(string $slug, int $id): string
    {
        return Route::has('storefront.product.show')
            ? frontRoute('storefront.product.show', [$slug.'-'.$id])
            : url('p/'.$slug.'-'.$id);
    }

    public static function category(string $slug): string
    {
        return CatalogMegaMenuProvider::categoryUrl($slug);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function search(string $term = '', array $params = []): string
    {
        $params = array_filter(['q' => $term] + $params, fn ($value): bool => $value !== null && $value !== '');

        return frontRoute('storefront.search').($params !== [] ? '?'.http_build_query($params) : '');
    }

    public static function collection(string $slug): string
    {
        return frontRoute('storefront.collection', [$slug]);
    }

    public static function store(string $slug): string
    {
        return frontRoute('storefront.store.show', [$slug]);
    }

    /**
     * A product image at card size (the "card" copy made on upload), else the original.
     */
    public static function productImage(?string $path, string $size = 'card'): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $folder = (string) config('product.upload.folder', 'product');

        return getImageUrl(['module' => $folder.'/'.$size, 'image' => $path])
            ?? getImageUrl(['module' => $folder, 'image' => $path]);
    }
}
