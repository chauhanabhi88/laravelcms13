<?php

return [
    'title' => 'My earnings',
    'subheading' => 'Margins you earn on cash-on-delivery orders for your customers.',
    'not_reseller' => 'Earnings are for resellers: switch on reseller mode to add your margin at checkout.',
    'enable_reseller' => 'Reseller mode',
    'available' => 'Available to withdraw',
    'pending' => 'Pending (return window open)',
    'paid' => 'Withdrawn',
    'pending_hint' => 'A margin becomes available once the order\'s return window closes.',
    'withdraw_heading' => 'Withdraw',
    'withdraw_hint' => 'Minimum :minimum. The money is sent to your UPI id or bank account.',
    'amount' => 'Amount (Rs)',
    'vpa' => 'UPI id',
    'account_holder' => 'Account holder name',
    'account_number' => 'Account number',
    'account_number_confirmation' => 'Confirm account number',
    'ifsc' => 'IFSC',
    'withdraw' => 'Withdraw',
    'withdrawals' => 'Withdrawals',
    'history' => 'History',
    'history_empty' => 'Nothing yet. Margins appear here when your customers\' orders are delivered.',
    'failed_by_finance' => 'Transfer failed',
    'confirm_fail' => 'Mark this withdrawal failed? The amount goes back to the reseller\'s wallet.',

    'modes' => [
        'upi' => 'UPI',
        'bank' => 'Bank account',
    ],

    'types' => [
        'margin_credit' => 'Margin earned',
        'withdrawal' => 'Withdrawal',
        'reversal' => 'Reversal',
        'adjustment' => 'Adjustment',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'available' => 'Available',
        'paid' => 'Paid',
    ],

    'descriptions' => [
        'margin_credit' => 'Margin on :item (:order)',
        'reversal' => 'Margin reversed for return :return',
        'withdrawal' => 'Withdrawal :no',
        'withdrawal_failed' => 'Withdrawal :no failed, amount returned',
    ],

    'errors' => [
        'not_reseller' => 'Only resellers can withdraw earnings.',
        'below_minimum' => 'You can withdraw :minimum or more.',
        'insufficient' => 'The amount is more than your available earnings.',
    ],

    'messages' => [
        'requested' => 'Withdrawal :no of :amount is on its way.',
        'marked_paid' => 'Withdrawal :no was marked paid.',
        'marked_failed' => 'Withdrawal :no was marked failed and the amount returned to the wallet.',
    ],
];
