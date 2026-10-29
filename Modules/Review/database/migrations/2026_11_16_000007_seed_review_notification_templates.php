<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notification\Support\EventDefinition;

return new class extends Migration
{
    /**
     * Templates for the events M16 adds to the notification catalogue: a rejected review
     * (review.rejected, customer) and a review reported past the threshold
     * (admin.review.reported). review.request was seeded by M09. The seeder inserts only
     * missing rows.
     */
    private const EVENTS = ['review.rejected' => 'customer', 'admin.review.reported' => 'admin'];

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
            $slugs = array_map(fn (string $key, string $audience): string => EventDefinition::mailSlugFor($key, $audience), array_keys(self::EVENTS), self::EVENTS);
            DB::table('mail_templates')->whereIn('slug', $slugs)->delete();
        }
    }
};
