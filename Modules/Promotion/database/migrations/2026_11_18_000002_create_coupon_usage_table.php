<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per coupon (or automatic offer) used by an order (plan M18 §3.2), written inside the
     * place-order transaction. Limits count the "applied" rows; a cancelled order reverses them.
     */
    public function up(): void
    {
        Schema::create('coupon_usage', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('coupon_id');
            $table->unsignedInteger('customer_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->string('code', 30);
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Item discount, or the delivery charge waived');
            $table->string('status', 10)->default('applied')->comment('applied | reversed');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['coupon_id', 'sales_order_id']);
            $table->index(['coupon_id', 'customer_id', 'status']);
            $table->index('sales_order_id');
            $table->foreign('coupon_id')->references('id')->on('coupon')->cascadeOnDelete();
            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usage');
    }
};
