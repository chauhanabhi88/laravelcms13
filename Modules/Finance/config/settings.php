<?php

/*
| Finance settings (admin > Settings > Finance). Read through Modules\Finance\Support\FinanceSettings,
| never with settings() directly; blank values fall back to config('finance.defaults').
*/

return (static function (): array {
    $yesNo = [1 => 'Yes', 2 => 'No'];

    return [
        'finance::finance.settings.group' => [
            'payout_gateway' => [
                'label' => 'finance::finance.settings.payout_gateway.label',
                'type' => 'select',
                'options' => [
                    'manual' => 'Manual bank transfer (upload UTRs)',
                    'razorpayx' => 'RazorpayX payouts',
                    'fake' => 'Fake (testing only)',
                ],
                'storage' => 'db',
                'comment' => 'finance::finance.settings.payout_gateway.comment',
            ],
            'payout_delay_days' => [
                'label' => 'finance::finance.settings.payout_delay_days.label',
                'type' => 'text',
                'placeholder' => '7',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.payout_delay_days.comment',
            ],
            'payout_day' => [
                'label' => 'finance::finance.settings.payout_day.label',
                'type' => 'select',
                'options' => [
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday',
                ],
                'storage' => 'db',
                'comment' => 'finance::finance.settings.payout_day.comment',
            ],
            'min_payout_amount' => [
                'label' => 'finance::finance.settings.min_payout_amount.label',
                'type' => 'text',
                'placeholder' => '100',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.min_payout_amount.comment',
            ],
            'tcs_rate' => [
                'label' => 'finance::finance.settings.tcs_rate.label',
                'type' => 'text',
                'placeholder' => '1',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.tcs_rate.comment',
            ],
            'tds_rate' => [
                'label' => 'finance::finance.settings.tds_rate.label',
                'type' => 'text',
                'placeholder' => '0.1',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.tds_rate.comment',
            ],
            'tds_rate_no_pan' => [
                'label' => 'finance::finance.settings.tds_rate_no_pan.label',
                'type' => 'text',
                'placeholder' => '5',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.tds_rate_no_pan.comment',
            ],
            'fee_gst_rate' => [
                'label' => 'finance::finance.settings.fee_gst_rate.label',
                'type' => 'text',
                'placeholder' => '18',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.fee_gst_rate.comment',
            ],
            'seller_cancel_penalty' => [
                'label' => 'finance::finance.settings.seller_cancel_penalty.label',
                'type' => 'text',
                'placeholder' => '0',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.seller_cancel_penalty.comment',
            ],
            'sla_breach_penalty' => [
                'label' => 'finance::finance.settings.sla_breach_penalty.label',
                'type' => 'text',
                'placeholder' => '0',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.sla_breach_penalty.comment',
            ],
            'seller_pays_forward_shipping' => [
                'label' => 'finance::finance.settings.seller_pays_forward_shipping.label',
                'type' => 'select',
                'options' => $yesNo,
                'storage' => 'db',
                'comment' => 'finance::finance.settings.seller_pays_forward_shipping.comment',
            ],
            'seller_pays_rto_shipping' => [
                'label' => 'finance::finance.settings.seller_pays_rto_shipping.label',
                'type' => 'select',
                'options' => $yesNo,
                'storage' => 'db',
                'comment' => 'finance::finance.settings.seller_pays_rto_shipping.comment',
            ],
            'hold_open_claims' => [
                'label' => 'finance::finance.settings.hold_open_claims.label',
                'type' => 'text',
                'placeholder' => '0',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.hold_open_claims.comment',
            ],
            'reseller_min_withdrawal' => [
                'label' => 'finance::finance.settings.reseller_min_withdrawal.label',
                'type' => 'text',
                'placeholder' => '100',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.reseller_min_withdrawal.comment',
            ],
            'platform_legal_name' => [
                'label' => 'finance::finance.settings.platform_legal_name.label',
                'type' => 'text',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.platform_legal_name.comment',
            ],
            'platform_gstin' => [
                'label' => 'finance::finance.settings.platform_gstin.label',
                'type' => 'text',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.platform_gstin.comment',
            ],
            'platform_address' => [
                'label' => 'finance::finance.settings.platform_address.label',
                'type' => 'text',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.platform_address.comment',
            ],
            'platform_state_id' => [
                'label' => 'finance::finance.settings.platform_state_id.label',
                'type' => 'text',
                'storage' => 'db',
                'comment' => 'finance::finance.settings.platform_state_id.comment',
            ],
        ],
    ];
})();
