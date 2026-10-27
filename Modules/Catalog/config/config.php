<?php

return [
    'name' => 'Catalog',

    /*
    | Entity names: cache buckets (BaseCacheDecorator) and session filter keys of the admin grids.
    */
    'cache' => [
        'category' => 'CatalogCategory',
        'brand' => 'CatalogBrand',
        'size_chart' => 'CatalogSizeChart',
        'category_attribute' => 'CatalogCategoryAttribute',
    ],

    /*
    | Upload folders under storage/app/public (served from /storage/<folder>).
    */
    'upload' => [
        'category' => 'catalog_category',
        'brand' => 'catalog_brand',
        'size_chart' => 'catalog_size_chart',
        'image_types' => 'jpg,jpeg,png,webp',
        'max_size_kb' => 2048,
    ],

    /*
    | Fallbacks for admin > Settings > Catalog, read through Modules\Catalog\Support\CatalogSettings.
    */
    'defaults' => [
        'default_return_window' => 7,
        'default_dispatch_sla_hours' => 48,
        'default_gst_percent' => 5,
        'max_category_depth' => 4,
        'max_variant_axes' => 2,
        'return_type' => 'return_exchange',
    ],

    'return_types' => ['return_exchange', 'exchange_only', 'none'],

    /*
    | Attribute codes that belong to the admin module generator, not to products: hidden
    | from the category attribute mapping screen.
    */
    'hidden_attribute_codes' => ['data-type', 'database-operation', 'filters', 'input-options'],

    'size_chart_units' => ['cm', 'in'],

    /*
    | Category tree / defaults / attribute resolver cache lifetime (seconds). Every write
    | bumps the catalog cache version (CatalogTreeChanged), so this is only a safety net.
    */
    'cache_ttl' => 86400,

    'api' => [
        'default_depth' => 3,
        'brand_limit' => 50,
    ],
];
