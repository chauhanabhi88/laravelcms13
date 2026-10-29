<?php

return [
    'menu' => 'Reviews',
    'title' => 'Ratings & reviews',
    'hint' => 'Published reviews of your products. Reply once per review — your reply is public, so keep it helpful and polite.',
    'summary' => [
        'average' => 'Store rating',
        'count' => '{0} No ratings yet|{1} Based on 1 rating|[2,*] Based on :count ratings',
        'unreplied' => '{0} All reviews answered|{1} 1 review without a reply|[2,*] :count reviews without a reply',
    ],
    'filters' => [
        'all_ratings' => 'All ratings',
        'all' => 'All',
        'unreplied' => 'Not replied',
        'replied' => 'Replied',
        'search' => 'Product name or SKU',
        'apply' => 'Apply',
    ],
    'empty_title' => 'No reviews yet',
    'empty_text' => 'Reviews from verified buyers of your products appear here.',
    'reply' => [
        'label' => 'Your reply',
        'placeholder' => 'Thank the buyer or explain what you are doing about the issue',
        'submit' => 'Post reply',
        'yours' => 'Your reply',
        'hint' => 'One reply per review. No phone numbers, e-mail addresses or links.',
    ],
    'messages' => [
        'replied' => 'Your reply is published.',
    ],
];
