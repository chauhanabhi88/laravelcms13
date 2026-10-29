<?php

return [
    'buttons' => [
        'retry' => 'Retry',
    ],

    'messages' => [
        'not_payable' => 'A payout in status ":status" cannot be marked paid.',
        'not_retryable' => 'Only a failed payout can be retried.',
        'entries_moved' => 'Some of this payout\'s entries were paid in another batch; they will be settled with the next batch instead.',
        'retried' => 'Payout :payout was sent again.',
        'marked_paid' => 'Payout :payout was marked paid.',
    ],
];
