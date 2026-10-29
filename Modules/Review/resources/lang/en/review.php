<?php

return [
    'stars' => ':count ★',
    'statuses' => [
        'pending' => 'Pending',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'hidden' => 'Hidden',
    ],
    'flags' => [
        'profanity' => 'Offensive words',
        'link' => 'Link',
        'contact' => 'Phone / e-mail',
        'duplicate' => 'Duplicate text',
        'too_short' => 'Too short',
    ],
    'report_reasons' => [
        'spam' => 'Spam or advertising',
        'offensive' => 'Offensive or abusive',
        'irrelevant' => 'Not about the product',
        'fake' => 'Looks fake or misleading',
    ],
    'settings' => [
        'group' => 'Reviews',
        'auto_publish' => [
            'label' => 'Publish reviews automatically',
            'comment' => 'Yes: reviews that pass the automatic checks (offensive words, links, phone numbers, duplicates) go live at once; the rest wait for a moderator. No: every review waits for a moderator.',
        ],
        'min_body_length' => [
            'label' => 'Minimum review length',
            'comment' => 'Characters required when the customer writes review text (a star rating alone is always allowed). Default 10.',
        ],
        'max_images' => [
            'label' => 'Photos per review',
            'comment' => 'Most photos a customer can add to one review (0 turns photos off, at most 10). Default 5.',
        ],
        'edit_window_days' => [
            'label' => 'Edit window (days)',
            'comment' => 'Days after writing during which the customer can edit their review. Changed text is checked again. Default 30.',
        ],
        'report_threshold' => [
            'label' => 'Reports before moderation',
            'comment' => 'Abuse reports that put a published review back in the moderation queue (it stays visible until a moderator decides). Default 3.',
        ],
        'prompt_delay_days' => [
            'label' => 'Review reminder delay (days)',
            'comment' => 'Days after delivery before the customer is asked to rate the item. Default 3.',
        ],
    ],
];
