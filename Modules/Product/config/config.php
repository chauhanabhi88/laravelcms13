<?php

return [
    'name' => 'Product',

    /*
    | Entity names: cache buckets (BaseCacheDecorator) and session filter keys of the admin grids.
    */
    'cache' => [
        'product' => 'Product',
        'product_qc' => 'ProductQc',
        'inventory_movement' => 'InventoryMovement',
        'catalog_upload' => 'CatalogUpload',
        'seller_catalog' => 'SellerCatalog',
    ],

    /*
    | Product images live flat in storage/app/public/<folder> (served from /storage/<folder>),
    | so getImageUrl(['module' => 'product', 'image' => $path]) resolves them.
    */
    'upload' => [
        'folder' => 'product',
        'image_types' => 'jpg,jpeg,png,webp',
        'max_size_kb' => 5120,
    ],

    /*
    | General images per product, and extra images per colour (attribute option).
    */
    'max_images' => 8,
    'max_images_per_option' => 8,
    'min_image_px' => 500,

    /*
    | Resized copies made on upload: folder => [width, height] (3:4 portrait).
    */
    'image_sizes' => [
        'card' => [300, 400],
        'pdp' => [800, 1067],
        'zoom' => [1500, 2000],
        'thumb' => [96, 128],
    ],

    'max_highlights' => 8,
    'max_highlight_length' => 200,

    /*
    | Anti fake-MRP guard: the MRP may be at most this many times the selling price.
    */
    'mrp_multiplier_guard' => 10,

    'default_low_stock_threshold' => 5,

    /*
    | Duplicate detection: same primary image hash and a name at least this similar (0-100).
    */
    'duplicate_name_similarity' => 85,

    /*
    | QC rejection reasons offered as checkboxes; labels in product::product.reason_templates.
    */
    'qc_reason_templates' => [
        'blurry_images',
        'watermark',
        'wrong_category',
        'mrp_mismatch',
        'prohibited_item',
        'duplicate_listing',
        'incomplete_details',
    ],

    /*
    | Review queue shown in the left pane of the QC review screen.
    */
    'qc_queue_preview' => 50,

    /*
    | Fallbacks for admin > Settings > Product, read through Modules\Product\Support\ProductSettings.
    */
    'defaults' => [
        'auto_approve_trusted_sellers' => 2,
        'trusted_seller_min_rating' => '4.3',
        'qc_sla_hours' => 72,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier panel catalog tools (M05)
    |--------------------------------------------------------------------------
    */

    'seller_per_page' => 20,

    /*
    | Variant matrix: at most this many variants per product.
    */
    'max_variants' => 100,

    /*
    | GST rates a GST-registered seller may pick for a product (category default otherwise).
    */
    'gst_rates' => ['0', '3', '5', '12', '18', '28', '40'],

    /*
    | Bulk uploads (new catalog / price & stock). Files stay on the private disk; the job runs
    | on its own queue: php artisan queue:work --queue=imports,default
    */
    'bulk_max_rows' => 5000,
    'bulk_max_mb' => 10,
    'bulk_chunk_rows' => 500,
    'bulk_disk' => 'private',
    'bulk_folder' => 'catalog_uploads',
    'bulk_mimes' => 'xlsx,csv,txt',
    'import_queue' => 'imports',

    /*
    | Image URLs in the bulk template: download timeout (seconds), size cap and redirect hops.
    */
    'image_fetch_timeout' => 10,
    'image_fetch_max_kb' => 5120,
    'image_fetch_max_redirects' => 3,

    /*
    | Price & earnings calculator. TCS (GST §52) is a percentage of the taxable value and TDS
    | (§194-O) of the gross sale, used while the FeeCalculator reports none. Forward shipping
    | comes from admin > Settings > Logistics "rate_card" when set (M13), else from this card:
    | the first slab whose weight covers the parcel, plus "additional_charge" per extra
    | "additional_grams" above the last slab.
    */
    'calculator' => [
        'tcs_percent' => '1',
        'tds_percent' => '0.1',
        'rate_card' => [
            'slabs' => [
                ['up_to_grams' => 500, 'charge' => '45'],
                ['up_to_grams' => 1000, 'charge' => '65'],
                ['up_to_grams' => 2000, 'charge' => '95'],
            ],
            'additional_grams' => 1000,
            'additional_charge' => '40',
        ],
    ],
];
