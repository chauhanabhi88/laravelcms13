<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Storefront\Services\ListingRenderer;
use Modules\Storefront\Support\ListingPage;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Category listing /c/{slug} (FR-DSC-002): products of the category and its descendants.
 * Disabled categories, or ones below a disabled ancestor, are a 404.
 */
class CategoryController extends Controller
{
    public function __construct(
        protected CatalogCategoryRepository $categories,
        protected CategoryTreeService $tree,
        protected ListingRenderer $listings,
    ) {}

    public function show(Request $request): View|JsonResponse
    {
        $category = $this->categories->findEnabledBySlug((string) $request->route('slug'));
        $ancestors = $category ? $this->tree->ancestors($category) : [];
        $enabled = (int) config('core.enabled');

        abort_if($category === null || collect($ancestors)->contains(fn (array $node): bool => $node['status'] !== $enabled), 404);

        $breadcrumbs = [];
        foreach ($ancestors as $ancestor) {
            $breadcrumbs[$ancestor['name']] = StorefrontUrls::category($ancestor['slug']);
        }
        $breadcrumbs[(string) $category->name] = null;

        $page = new ListingPage(
            kind: ListingPage::CATEGORY,
            title: (string) $category->name,
            baseUrl: StorefrontUrls::category($category->slug),
            fixed: ['categoryId' => $category->id, 'categoryPath' => $category->path],
            breadcrumbs: $breadcrumbs,
            metaTitle: $category->meta_title ?: null,
            metaDescription: $category->meta_description ?: $category->description,
            image: $category->banner_image ? getImageUrl(['module' => config('catalog.upload.category'), 'image' => $category->banner_image]) : null,
        );

        return $this->listings->render($request, $page);
    }
}
