<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

/**
 * The context of one listing page (category, search, collection, store or the home "Picked
 * for You" grid): heading and SEO data, the page URL without query string, the fixed
 * ListingQuery values and extra view data.
 */
final readonly class ListingPage
{
    public const CATEGORY = 'category';

    public const SEARCH = 'search';

    public const COLLECTION = 'collection';

    public const STORE = 'store';

    public const HOME = 'home';

    /**
     * @param  array<string, mixed>  $fixed  ListingQuery values the shopper cannot change
     * @param  array<string, string|null>  $breadcrumbs  label => url (the last item is the page)
     * @param  array<string, mixed>  $extra  extra view data
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $baseUrl,
        public array $fixed = [],
        public array $breadcrumbs = [],
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $image = null,
        public array $extra = [],
    ) {}

    public function isSearch(): bool
    {
        return $this->kind === self::SEARCH;
    }

    /**
     * Child categories link to their own listing page (not to a filter) on category pages.
     */
    public function linksCategoriesToPages(): bool
    {
        return $this->kind === self::CATEGORY;
    }
}
