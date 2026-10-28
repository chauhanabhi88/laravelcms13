<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notification\Support\EventDefinition;

return new class extends Migration
{
    /**
     * Templates for the events this module adds to the notification catalogue: the customer's
     * failed delivery attempt (order.delivery_failed) and the Logistics Ops alert for a
     * shipment needing attention (admin.shipment.attention). The seeder inserts only missing
     * rows, so existing admin edits are kept.
     */
    private const EVENTS = ['order.delivery_failed' => 'customer', 'admin.shipment.attention' => 'admin'];

    public function up(): void
    {
        if (class_exists(NotificationTemplateSeeder::class)) {
            (new NotificationTemplateSeeder)->run();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_template')) {
            DB::table('notification_template')->whereIn('event_key', array_keys(self::EVENTS))->delete();
        }

        if (Schema::hasTable('mail_templates') && class_exists(EventDefinition::class)) {
            foreach (self::EVENTS as $key => $audience) {
                DB::table('mail_templates')->where('slug', EventDefinition::mailSlugFor($key, $audience))->delete();
            }
        }
    }
};
