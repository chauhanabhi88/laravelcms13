<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carts (FR-CRT-001): guests are keyed by the "cart_token" cookie, customers by customer_id.
     * A customer has at most one cart per type ("standard", and a short-lived "buy_now").
     */
    public function up(): void
    {
        Schema::create('cart', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->char('token', 40)->unique()->comment('Guest cookie / X-Cart-Token value');
            $table->string('type', 10)->default('standard')->comment('standard | buy_now');
            $table->string('coupon_code', 40)->nullable()->comment('Applied by the Promotion module (M18)');
            $table->char('delivery_pincode', 6)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('reminded_at')->nullable()->comment('Abandoned-cart reminder sent');
            $table->timestamps();

            $table->unique(['customer_id', 'type']);
            $table->index(['type', 'last_activity_at']);
            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart');
    }
};
