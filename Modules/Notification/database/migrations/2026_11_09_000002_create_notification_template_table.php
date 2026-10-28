<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMS / push / in-app / WhatsApp templates per catalogue event and audience. Email
     * templates stay in the Mail module (mail_templates, slug "<event-key>-<audience>").
     */
    public function up(): void
    {
        Schema::create('notification_template', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80);
            $table->string('channel', 20)->comment('sms | push | database | whatsapp');
            $table->string('audience', 20)->comment('customer | seller | admin');
            $table->string('title', 150)->nullable();
            $table->text('body')->comment('Text with {{placeholders}}');
            $table->string('provider_template_id', 60)->nullable()->comment('DLT / WhatsApp template id');
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 2 = Inactive');
            $table->timestamps();

            $table->unique(['event_key', 'channel', 'audience'], 'notification_template_event_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_template');
    }
};
