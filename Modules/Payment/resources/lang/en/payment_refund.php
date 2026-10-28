<?php

return [
    'titles' => [
        'refunds' => 'Refunds',
        'list' => 'Refunds',
    ],

    'labels' => [
        'list' => 'View refunds',
        'filters' => 'Filter refunds',
        'mark_processed' => 'Mark refund paid',
        'retry' => 'Retry refund',
        'status' => 'Status',
        'mode' => 'Mode',
        'search' => 'Order no, gateway refund id or UTR',
        'utr' => 'Bank reference (UTR)',
        'utr_hint' => 'Required for COD refunds paid by hand.',
        'confirm_mark' => 'Mark this refund as paid?',
        'confirm_retry' => 'Send this refund again?',
        'beneficiary' => 'Beneficiary',
        'payment' => 'Payment',
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],

    'columns' => [
        'id' => 'ID',
        'payment' => 'Payment',
        'amount' => 'Amount',
        'mode' => 'Mode',
        'status' => 'Status',
        'reference' => 'Reference',
        'reason' => 'Reason',
        'failure_reason' => 'Failure',
        'processed_at' => 'Processed At',
        'created_at' => 'Created At',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'processed' => 'Processed',
        'failed' => 'Failed',
    ],

    'modes' => [
        'source' => 'Original method',
        'bank' => 'Bank account',
        'upi' => 'UPI',
    ],

    'messages' => [
        'not_found' => 'The refund was not found.',
        'marked_processed' => 'The refund is marked as paid.',
        'retried' => 'The refund was sent again (now :status).',
        'utr_required' => 'Enter the bank reference (UTR) of the payout.',
        'invalid_transition' => 'A :status refund cannot be changed this way.',
        'not_retryable' => 'Only failed or pending refunds can be retried.',
        'not_refundable' => 'This payment has not been paid, so nothing can be refunded.',
        'over_refund' => 'The refund is more than what is left to refund (:available).',
        'bad_key' => 'The refund idempotency key must be a UUID.',
        'bad_amount' => 'The refund amount must be more than zero.',
        'prepaid_to_source' => 'Online payments are refunded to the original payment method.',
        'cod_beneficiary' => 'Cash on Delivery refunds need a valid bank account (number and IFSC) or UPI id.',
        'key_reused' => 'This idempotency key belongs to a refund on another payment.',
    ],
];
