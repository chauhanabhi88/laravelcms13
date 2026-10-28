<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inbound provider webhooks (FR-PAY-003), shared with other providers (razorpayx,
     * shiprocket). The unique (provider, event_id) key is the idempotency guard: a
     * redelivered event is ignored.
     */
    public function up(): void
    {
        Schema::create('webhook_event', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('provider', 20)->comment('razorpay | razorpayx | shiprocket');
            $table->string('event_id', 80);
            $table->string('event_type', 60)->nullable();
            $table->tinyInteger('signature_valid')->default(1)->comment('1 = Yes, 2 = No');
            $table->json('payload')->nullable();
            $table->string('status', 20)->index()->comment('received, processed, failed, ignored');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_event');
    }
};
