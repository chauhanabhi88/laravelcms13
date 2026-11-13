<?php

declare(strict_types=1);

namespace Modules\Storefront\Support\JsonLd;

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Services\Search\ListingResult;
use Modules\Storefront\Support\ListingPage;
use Modules\Theme\Support\ThemeSettings;

/**
 * schema.org data for the home page and listings (plan M21 E4): Organization and WebSite with
 * a SearchAction on the home page; BreadcrumbList and ItemList on listings. Product pages use
 * ProductJsonLd. Print with ProductJsonLd::encode().
 */
final class SiteJsonLd
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) config('app.name'),
            'url' => frontRoute('homepage'),
            'logo' => ThemeSettings::logoUrl(),
            'sameAs' => array_values(ThemeSettings::socialLinks()),
        ];

        if ($data['logo'] === null) {
            unset($data['logo']);
        }

        $contact = array_filter([
            'telephone' => ThemeSettings::supportPhone(),
            'email' => ThemeSettings::supportEmail(),
        ]);

        if ($contact !== []) {
            $data['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'areaServed' => 'IN'] + $contact;
        }

        return array_filter($data, fn ($value): bool => $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) config('app.name'),
            'url' => frontRoute('homepage'),
        ];

        if (Route::has('storefront.search')) {
            $data['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => frontRoute('storefront.search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $data;
    }

    /**
     * Home > ancestors > page, from ListingPage::$breadcrumbs (label => url, null for the page).
     *
     * @return array<string, mixed>|null
     */
    public static function breadcrumbs(ListingPage $page, string $canonical): ?array
    {
        if ($page->breadcrumbs === []) {
            return null;
        }

        $items = [['name' => trans('theme::frontend.common.home'), 'url' => frontRoute('homepage')]];
        foreach ($page->breadcrumbs as $label => $url) {
            $items[] = ['name' => (string) $label, 'url' => $url ?? $canonical];
        }

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
     * The products on this listing page, in display order.
     *
     * @return array<string, mixed>|null
     */
    public static function itemList(ListingResult $result): ?array
    {
        $items = array_values(array_filter($result->cards, fn (array $card): bool => isset($card['url'])));

        if ($items === []) {
            return null;
        }

        $offset = $result->firstItem() - 1;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn (array $card, int $index): array => array_filter([
                '@type' => 'ListItem',
                'position' => $offset + $index + 1,
                'url' => $card['url'],
                'name' => $card['name'] ?? null,
            ]), $items, array_keys($items)),
        ];
    }
}
