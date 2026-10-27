<?php

return [
    'name' => 'Storefront',

    /*
    | Entity names: cache buckets (BaseCacheDecorator) and session filter keys of the admin grids.
    */
    'cache' => [
        'home_section' => 'HomeSection',
        'product_collection' => 'ProductCollection',
    ],

    /*
    | Collection banners live in storage/app/public/<folder>.
    */
    'upload' => [
        'collection' => 'product_collection',
        'image_types' => 'jpg,jpeg,png,webp',
        'max_size_kb' => 4096,
    ],

    /*
    | Cards per listing page (first page and every infinite-scroll page).
    */
    'per_page' => 40,

    /*
    | Highest ?page= served; deeper pages are a 404 (crawler traps, offset cost).
    */
    'max_page' => 250,

    /*
    | Cache lifetimes in seconds: total counts and facets per query, the chosen full-text
    | strategy per term, home page section fragments and autocomplete answers per prefix.
    */
    'count_cache_ttl' => 60,
    'facet_cache_ttl' => 120,
    'text_mode_cache_ttl' => 300,
    'home_cache_ttl' => 300,
    'autocomplete_cache_ttl' => 300,

    /*
    | Shortest word the InnoDB full-text index holds (MySQL innodb_ft_min_token_size). Shorter
    | words are matched with LIKE on the product name.
    */
    'fulltext_min_token_size' => (int) env('STOREFRONT_FT_MIN_TOKEN_SIZE', 3),

    /*
    | Sort options in display order. "relevance" falls back to popularity without a query.
    */
    'sorts' => ['relevance', 'new', 'price_asc', 'price_desc', 'rating', 'discount'],

    /*
    | The "rating" sort ranks products with at least this many ratings first.
    */
    'rating_sort_min_count' => 5,

    /*
    | Filter presets. Prices are rupees: [from, to] with null = open ended.
    */
    'price_presets' => [
        [null, 199],
        [199, 499],
        [499, 999],
        [999, null],
    ],
    'rating_buckets' => [4, 3],
    'discount_buckets' => [10, 30, 50, 70],

    /*
    | Facet sizes: brands listed (selected ones always show), attributes offered on a search
    | without a category, and options shown before "Show more" / the option search box.
    */
    'facet_brand_limit' => 20,
    'facet_search_attribute_limit' => 6,
    'facet_option_search_from' => 10,

    /*
    | Autocomplete: minimum characters and the size of each suggestion group.
    */
    'autocomplete_min_length' => 2,
    'autocomplete_limits' => [
        'categories' => 4,
        'brands' => 3,
        'products' => 5,
        'trending' => 4,
        'trending_empty' => 8,
    ],

    /*
    | Search analytics: logs are kept this many days; trending reads this many stat rows.
    | "Did you mean" suggests a trending term within this edit distance.
    */
    'search_log_retention_days' => 90,
    'trending_pool' => 500,
    'did_you_mean_distance' => 2,
    'zero_result_categories' => 8,

    /*
    | Recently viewed: rows kept per customer, ids accepted from a guest's browser and the
    | minimum number of products before the home page shows the row.
    */
    'recent_views_keep' => 50,
    'recent_views_guest_limit' => 20,
    'recent_views_min' => 3,

    /*
    | "Top Seller" card badge: seller rating and number of ratings at or above these values.
    */
    'top_seller' => [
        'min_rating' => '4.3',
        'min_ratings' => 100,
    ],

    /*
    | Home page section types (admin > Storefront > Home Sections). blog_posts keeps the CMS
    | home's featured articles available as a section.
    */
    'home_section_types' => [
        'hero',
        'trust_strip',
        'category_tiles',
        'banner_row',
        'collection',
        'products_for_you',
        'html_block',
        'blog_posts',
    ],

    'home_defaults' => [
        'category_tiles_limit' => 12,
        'collection_limit' => 12,
        'blog_posts_limit' => 3,
    ],

    /*
    | Fallbacks for admin > Settings > Storefront, read through Modules\Storefront\Support\StorefrontSettings.
    */
    'defaults' => [
        'show_out_of_stock' => 1,
        'trending_terms_manual' => '',
        'free_delivery_badge' => 1,
    ],
];
