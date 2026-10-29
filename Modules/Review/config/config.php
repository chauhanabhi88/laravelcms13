<?php

return [
    'name' => 'Review',

    /*
    | Session filter / cache entity key of the admin moderation grid.
    */
    'cache' => [
        'product_review' => 'ProductReview',
    ],

    /*
    | Report reasons (lang review::review.report_reasons.*), in the order the customer sees them.
    */
    'report_reasons' => ['spam', 'offensive', 'irrelevant', 'fake'],

    /*
    | Sorts of the full reviews page (lang review::front.sorts.*); the first is the default.
    */
    'sorts' => ['helpful', 'latest', 'rating_high', 'rating_low'],

    /*
    | Text limits. min_body_length comes from Settings > Reviews.
    */
    'title_max_length' => 120,
    'body_max_length' => 2000,
    'reply_min_length' => 2,
    'reply_max_length' => 1000,

    /*
    | Review photos: public disk (shown on the storefront), re-encoded as JPEG (EXIF dropped),
    | scaled to max_dimension plus a square-ish thumbnail, random names under reviews/{id}/.
    */
    'uploads' => [
        'disk' => 'public',
        'folder' => 'reviews',
        'max_size_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_dimension' => 1600,
        'thumb_dimension' => 320,
    ],

    /*
    | Product page summary: photos in the strip, top reviews, minutes it is cached (flushed
    | whenever the product's reviews change).
    */
    'summary' => [
        'photos' => 10,
        'top' => 3,
        'cache_minutes' => 10,
    ],

    'per_page' => 10,
    'customer_per_page' => 10,
    'seller_per_page' => 20,

    /*
    | Contact details and links are held for moderation (no off-platform selling in reviews).
    */
    'patterns' => [
        'link' => '~(?:https?://|www\.)\S+|\b[a-z0-9][a-z0-9-]*\.(?:com|in|net|org|co|io|xyz|shop|store|info|biz|me|app|link|site|online|ly)\b(?:/\S*)?~iu',
        'phone' => '~(?<!\d)(?:\+?91[\s.-]?|0)?[6-9](?:[\s.-]?\d){9}(?!\d)~u',
        'email' => '~[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}~iu',
    ],

    /*
    | Defaults for admin > Settings > Reviews (read through Modules\Review\Support\ReviewSettings).
    */
    'defaults' => [
        'auto_publish' => 1,
        'min_body_length' => 10,
        'max_images' => 5,
        'edit_window_days' => 30,
        'report_threshold' => 3,
        'prompt_delay_days' => 3,
    ],
];
