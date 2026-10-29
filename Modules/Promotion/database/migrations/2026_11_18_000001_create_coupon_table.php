<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coupons and automatic offers (FR-PRM-001/002, plan M18 §3.1). A row with is_auto = 1 is an
     * offer applied without a code; seller_id marks a seller's own coupon (funded by the seller,
     * limited to their products); customer_id a personal coupon (referral reward).
     */
    public function up(): void
    {
        Schema::create('coupon', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code', 30)->unique()->comment('Upper case');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('type', 15)->comment('percent | flat | free_delivery');
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable()->comment('Cap of a percent coupon');
            $table->decimal('min_cart_value', 10, 2)->default(0)->comment('On the eligible lines');
            $table->string('scope', 10)->default('all')->comment('all | category | seller | product | brand');
            $table->json('scope_ids')->nullable();
            $table->string('funded_by', 10)->default('platform')->comment('platform | seller');
            $table->unsignedInteger('seller_id')->nullable()->comment('Seller coupon (scope locked to their products)');
            $table->unsignedInteger('customer_id')->nullable()->comment('Personal coupon');
            $table->unsignedInteger('usage_limit_total')->nullable()->comment('null = unlimited');
            $table->unsignedInteger('usage_limit_per_customer')->nullable()->default(1)->comment('null = unlimited');
            $table->unsignedInteger('used_count')->default(0)->comment('Applied usages (display only)');
            $table->unsignedTinyInteger('first_order_only')->default(2);
            $table->unsignedSmallInteger('new_customer_days')->nullable()->comment('Only customers who joined within N days');
            $table->json('channels')->nullable()->comment('web, app; null = every channel');
            $table->json('payment_methods')->nullable()->comment('prepaid, cod; null = every method');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedTinyInteger('is_auto')->default(2)->comment('Automatic offer (no code)');
            $table->unsignedTinyInteger('is_visible')->default(1)->comment('Listed in "Available offers"');
            $table->unsignedTinyInteger('stackable')->default(2);
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('batch', 30)->nullable()->comment('Bulk-generated codes share a batch');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_auto']);
            $table->index('seller_id');
            $table->index('customer_id');
            $table->index('batch');
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon');
    }
};
