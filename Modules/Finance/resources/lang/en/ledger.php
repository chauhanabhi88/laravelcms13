<?php

return [
    'descriptions' => [
        'sale' => ':item × :qty (:order)',
        'commission' => 'Commission on :item × :qty (:order)',
        'platform_fee' => 'Platform fee on :item × :qty (:order)',
        'tcs' => 'TCS :rate% on :item × :qty (:order)',
        'tds' => 'TDS :rate% on :item × :qty (:order)',
        'logistics_forward' => 'Shipping for :order (AWB :awb)',
        'logistics_rto' => 'RTO shipping for :order (AWB :awb)',
        'logistics_reverse' => 'Return shipping for :return',
        'return_reversal' => 'Return :return: :qty unit(s) refunded',
        'commission_reversal' => 'Commission refunded for return :return',
        'platform_fee_reversal' => 'Platform fee refunded for return :return',
        'tcs_reversal' => 'TCS reversed for return :return',
        'tds_reversal' => 'TDS reversed for return :return',
        'penalty_cancel' => 'Penalty: order :order cancelled by the seller',
        'penalty_sla' => 'Penalty: order :order not dispatched in time',
        'claim_credit' => 'Claim #:claim accepted',
        'adjustment' => 'Adjustment: :reason',
        'payout' => 'Payout :payout (UTR :utr)',
        'payout_reversed' => 'Payout :payout returned by the bank',
    ],

    'adjust' => [
        'title' => 'Manual adjustment',
        'hint' => 'A second finance admin must approve it before it reaches the seller\'s ledger.',
        'seller_code' => 'Seller code',
        'direction' => 'Direction',
        'amount' => 'Amount (Rs)',
        'reason' => 'Reason',
        'submit' => 'Request adjustment',
        'pending' => 'Adjustments awaiting approval',
        'none' => 'No adjustment is waiting.',
        'approve' => 'Approve',
        'reject' => 'Reject',
    ],

    'messages' => [
        'amount_positive' => 'The amount must be more than zero.',
        'adjustment_requested' => 'The adjustment was requested; another finance admin must approve it.',
        'adjustment_approved' => 'The adjustment was approved and posted.',
        'adjustment_rejected' => 'The adjustment was rejected.',
        'adjustment_decided' => 'This adjustment was already decided.',
        'maker_checker' => 'You requested this adjustment, so another admin must approve it.',
        'seller_not_found' => 'No seller has this code.',
    ],
];
