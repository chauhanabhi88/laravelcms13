<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notification\Support\EventDefinition;

return new class extends Migration
{
    /**
     * Templates for the admin events this module adds to the notification catalogue
     * (admin.refund.failed, admin.payment.recon_mismatch). The seeder inserts only missing
     * rows, so existing admin edits are kept.
     */
    private const EVENTS = ['admin.refund.failed', 'admin.payment.recon_mismatch'];

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

        if (Schema::hasTable('mail_templates') && class_exists(EventDefinition::class)) {
            DB::table('mail_templates')
                ->whereIn('slug', array_map(fn (string $key): string => EventDefinition::mailSlugFor($key, 'admin'), self::EVENTS))
                ->delete();
        }
    }
};
