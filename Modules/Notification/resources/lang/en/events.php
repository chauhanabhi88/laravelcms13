<?php

/*
| Admin labels of the catalogue events (config notification.events), keyed by event key.
| Nested because event keys contain dots: trans('notification::events.order.shipped').
*/

return [
    'customer' => [
        'welcome' => 'Welcome',
    ],
    'order' => [
        'placed' => 'Order placed',
        'shipped' => 'Order shipped',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Order delivered',
        'cancelled' => 'Order cancelled',
    ],
    'return' => [
        'approved' => 'Return approved',
        'picked_up' => 'Return picked up',
        'rejected' => 'Return rejected',
    ],
    'refund' => [
        'processed' => 'Refund processed',
    ],
    'review' => [
        'request' => 'Review request',
    ],
    'cart' => [
        'abandoned' => 'Abandoned cart',
    ],
    'seller' => [
        'order' => ['new' => 'New order'],
        'sla' => ['warning' => 'Dispatch SLA warning'],
        'product' => ['approved' => 'Listing approved', 'rejected' => 'Listing rejected'],
        'upload' => ['completed' => 'Bulk upload finished'],
        'low_stock' => ['digest' => 'Low stock digest'],
        'kyc' => ['approved' => 'KYC approved', 'rejected' => 'KYC rejected', 'changes_requested' => 'KYC changes requested'],
        'payout' => ['processed' => 'Payout sent'],
        'return' => ['received' => 'Return on its way'],
    ],
    'admin' => [
        'seller' => ['submitted' => 'Supplier application submitted'],
        'webhook' => ['failed' => 'Webhook failed'],
        'payout' => ['failed' => 'Payout failed'],
        'refund' => ['failed' => 'Refund failed'],
        'payment' => ['recon_mismatch' => 'Settlement mismatches found'],
    ],
];
