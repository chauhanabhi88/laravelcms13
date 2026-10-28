<?php

/*
|--------------------------------------------------------------------------
| Notification event catalogue (FR-NTF-002)
|--------------------------------------------------------------------------
|
| Feature modules call NotificationHub::event('<key>', $recipient, $data). Per event:
|   audience        customer | seller | admin
|   channels        the channels it may go out on (each still needs a route, an active
|                   template and, for opt-out categories, the recipient's consent)
|   category        transactional (always sent) | promotional | order_alerts | digest
|   icon            in-app bell icon (<x-theme::icon> name)
|   vars            placeholders the templates may use, besides the common ones
|                   (app_name, recipient_name, url)
|   seller_roles    seller staff roles that receive it besides the owner (eventForSeller)
|   admin_permission admins whose role grants it receive it (eventForAdmins)
|
| OTP is not sent through the hub: it goes straight from OtpService to the SmsDriver.
|
*/

return [
    // Customer
    'customer.welcome' => ['audience' => 'customer', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'user', 'vars' => []],
    'order.placed' => ['audience' => 'customer', 'channels' => ['mail', 'sms', 'push', 'database'], 'category' => 'transactional', 'icon' => 'package', 'vars' => ['order_no', 'amount', 'item_count', 'delivery_date']],
    'order.shipped' => ['audience' => 'customer', 'channels' => ['sms', 'push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'truck', 'vars' => ['order_no', 'courier', 'awb', 'track_url']],
    'order.out_for_delivery' => ['audience' => 'customer', 'channels' => ['sms', 'push'], 'category' => 'transactional', 'icon' => 'truck', 'vars' => ['order_no', 'amount_due']],
    'order.delivered' => ['audience' => 'customer', 'channels' => ['push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'check', 'vars' => ['order_no']],
    'order.cancelled' => ['audience' => 'customer', 'channels' => ['sms', 'mail', 'database'], 'category' => 'transactional', 'icon' => 'x', 'vars' => ['order_no', 'reason']],
    'order.delivery_failed' => ['audience' => 'customer', 'channels' => ['sms', 'push', 'database'], 'category' => 'transactional', 'icon' => 'truck', 'vars' => ['order_no', 'reason', 'attempts']],
    'return.requested' => ['audience' => 'customer', 'channels' => ['push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'rotate-ccw', 'vars' => ['return_no', 'order_no', 'type']],
    'return.approved' => ['audience' => 'customer', 'channels' => ['sms', 'push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'package', 'vars' => ['return_no', 'order_no', 'pickup_date']],
    'return.picked_up' => ['audience' => 'customer', 'channels' => ['push', 'database'], 'category' => 'transactional', 'icon' => 'truck', 'vars' => ['return_no']],
    'return.rejected' => ['audience' => 'customer', 'channels' => ['push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'x', 'vars' => ['return_no', 'reason']],
    'refund.processed' => ['audience' => 'customer', 'channels' => ['sms', 'push', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'banknote', 'vars' => ['order_no', 'amount', 'refund_mode']],
    'review.request' => ['audience' => 'customer', 'channels' => ['push', 'mail'], 'category' => 'promotional', 'icon' => 'star', 'vars' => ['product_name', 'order_no']],
    'cart.abandoned' => ['audience' => 'customer', 'channels' => ['push'], 'category' => 'promotional', 'icon' => 'cart', 'vars' => ['item_count']],

    // Seller
    'seller.order.new' => ['audience' => 'seller', 'channels' => ['sms', 'database', 'mail'], 'category' => 'order_alerts', 'icon' => 'package', 'vars' => ['order_no', 'item_count', 'dispatch_by'], 'seller_roles' => ['orders']],
    'seller.sla.warning' => ['audience' => 'seller', 'channels' => ['sms', 'database'], 'category' => 'transactional', 'icon' => 'clock', 'vars' => ['order_no', 'dispatch_by'], 'seller_roles' => ['orders']],
    'seller.order.sla_cancelled' => ['audience' => 'seller', 'channels' => ['sms', 'database'], 'category' => 'transactional', 'icon' => 'x', 'vars' => ['order_no', 'reason'], 'seller_roles' => ['orders']],
    'seller.labels.ready' => ['audience' => 'seller', 'channels' => ['database'], 'category' => 'transactional', 'icon' => 'download', 'vars' => ['count'], 'seller_roles' => ['orders']],
    'seller.product.approved' => ['audience' => 'seller', 'channels' => ['database', 'mail'], 'category' => 'transactional', 'icon' => 'check', 'vars' => ['product_name'], 'seller_roles' => ['catalog']],
    'seller.product.rejected' => ['audience' => 'seller', 'channels' => ['database', 'mail'], 'category' => 'transactional', 'icon' => 'x', 'vars' => ['product_name', 'reason'], 'seller_roles' => ['catalog']],
    'seller.upload.completed' => ['audience' => 'seller', 'channels' => ['database'], 'category' => 'transactional', 'icon' => 'upload', 'vars' => ['upload_id', 'success', 'failed'], 'seller_roles' => ['catalog']],
    'seller.low_stock.digest' => ['audience' => 'seller', 'channels' => ['database'], 'category' => 'digest', 'icon' => 'package', 'vars' => ['count'], 'seller_roles' => ['catalog']],
    'seller.kyc.approved' => ['audience' => 'seller', 'channels' => ['sms', 'database'], 'category' => 'transactional', 'icon' => 'check', 'vars' => ['store']],
    'seller.kyc.rejected' => ['audience' => 'seller', 'channels' => ['sms', 'database'], 'category' => 'transactional', 'icon' => 'x', 'vars' => ['store', 'reason']],
    'seller.kyc.changes_requested' => ['audience' => 'seller', 'channels' => ['sms', 'database'], 'category' => 'transactional', 'icon' => 'pencil', 'vars' => ['store', 'reason']],
    'seller.payout.processed' => ['audience' => 'seller', 'channels' => ['sms', 'database', 'mail'], 'category' => 'transactional', 'icon' => 'banknote', 'vars' => ['payout_no', 'amount', 'utr'], 'seller_roles' => ['finance']],
    'seller.return.received' => ['audience' => 'seller', 'channels' => ['database'], 'category' => 'transactional', 'icon' => 'package', 'vars' => ['return_no', 'order_no'], 'seller_roles' => ['orders']],

    // Admin
    'admin.seller.submitted' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'store', 'vars' => ['store', 'seller_code'], 'admin_permission' => 'admin.seller_application.index'],
    'admin.webhook.failed' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'info', 'vars' => ['provider', 'event_id', 'error'], 'admin_permission' => 'admin.notification_log.index'],
    'admin.payout.failed' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'banknote', 'vars' => ['payout_no', 'store', 'error'], 'admin_permission' => 'admin.notification_log.index'],
    'admin.refund.failed' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'banknote', 'vars' => ['order_no', 'amount', 'error'], 'admin_permission' => 'admin.payment_refund.index'],
    'admin.payment.recon_mismatch' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'banknote', 'vars' => ['date', 'count'], 'admin_permission' => 'admin.payment_recon.index'],
    'admin.shipment.attention' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'truck', 'vars' => ['order_no', 'reason'], 'admin_permission' => 'admin.shipment.index'],
    'admin.return.review' => ['audience' => 'admin', 'channels' => ['database'], 'category' => 'transactional', 'icon' => 'rotate-ccw', 'vars' => ['return_no', 'order_no', 'reason'], 'admin_permission' => 'admin.return_request.approve'],
    'admin.return.claim' => ['audience' => 'admin', 'channels' => ['mail', 'database'], 'category' => 'transactional', 'icon' => 'rotate-ccw', 'vars' => ['order_no', 'store', 'reason'], 'admin_permission' => 'admin.return_claim.decide'],
];
