<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;

return new class extends Migration
{
    /**
     * Templates for the supplier events M14 adds to the notification catalogue: a label batch
     * ready to download (seller.labels.ready) and an order cancelled for a missed dispatch
     * deadline (seller.order.sla_cancelled). The seeder inserts only missing rows.
     */
    private const EVENTS = ['seller.labels.ready', 'seller.order.sla_cancelled'];

    public function up(): void
    {
        if (class_exists(NotificationTemplateSeeder::class)) {
            (new NotificationTemplateSeeder)->run();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_template')) {
            DB::table('notification_template')->whereIn('event_key', self::EVENTS)->delete();
        }
    }
};
