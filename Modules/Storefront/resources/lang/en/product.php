<?php

/*
| Product detail page (M07).
*/

return [
    'meta' => [
        'title' => ':name – Buy online at :price',
    ],

    'preview' => [
        'banner' => 'Preview: this product is :status and not visible to buyers.',
        'banner_live' => 'Preview mode: the page is not indexed and views are not counted.',
    ],

    'gallery' => [
        'label' => 'Product images',
        'show_image' => 'Show image :number',
        'image_of' => 'Image :number of :total',
        'open_fullscreen' => 'View images full screen',
        'close' => 'Close',
        'previous' => 'Previous image',
        'next' => 'Next image',
        'no_image' => 'No image yet',
    ],

    'price' => [
        'inclusive_taxes' => 'Inclusive of all taxes',
        'sku' => 'SKU: :sku',
        'ratings_link' => 'See ratings',
    ],

    'picker' => [
        'select' => 'Select :name',
        'selected' => ':name: :value',
        'required' => 'Please select a :name.',
        'size_chart' => 'Size chart',
        'out_of_stock' => 'Out of stock',
        'only_left' => 'Only :count left',
        'unavailable_combination' => 'This combination is not available.',
        'unavailable_option' => ':name (out of stock)',
    ],

    'actions' => [
        'add_to_cart' => 'Add to Cart',
        'buy_now' => 'Buy Now',
        'coming_soon' => 'Cart and checkout are coming soon.',
        'out_of_stock' => 'Out of stock',
        'select_options' => 'Select options to continue',
    ],

    'size_chart' => [
        'title' => 'Size chart',
        'size' => 'Size',
        'unit' => 'All measurements in :unit.',
    ],

    'delivery' => [
        'title' => 'Delivery & services',
        'delivery_by' => 'Estimated delivery by :date',
        'cod_available' => 'Cash on Delivery available',
        'cod_unavailable' => 'Cash on Delivery not available',
        'not_serviceable' => 'This pincode isn\'t serviceable yet. Try another address.',
        'invalid_pincode' => 'Enter a valid 6-digit pincode.',
        'invalid_variant' => 'Choose an available option of this product.',
        'product_unavailable' => 'This product is not available.',
        'estimate_note' => 'Delivery dates are estimates and may vary.',
    ],

    'returns' => [
        'title' => 'Returns',
        'return_exchange' => ':days-day easy return & exchange',
        'exchange_only' => ':days-day exchange only',
        'none' => 'No returns on this item',
        'note' => 'Returns are accepted within the window from delivery, unused and with original tags and packaging.',
    ],

    'details' => [
        'title' => 'Product Details',
        'highlights' => 'Highlights',
        'specifications' => 'Specifications',
        'description' => 'Description',
        'manufacturer' => 'Manufacturer & Origin',
        'brand' => 'Brand',
        'verified_brand' => 'Verified brand',
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'manufacturer' => [
        'manufacturer' => 'Manufacturer',
        'packer' => 'Packer',
        'importer' => 'Importer',
        'country_of_origin' => 'Country of origin',
    ],

    'seller' => [
        'sold_by' => 'Sold by',
        'view_shop' => 'View Shop',
        'no_rating' => 'New seller',
        'followers' => '{0} No followers|{1} 1 follower|[2,*] :count followers',
        'products' => '{0} No products|{1} 1 product|[2,*] :count products',
    ],

    'reviews' => [
        'title' => 'Ratings & Reviews',
        'no_ratings' => 'No ratings yet',
        'no_ratings_text' => 'Ratings appear here once buyers review this product.',
        'based_on' => '{1} Based on 1 rating|[2,*] Based on :count ratings',
        'stars' => ':count★',
        'photos' => 'Photos from buyers',
        'view_all' => 'View all reviews',
        'view_all_count' => 'View all :count reviews',
        'filter_stars' => 'Show :count-star reviews',
        'verified' => 'Verified Buyer',
        'helpful' => '{1} 1 person found this helpful|[2,*] :count people found this helpful',
        'seller_reply' => 'Seller’s reply',
        'close_photo' => 'Close photo',
        'previous_photo' => 'Previous photo',
        'next_photo' => 'Next photo',
    ],

    'similar' => [
        'similar' => 'Similar Products',
        'seller' => 'More from this seller',
        'loading' => 'Loading products…',
    ],

    'share' => [
        'share' => 'Share',
        'whatsapp' => 'WhatsApp',
        'copy_link' => 'Copy link',
        'copied' => 'Link copied',
        'facebook' => 'Facebook',
        'text' => ':name at just :price',
    ],

    'share_earn' => [
        'button' => 'Share & Earn',
        'title' => 'Share & Earn',
        'intro' => 'Set your selling price, then share the details and images with your customer.',
        'selling_price' => 'Our price',
        'your_price' => 'Your price (₹)',
        'margin' => 'You earn',
        'price_too_low' => 'Your price must be at least :price.',
        'copy_text' => 'Copy details',
        'copied' => 'Details copied',
        'share_images' => 'Share images',
        'download_images' => 'Download images',
        'text_price' => 'Price: :price',
        'text_sizes' => 'Sizes: :sizes',
        'text_perks' => 'Free delivery · Cash on Delivery available',
    ],

    'unavailable' => [
        'title' => 'This product is not available',
        'suggestions' => 'You may also like',
    ],

    'wishlist' => [
        'add' => 'Add to wishlist',
        'remove' => 'Remove from wishlist',
    ],
];
