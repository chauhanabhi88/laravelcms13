<?php

return [
    'titles' => [
        'list' => 'Flash deals',
        'create' => 'New flash deal',
        'edit' => 'Edit flash deal',
        'general' => 'Deal',
        'items' => 'Variants on deal',
        'add_items' => 'Add variants',
    ],

    'permissions' => [
        'index' => 'List flash deals',
        'filters' => 'Filter flash deals',
        'create' => 'Create flash deals',
        'edit' => 'Edit flash deals',
        'delete' => 'Delete flash deals',
    ],

    'fields' => [
        'name' => 'Name',
        'starts_at' => 'Starts',
        'ends_at' => 'Ends',
        'banner' => 'Banner image URL',
        'status' => 'Enabled',
        'sku' => 'System SKU',
        'product' => 'Product',
        'price' => 'Own price',
        'deal_price' => 'Deal price (₹)',
        'stock_cap' => 'Deal units (blank = no cap)',
        'sold' => 'Sold at deal price',
        'remove' => 'Remove',
    ],

    'hints' => [
        'list' => 'During the window the variants sell at their deal price (when it is below their own price) until the deal units run out; then the price falls back. Add a "Flash deal (countdown)" home section to show the running deal on the home page.',
        'items' => 'Add variants by their system SKU (shown on the product screen). The deal price must be below the variant\'s own price.',
    ],

    'columns' => [
        'id' => 'ID',
        'name' => 'Name',
        'items_count' => 'Variants',
        'starts_at' => 'Starts',
        'ends_at' => 'Ends',
        'state' => 'State',
        'status' => 'Status',
        'created_at' => 'Created At',
    ],

    'buttons' => [
        'add' => 'Add flash deal',
        'delete' => 'Delete',
        'edit' => 'Edit',
    ],

    'errors' => [
        'unknown_sku' => 'No variant has the SKU ":sku".',
        'duplicate' => ':sku is already in this deal.',
        'too_many' => 'A deal holds at most :max variants.',
        'price_required' => 'Enter a deal price above zero.',
        'price_not_lower' => 'The deal price of :sku must be below its own price :price.',
        'cap_below_sold' => 'The cap cannot be below the :sold units already sold.',
    ],

    'messages' => [
        'created' => 'Flash deal created.',
        'updated' => 'Flash deal updated.',
        'deleted' => 'Flash deal deleted.',
        'not_found' => 'Flash deal not found.',
        'rows_invalid' => 'Some variant rows need attention; nothing was saved.',
        'confirm_delete' => 'Delete this flash deal?',
        'no_items' => 'No variants yet.',
    ],
];
