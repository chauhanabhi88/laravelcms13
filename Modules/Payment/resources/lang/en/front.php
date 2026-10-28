<?php

return [
    'pay' => [
        'title' => 'Complete your payment',
        'heading' => 'Pay for order :order_no',
        'amount' => 'Amount to pay',
        'description' => 'Order :order_no',
        'upi_block' => 'Pay by UPI',
        'methods' => 'UPI, cards, net banking and wallets. Your card details go straight to Razorpay; we never see them.',
        'button' => 'Pay :amount',
        'time_left' => 'Complete the payment within',
        'expired' => 'The payment window has closed.',
        'last_failed' => 'Your last attempt did not go through: :reason',
        'retry' => 'Try again',
        'cod_heading' => 'Prefer to pay on delivery?',
        'cod_button' => 'Pay with Cash on Delivery instead',
        'cod_unavailable' => 'Cash on Delivery is not available: :reason',
        'secure' => '100% secure payments',
        'no_js' => 'Turn on JavaScript to open the payment window.',
        'test_mode' => 'Test gateway: no real money is charged.',
        'simulate_success' => 'Simulate a successful payment',
        'simulate_failure' => 'Simulate a failed payment',
        'simulated_failure' => 'Simulated failure.',
        'loading' => 'Opening the payment window…',
    ],

    'failed' => [
        'title' => 'Payment not completed',
        'heading' => 'We could not complete your payment',
        'body' => 'The payment for order :order_no was not completed in time, so the order has been cancelled and no money was taken. If money left your account, it is refunded automatically within 5–7 working days.',
        'cod_body' => 'Order :order_no was not confirmed in time, so it has been cancelled.',
        'continue' => 'Continue shopping',
        'view_cart' => 'Go to cart',
    ],

    'cod_otp' => [
        'title' => 'Confirm your Cash on Delivery order',
        'heading' => 'Confirm order :order_no',
        'subheading' => 'Enter the code we sent to :to to confirm your Cash on Delivery order.',
        'amount' => 'Keep :amount ready at delivery.',
        'verify' => 'Confirm order',
        'resend' => 'Resend code',
        'resend_in' => 'Resend in :seconds s',
        'time_left' => 'The order is cancelled if it is not confirmed within',
        'required' => 'Enter the code.',
        'invalid' => 'That code is not right or has expired. Try again or ask for a new one.',
        'wait' => 'Please wait :seconds seconds before asking for another code.',
        'send_failed' => 'We could not send the code. Please try again.',
        'switch_online' => 'Pay online instead',
    ],

    'cod' => [
        'reasons' => [
            'disabled' => 'Cash on Delivery is not offered right now.',
            'pincode' => 'Cash on Delivery is not available for pincode :pincode.',
            'above_max' => 'Cash on Delivery is available for orders up to :amount.',
            'below_min' => 'Cash on Delivery is available for orders from :amount.',
            'items' => 'Some items in this order can only be paid online.',
            'customer_blocked' => 'Cash on Delivery is not available on your account. Please pay online.',
            'daily_limit' => 'You have reached the limit of :count Cash on Delivery orders for today.',
        ],
    ],

    'refund_modes' => [
        'source' => 'original payment method',
        'bank' => 'bank account',
        'upi' => 'UPI id',
    ],

    'messages' => [
        'paid' => 'Payment received. Thank you!',
        'cod_confirmed' => 'Your Cash on Delivery order is confirmed.',
        'cod_otp_sent' => 'We have sent you a code to confirm the order.',
    ],

    'errors' => [
        'already_paid' => 'This order is already paid.',
        'not_payable' => 'This order cannot be paid any more.',
        'gateway_unavailable' => 'The payment service is not responding. Please try again in a minute.',
        'invalid_signature' => 'We could not verify this payment. If money was taken, it will be confirmed or refunded automatically.',
        'payment_failed' => 'The payment did not go through. You can try again.',
        'cancelled_by_buyer' => 'Payment cancelled.',
        'confirming' => 'We are confirming your payment with the bank. This page will update shortly.',
        'amount_mismatch' => 'The paid amount does not match the order. Our team will look into it.',
        'cod_otp_not_needed' => 'This order does not need a confirmation code.',
        'cod_otp_expired' => 'The time to confirm this order has passed.',
        'cod_otp_no_contact' => 'Add a mobile number or email to your account to confirm Cash on Delivery orders.',
    ],
];
