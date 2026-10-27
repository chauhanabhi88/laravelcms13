<?php

declare(strict_types=1);

namespace Modules\Storefront\Support\JsonLd;

use Illuminate\Support\Str;
use Modules\Storefront\ViewModels\ProductPageViewModel;

/**
 * schema.org structured data for the product page (FR-PDP-010): a Product with an
 * AggregateOffer over its enabled variants (and an AggregateRating once it has ratings), and
 * the category BreadcrumbList.
 */
class ProductJsonLd
{
    /**
     * @return array<string, mixed>
     */
    public static function product(ProductPageViewModel $page): array
    {
        $variant = $page->priceVariant();
        $seller = $page->sellerCard();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $page->name(),
            'url' => $page->canonicalUrl(),
            'image' => $page->allImageUrls(),
            'description' => Str::limit(trim(strip_tags($page->description() ?? implode('. ', $page->highlights()))), 5000, ''),
            'sku' => $variant?->sku ?? (string) $page->product->id,
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'INR',
                'lowPrice' => $page->lowestPrice()->toDecimal(),
                'highPrice' => $page->highestPrice()->toDecimal(),
                'offerCount' => max(1, count($page->variants)),
                'availability' => $page->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $page->canonicalUrl(),
            ],
        ];

        if ($page->brand !== null) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $page->brand['name']];
        }

        if ($seller !== null) {
            $data['offers']['seller'] = ['@type' => 'Organization', 'name' => $seller['name']];
        }

        if ($page->reviews->hasRatings()) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($page->reviews->average, 1),
                'reviewCount' => $page->reviews->count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return array_filter($data, fn ($value): bool => $value !== '' && $value !== []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function breadcrumbs(ProductPageViewModel $page): ?array
    {
        if ($page->categoryTrail === []) {
            return null;
        }

        $items = [['name' => trans('theme::frontend.common.home'), 'url' => frontRoute('homepage')]];
        foreach ($page->categoryTrail as $category) {
            $items[] = ['name' => $category['name'], 'url' => $category['url']];
        }
        $items[] = ['name' => $page->name(), 'url' => $page->canonicalUrl()];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * JSON safe to print inside <script type="application/ld+json">.
     *
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
