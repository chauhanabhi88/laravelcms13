<?php

return [
    'payment::payment.titles.payments' => [
        'admin.payment.index' => 'payment::payment.labels.list',
        'admin.payment.filters' => 'payment::payment.labels.filters',
        'admin.payment.show' => 'payment::payment.labels.show',
    ],
    'payment::payment_refund.titles.refunds' => [
        'admin.payment_refund.index' => 'payment::payment_refund.labels.list',
        'admin.payment_refund.filters' => 'payment::payment_refund.labels.filters',
        'admin.payment_refund.mark_processed' => 'payment::payment_refund.labels.mark_processed',
        'admin.payment_refund.retry' => 'payment::payment_refund.labels.retry',
    ],
    'payment::webhook_event.titles.webhooks' => [
        'admin.webhook_event.index' => 'payment::webhook_event.labels.list',
        'admin.webhook_event.filters' => 'payment::webhook_event.labels.filters',
        'admin.webhook_event.show' => 'payment::webhook_event.labels.show',
        'admin.webhook_event.replay' => 'payment::webhook_event.labels.replay',
    ],
    'payment::payment_recon.titles.reconciliation' => [
        'admin.payment_recon.index' => 'payment::payment_recon.labels.list',
        'admin.payment_recon.filters' => 'payment::payment_recon.labels.filters',
        'admin.payment_recon.resolve' => 'payment::payment_recon.labels.resolve',
    ],
];
