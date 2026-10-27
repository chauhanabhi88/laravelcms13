<?php

return [
    'titles' => [
        'storefront' => 'Storefront',
    ],

    'settings' => [
        'storefront' => 'Storefront',
        'show_out_of_stock' => [
            'label' => 'Show out-of-stock products',
            'comment' => 'Keep sold-out products in listings and search (always sorted after available ones).',
        ],
        'free_delivery_badge' => [
            'label' => 'Show "Free Delivery" on product cards',
            'comment' => 'Turn off when delivery is charged on some orders.',
        ],
        'trending_terms_manual' => [
            'label' => 'Pinned trending searches',
            'comment' => 'Comma separated. Shown first in the search box suggestions, before the most searched terms.',
        ],
    ],

    'home' => [
        'picked_for_you' => 'Picked for You',
        'shop_by_category' => 'Shop by Category',
        'recently_viewed' => 'Recently viewed',
        'view_all' => 'View all',
        'from_blog' => 'From the Blog',
        'all_articles' => 'All articles',
        'meta_description' => 'Shop everyday essentials, fashion and more at honest prices, with Pay on Delivery and easy returns.',
    ],

    'listing' => [
        'results' => '{0} No products|{1} 1 product|[2,*] :count products',
        'showing' => 'Showing :from–:to of :total',
        'showing_open' => 'Showing :from–:to',
        'sort_by' => 'Sort by',
        'sort' => 'Sort',
        'filter' => 'Filter',
        'filters' => 'Filters',
        'clear_all' => 'Clear all',
        'apply' => 'Apply',
        'apply_count' => '{0} No products|{1} Show 1 product|[2,*] Show :count products',
        'applied_filters' => 'Applied filters',
        'remove_filter' => 'Remove filter :name',
        'next_page' => 'Next page',
        'previous_page' => 'Previous page',
        'load_more' => 'Load more',
        'loading' => 'Loading more products…',
        'load_failed' => 'Could not load more products.',
        'end_of_results' => 'You have seen all products.',
        'back_to_top' => 'Back to top',
        'empty_title' => 'No products here yet',
        'empty_text' => 'Try removing a filter or browsing another category.',
        'out_of_stock' => 'Out of stock',
        'sorts' => [
            'relevance' => 'Relevance',
            'popularity' => 'Popularity',
            'new' => 'New Arrivals',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'rating' => 'Customer Rating',
            'discount' => 'Biggest Discount',
        ],
    ],

    'filters' => [
        'category' => 'Category',
        'all_categories' => 'All categories',
        'back_to' => 'Back to :name',
        'price' => 'Price',
        'price_under' => 'Under :to',
        'price_over' => ':from and above',
        'price_between' => ':from – :to',
        'price_apply' => 'Apply price',
        'rating' => 'Customer Rating',
        'rating_value' => ':value★ & above',
        'discount' => 'Discount',
        'discount_value' => ':value% off or more',
        'brand' => 'Brand',
        'cod' => 'Pay on Delivery',
        'cod_label' => 'Pay on Delivery available',
        'search_options' => 'Search :name',
        'show_more' => 'Show :count more',
        'show_less' => 'Show less',
    ],

    'search' => [
        'breadcrumb' => 'Search',
        'results_for' => 'Results for “:term”',
        'all_products' => 'All products',
        'no_results' => 'No results for “:term”',
        'no_results_text' => 'Check the spelling, try a more general word, or explore a category below.',
        'did_you_mean' => 'Did you mean',
        'popular_categories' => 'Popular categories',
        'browse_all' => 'Browse all products',
    ],

    'autocomplete' => [
        'categories' => 'Categories',
        'brands' => 'Brands',
        'products' => 'Products',
        'trending' => 'Trending searches',
        'recent' => 'Recent searches',
        'clear_recent' => 'Clear',
        'brand' => 'Brand',
        'in_category' => 'in :name',
    ],

    'card' => [
        'top_seller' => 'Top Seller',
        'verified_brand' => 'Verified Brand',
    ],

    'store' => [
        'products' => '{0} No products|{1} 1 product|[2,*] :count products',
        'follow' => 'Follow',
        'follow_soon' => 'Following stores is coming soon.',
    ],

    'collection' => [
        'breadcrumb' => 'Collections',
    ],
];
