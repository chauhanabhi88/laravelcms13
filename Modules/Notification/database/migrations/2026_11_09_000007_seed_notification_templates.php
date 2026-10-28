<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Notification\Database\Seeders\NotificationTemplateSeeder;

return new class extends Migration
{
    /**
     * Default templates for every catalogue event (notification_template + mail_templates).
     * The seeder only inserts missing rows, so admin edits survive a re-run.
     */
    public function up(): void
    {
        (new NotificationTemplateSeeder)->run();
    }

    public function down(): void
    {
        NotificationTemplateSeeder::remove();
    }
};
