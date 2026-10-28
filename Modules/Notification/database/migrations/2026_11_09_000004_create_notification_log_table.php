<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per hub notification and channel (FR-NTF-004): queued when the hub dispatches,
     * then sent / failed / skipped once the queued channel ran.
     */
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('notification_id')->nullable();
            $table->string('event_key', 80);
            $table->string('channel', 20);
            $table->string('notifiable_type', 120)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('destination', 150)->nullable()->comment('Masked phone / email / token suffix');
            $table->string('status', 20)->default('queued')->comment('queued | sent | failed | skipped');
            $table->string('provider_message_id', 191)->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event_key', 'created_at'], 'notification_log_event_index');
            $table->index(['notifiable_type', 'notifiable_id'], 'notification_log_notifiable_index');
            $table->index(['status', 'created_at'], 'notification_log_status_index');
            $table->index('notification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
