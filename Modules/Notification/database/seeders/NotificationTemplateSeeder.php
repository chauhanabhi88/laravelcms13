<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Support\EventDefinition;

/**
 * Default English templates for every catalogue event: notification_template rows for the
 * SMS / push / in-app channels and Mail module rows (mail_templates) for email. Inserts
 * only what is missing, so admin edits are kept.
 *
 * SMS text must be registered on the DLT portal before go-live; paste the approved
 * template id into provider_template_id and keep the wording identical.
 */
class NotificationTemplateSeeder extends Seeder
{
    /**
     * Per event: title (push / in-app heading), text (push / in-app body), sms, and the email
     * subject + body. {{var}} in the first three, ##var## in the email.
     *
     * @var array<string, array{title: string, text: string, sms?: string, subject?: string, mail?: string}>
     */
    private const COPY = [
        'customer.welcome' => [
            'title' => 'Welcome to {{app_name}}',
            'text' => 'Your account is ready. Start exploring great value picks.',
            'subject' => 'Welcome to ##app_name##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your ##app_name## account is ready. Discover everyday value across fashion, home and more, with pay on delivery and easy returns.</p><p><a href="##url##">Start shopping</a></p>',
        ],
        'order.placed' => [
            'title' => 'Order {{order_no}} placed',
            'text' => 'Thanks for your order of {{item_count}} item(s) worth Rs {{amount}}. We will keep you posted.',
            'sms' => 'Your {{app_name}} order {{order_no}} for Rs {{amount}} is placed. Expected delivery by {{delivery_date}}.',
            'subject' => 'Your order ##order_no## is placed',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Thank you for shopping with ##app_name##. Your order <strong>##order_no##</strong> for Rs ##amount## (##item_count## item(s)) is placed.</p><p>Expected delivery: ##delivery_date##</p><p><a href="##url##">View your order</a></p>',
        ],
        'order.shipped' => [
            'title' => 'Order {{order_no}} shipped',
            'text' => 'Your order is on its way with {{courier}}. Tracking number {{awb}}.',
            'sms' => 'Your {{app_name}} order {{order_no}} has shipped via {{courier}}, AWB {{awb}}. Track: {{track_url}}',
            'subject' => 'Your order ##order_no## has shipped',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Good news: your order <strong>##order_no##</strong> is on its way with ##courier## (tracking number ##awb##).</p><p><a href="##track_url##">Track your shipment</a></p>',
        ],
        'order.out_for_delivery' => [
            'title' => 'Arriving today',
            'text' => 'Order {{order_no}} is out for delivery. Amount to pay: Rs {{amount_due}}.',
            'sms' => 'Your {{app_name}} order {{order_no}} is out for delivery today. Amount to pay: Rs {{amount_due}}.',
        ],
        'order.delivered' => [
            'title' => 'Order {{order_no}} delivered',
            'text' => 'Your order has been delivered. We hope you love it!',
            'subject' => 'Your order ##order_no## is delivered',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your order <strong>##order_no##</strong> has been delivered. If something is not right, you can request a return from your orders page.</p><p><a href="##url##">View order</a></p>',
        ],
        'order.cancelled' => [
            'title' => 'Order {{order_no}} cancelled',
            'text' => 'Your order was cancelled. {{reason}}',
            'sms' => 'Your {{app_name}} order {{order_no}} is cancelled. Any amount paid will be refunded to the original payment method.',
            'subject' => 'Your order ##order_no## is cancelled',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your order <strong>##order_no##</strong> has been cancelled. Reason: ##reason##</p><p>Any amount you paid will be refunded to the original payment method.</p>',
        ],
        'return.approved' => [
            'title' => 'Return {{return_no}} approved',
            'text' => 'Pickup for your return is scheduled for {{pickup_date}}. Keep the item packed.',
            'sms' => 'Your {{app_name}} return {{return_no}} for order {{order_no}} is approved. Pickup on {{pickup_date}}.',
            'subject' => 'Your return ##return_no## is approved',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your return <strong>##return_no##</strong> for order ##order_no## is approved. Our courier partner will pick it up on ##pickup_date##.</p><p>Please keep the item ready in its original packaging.</p>',
        ],
        'return.picked_up' => [
            'title' => 'Return {{return_no}} picked up',
            'text' => 'We have collected your return. Your refund is processed after a quick quality check.',
        ],
        'return.rejected' => [
            'title' => 'Return {{return_no}} not approved',
            'text' => 'We could not approve your return. {{reason}}',
            'subject' => 'Update on your return ##return_no##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>We are sorry, your return <strong>##return_no##</strong> could not be approved. Reason: ##reason##</p><p>Reply to this email if you need help.</p>',
        ],
        'refund.processed' => [
            'title' => 'Refund of Rs {{amount}} processed',
            'text' => 'Your refund for order {{order_no}} has been sent to your {{refund_mode}}.',
            'sms' => 'Refund of Rs {{amount}} for your {{app_name}} order {{order_no}} is processed to your {{refund_mode}}.',
            'subject' => 'Refund processed for order ##order_no##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>We have processed a refund of <strong>Rs ##amount##</strong> for order ##order_no## to your ##refund_mode##. It may take a few working days to reflect.</p>',
        ],
        'review.request' => [
            'title' => 'How was your {{product_name}}?',
            'text' => 'Rate your purchase and help other shoppers choose well.',
            'subject' => 'How was your ##product_name##?',
            'mail' => '<p>Hi ##recipient_name##,</p><p>We hope you are enjoying your <strong>##product_name##</strong>. Share a quick rating to help other shoppers.</p><p><a href="##url##">Rate now</a></p>',
        ],
        'cart.abandoned' => [
            'title' => 'Your picks are waiting',
            'text' => 'You left {{item_count}} item(s) in your cart. Complete your order before they sell out.',
        ],
        'seller.order.new' => [
            'title' => 'New order {{order_no}}',
            'text' => '{{item_count}} item(s) to accept. Dispatch by {{dispatch_by}}.',
            'sms' => 'New {{app_name}} order {{order_no}} with {{item_count}} item(s). Please accept and dispatch by {{dispatch_by}}.',
            'subject' => 'New order ##order_no##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>You have a new order <strong>##order_no##</strong> with ##item_count## item(s). Please accept it and dispatch by ##dispatch_by##.</p><p><a href="##url##">Open the order</a></p>',
        ],
        'seller.sla.warning' => [
            'title' => 'Dispatch due for {{order_no}}',
            'text' => 'Order {{order_no}} must be dispatched by {{dispatch_by}} to avoid cancellation.',
            'sms' => '{{app_name}}: order {{order_no}} must be dispatched by {{dispatch_by}} to avoid auto-cancellation.',
        ],
        'seller.product.approved' => [
            'title' => 'Listing approved',
            'text' => '{{product_name}} passed quality check and is now live.',
            'subject' => 'Your listing is live: ##product_name##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your listing <strong>##product_name##</strong> passed quality check and is now live for shoppers.</p><p><a href="##url##">View listing</a></p>',
        ],
        'seller.product.rejected' => [
            'title' => 'Listing needs changes',
            'text' => '{{product_name}} was not approved. {{reason}}',
            'subject' => 'Changes needed for ##product_name##',
            'mail' => '<p>Hi ##recipient_name##,</p><p>Your listing <strong>##product_name##</strong> did not pass quality check.</p><p>Reason: ##reason##</p><p><a href="##url##">Update the listing</a></p>',
        ],
        'seller.upload.completed' => [
            'title' => 'Bulk upload #{{upload_id}} finished',
            'text' => '{{success}} row(s) saved, {{failed}} failed.',
        ],
        'seller.low_stock.digest' => [
            'title' => 'Low stock on {{count}} SKU(s)',
            'text' => 'Restock soon to keep your listings live.',
        ],
        'seller.kyc.approved' => [
            'title' => 'Your store is approved',
            'text' => '{{store}} is verified. You can start listing products.',
            'sms' => 'Congratulations! Your {{app_name}} supplier account {{store}} is approved. Log in to start listing.',
        ],
        'seller.kyc.rejected' => [
            'title' => 'Application not approved',
            'text' => 'We could not approve {{store}}. {{reason}}',
            'sms' => 'Your {{app_name}} supplier application for {{store}} was not approved. Log in to see the reason.',
        ],
        'seller.kyc.changes_requested' => [
            'title' => 'Changes requested',
            'text' => 'Please update your application: {{reason}}',
            'sms' => 'Action needed: please update your {{app_name}} supplier application for {{store}}. Log in to see the details.',
        ],
        'seller.payout.processed' => [
            'title' => 'Payout {{payout_no}} sent',
            'text' => 'Rs {{amount}} was transferred to your bank account. UTR {{utr}}.',
            'sms' => '{{app_name}} payout {{payout_no}} of Rs {{amount}} is sent to your bank account. UTR {{utr}}.',
            'subject' => 'Payout ##payout_no## sent',
            'mail' => '<p>Hi ##recipient_name##,</p><p>We have transferred <strong>Rs ##amount##</strong> to your bank account for payout ##payout_no##. Bank reference (UTR): ##utr##.</p>',
        ],
        'seller.return.received' => [
            'title' => 'Return {{return_no}} on its way back',
            'text' => 'A return for order {{order_no}} is heading to your warehouse.',
        ],
        'admin.seller.submitted' => [
            'title' => 'New supplier application',
            'text' => '{{store}} ({{seller_code}}) is waiting for verification.',
            'subject' => 'Supplier application to review: ##store##',
            'mail' => '<p>Hi ##recipient_name##,</p><p><strong>##store##</strong> (##seller_code##) submitted their application and is waiting for KYC verification.</p><p><a href="##url##">Review the application</a></p>',
        ],
        'admin.webhook.failed' => [
            'title' => 'Webhook failed: {{provider}}',
            'text' => 'Event {{event_id}} could not be processed. {{error}}',
            'subject' => 'Webhook failed: ##provider##',
            'mail' => '<p>A ##provider## webhook (event ##event_id##) could not be processed.</p><p>Error: ##error##</p>',
        ],
        'admin.payout.failed' => [
            'title' => 'Payout {{payout_no}} failed',
            'text' => 'Payout to {{store}} failed. {{error}}',
            'subject' => 'Payout ##payout_no## failed',
            'mail' => '<p>The payout <strong>##payout_no##</strong> to ##store## failed.</p><p>Error: ##error##</p>',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('notification_template')) {
            return;
        }

        $now = now();

        foreach ((array) config('notification.events', require __DIR__.'/../../config/events.php') as $key => $event) {
            $copy = self::COPY[$key] ?? null;

            if ($copy === null) {
                continue;
            }

            foreach ((array) ($event['channels'] ?? []) as $channel) {
                $body = match ($channel) {
                    'sms' => $copy['sms'] ?? null,
                    'push', 'database' => $copy['text'],
                    default => null,
                };

                if ($channel === 'mail') {
                    $this->seedMailTemplate((string) $key, (string) $event['audience'], $copy, $now);

                    continue;
                }

                if ($body === null) {
                    continue;
                }

                DB::table('notification_template')->insertOrIgnore([
                    'event_key' => $key,
                    'channel' => $channel,
                    'audience' => $event['audience'],
                    'title' => $channel === 'sms' ? null : $copy['title'],
                    'body' => $body,
                    'provider_template_id' => null,
                    'status' => (int) config('core.enabled', 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Remove the seeded rows (migration rollback).
     */
    public static function remove(): void
    {
        if (Schema::hasTable('notification_template')) {
            DB::table('notification_template')->whereIn('event_key', array_keys(self::COPY))->delete();
        }

        if (Schema::hasTable('mail_templates')) {
            $events = require __DIR__.'/../../config/events.php';
            $slugs = array_map(
                fn (string $key): string => EventDefinition::mailSlugFor($key, (string) $events[$key]['audience']),
                array_keys(array_intersect_key($events, self::COPY)),
            );

            DB::table('mail_templates')->whereIn('slug', $slugs)->delete();
        }
    }

    /**
     * @param  array{title: string, text: string, subject?: string, mail?: string}  $copy
     */
    private function seedMailTemplate(string $key, string $audience, array $copy, mixed $now): void
    {
        if (! isset($copy['subject'], $copy['mail']) || ! Schema::hasTable('mail_templates')) {
            return;
        }

        $slug = EventDefinition::mailSlugFor($key, $audience);

        if (DB::table('mail_templates')->where('slug', $slug)->exists()) {
            return;
        }

        DB::table('mail_templates')->insert([
            'name' => 'Notification: '.$key.' ('.$audience.')',
            'subject' => $copy['subject'],
            'slug' => $slug,
            'cc' => null,
            'bcc' => null,
            'body' => $copy['mail'],
            'status' => (int) config('core.enabled', 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
