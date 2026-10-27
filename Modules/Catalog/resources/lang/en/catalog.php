<?php

return [
    'titles' => [
        'catalog' => 'Catalog',
    ],
    'settings' => [
        'catalog' => 'Catalog',
        'default_return_window' => [
            'label' => 'Default Return Window (days)',
            'comment' => 'Used when neither a category nor any of its parents sets a return window.',
        ],
        'default_dispatch_sla_hours' => [
            'label' => 'Default Dispatch SLA (hours)',
            'comment' => 'Hours a seller has to dispatch an order when no category sets its own SLA.',
        ],
        'default_gst_percent' => [
            'label' => 'Default GST %',
            'comment' => 'Used when neither a category nor any of its parents sets a GST rate.',
        ],
        'max_category_depth' => [
            'label' => 'Maximum Category Levels',
            'comment' => 'How many levels the category tree may have (top-level categories are level 1).',
        ],
        'max_variant_axes' => [
            'label' => 'Maximum Variant Axes per Category',
            'comment' => 'How many attributes (e.g. Size and Colour) may define product variants in one category.',
        ],
    ],
    'return_types' => [
        'return_exchange' => 'Return & Exchange',
        'exchange_only' => 'Exchange Only',
        'none' => 'No Returns',
    ],
    'errors' => [
        'cycle' => 'A category cannot be moved under itself or one of its own subcategories.',
        'too_deep' => 'The category tree can have at most :max levels.',
        'has_children' => '":name" still has subcategories. Move or delete them first.',
        'has_products' => '":name" has products. Move the products to another category first.',
        'parent_has_products' => '":name" has products, so it cannot get subcategories. Move the products first.',
        'parent_missing' => 'The selected parent category no longer exists.',
        'too_many_axes' => '":name" would have more than :max variant attributes. Untick "Variant axis" on one of them.',
        'not_found' => 'Category not found.',
    ],
];
