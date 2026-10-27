<?php

return [
    'name' => 'Pages',
    'web' => 1,
    'mobile' => 2,
    'lang_path' => 'pages::pages.labels',

    /*
    | Public home page: Banner group code for the hero slider and Block slugs
    | for the content sections. Missing groups/blocks simply hide their section.
    */
    'home' => [
        'banner_group' => 'home-banner',
        'blocks' => [
            'intro' => 'home_intro',
            'cta' => 'home_cta',
        ],
        'featured_posts' => 3,
    ],
];
