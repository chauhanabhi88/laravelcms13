<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;

return new class extends Migration
{
    /**
     * Templates for the events M15 adds to the notification catalogue: the customer's
     * request confirmation (return.requested), a return held for CX (admin.return.review) and
     * a seller claim (admin.return.claim). The seeder inserts only missing rows.
     */
    private const EVENTS = ['return.requested', 'admin.return.review', 'admin.return.claim'];

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
