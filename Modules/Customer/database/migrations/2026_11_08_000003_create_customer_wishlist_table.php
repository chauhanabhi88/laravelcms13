<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products a customer saved (FR-CUS-006). Guests have no wishlist: the heart asks them to log in.
     */
    public function up(): void
    {
        Schema::create('customer_wishlist', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('product_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['customer_id', 'product_id']);
            $table->index('product_id');
            $table->index(['customer_id', 'created_at']);
            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wishlist');
    }
};
