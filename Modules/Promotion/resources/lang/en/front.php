<?php

return [
    'box' => [
        'title' => 'Coupons & offers',
        'label' => 'Coupon code',
        'placeholder' => 'Enter coupon code',
        'apply' => 'Apply',
        'remove' => 'Remove',
        'applied' => ':code applied',
        'you_save' => 'You save :amount',
        'offer_applied' => ':name applied automatically',
        'not_applied' => ':code is not applied',
        'view_offers' => 'View available offers',
        'offers_title' => 'Available offers',
        'no_offers' => 'No offers right now. Check back soon.',
        'use' => 'Use',
        'in_use' => 'Applied',
        'valid_till' => 'Valid till :date',
        'min_cart' => 'On orders of :amount or more',
        'login_hint' => 'Log in to see offers made for you.',
    ],

    'summary' => [
        'coupon' => 'Coupon (:code)',
        'offer' => 'Offer: :name',
    ],

    'benefit' => [
        'percent' => ':percent% off',
        'percent_capped' => ':percent% off up to :cap',
        'flat' => ':amount off',
        'free_delivery' => 'Free delivery',
    ],

    'messages' => [
        'applied' => ':code applied. You save :amount.',
        'removed' => 'Coupon removed.',
    ],

    'errors' => [
        'not_found' => ':code is not a valid coupon code.',
        'inactive' => ':code is not active.',
        'not_started' => ':code can be used from :date.',
        'expired' => ':code has expired.',
        'login_required' => 'Log in to use :code.',
        'not_yours' => ':code is not available on your account.',
        'channel' => ':code can be used only on the :channel.',
        'exhausted' => ':code has been fully redeemed.',
        'already_used' => 'You have already used :code.',
        'first_order' => ':code is for your first order only.',
        'new_customer' => ':code is for customers who joined in the last :days days.',
        'scope' => ':code doesn\'t apply to the items in your cart.',
        'min_cart' => 'Add :amount more to use :code (minimum order :minimum).',
        'payment_method' => ':code is valid only with :method.',
        'empty_cart' => 'Add items to your cart to use :code.',
        'delivery_free' => 'Delivery is already free on this order, so :code adds nothing.',
        'not_applicable' => ':code doesn\'t apply to this order.',
    ],

    'channels' => [
        'web' => 'website',
        'app' => 'app',
    ],

    'payment_methods' => [
        'prepaid' => 'online payment',
        'cod' => 'Cash on Delivery',
    ],

    'flash_deal' => [
        'title' => 'Flash deal',
        'ends_in' => 'Ends in',
        'ended' => 'This deal has ended',
        'hours' => 'h',
        'minutes' => 'm',
        'seconds' => 's',
    ],

    'referral' => [
        'title' => 'Refer & earn',
        'intro' => 'Invite friends with your link. When a friend\'s first order is delivered, you get a :amount coupon.',
        'disabled' => 'Referral rewards are not running right now.',
        'your_code' => 'Your referral code',
        'share_link' => 'Your share link',
        'copy' => 'Copy link',
        'copied' => 'Link copied',
        'share_whatsapp' => 'Share on WhatsApp',
        'share_text' => 'Shop great value finds with me. Join using my link: :url',
        'friends' => 'Friends who joined',
        'no_friends' => 'No friends have joined through your link yet.',
        'rewards' => 'Your reward coupons',
        'no_rewards' => 'Rewards appear here once a friend\'s first order is delivered.',
        'statuses' => [
            'pending' => 'Joined, first order pending',
            'qualified' => 'Qualified',
            'rewarded' => 'Reward issued',
        ],
        'reward_name' => 'Referral reward',
        'reward_description' => 'Thank you for inviting a friend.',
        'landing' => 'Welcome! Sign up to start shopping.',
        'coupon_status' => [
            'active' => 'Ready to use',
            'used' => 'Used',
            'expired' => 'Expired',
        ],
    ],
];
