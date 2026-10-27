<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customers following a store (FR-SEL-012, C priority): table only, no UI yet.
     */
    public function up(): void
    {
        Schema::create('seller_follower', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('customer_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['seller_id', 'customer_id']);
            $table->index('customer_id');
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_follower');
    }
};
