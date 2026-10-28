<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-outs per recipient, channel and category. No row means enabled; transactional
     * messages ignore this table.
     */
    public function up(): void
    {
        Schema::create('notification_preference', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type', 120);
            $table->unsignedBigInteger('notifiable_id');
            $table->string('channel', 20);
            $table->string('category', 20)->comment('promotional | order_alerts | digest');
            $table->tinyInteger('enabled')->default(1)->comment('1 = Yes, 2 = No');
            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'channel', 'category'], 'notification_preference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preference');
    }
};
