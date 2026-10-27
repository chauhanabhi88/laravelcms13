<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Theme\Contracts\MegaMenuProvider;

/**
 * Feeds the storefront mega-menu, mobile drawer and footer from the enabled categories
 * marked "show in menu". Theme caches the result per locale (Theme\Support\MegaMenu).
 */
class CatalogMegaMenuProvider implements MegaMenuProvider
{
    public function __construct(protected CategoryTreeService $tree) {}

    public function tree(int $depth = 3): array
    {
        return $this->present($this->tree->tree(max(1, $depth), enabledOnly: true, menuOnly: true));
    }

    /**
     * The storefront listing URL of a category: the Storefront route (M06) once it
     * exists, else its canonical path.
     */
    public static function categoryUrl(string $slug): string
    {
        return Route::has('storefront.category') ? frontRoute('storefront.category', [$slug]) : url('c/'.$slug);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<int, array{id: int, name: string, url: string, image: ?string, children: array<int, mixed>}>
     */
    protected function present(array $nodes): array
    {
        return array_map(fn (array $node): array => [
            'id' => $node['id'],
            'name' => $node['name'],
            'url' => self::categoryUrl($node['slug']),
            'image' => $node['image'] ? getImageUrl(['module' => config('catalog.upload.category'), 'image' => $node['image']]) : null,
            'children' => $this->present($node['children']),
        ], $nodes);
    }
}
