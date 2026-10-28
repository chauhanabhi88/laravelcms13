<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The order items (and quantities) of a return; for an exchange, the variant wanted instead
     * (plan M15 §3.2). unit_refund is the per-unit refund after the coupon share.
     */
    public function up(): void
    {
        Schema::create('return_request_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('return_request_id');
            $table->unsignedBigInteger('sales_order_item_id');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('exchange_variant_id')->nullable();
            $table->decimal('unit_refund', 12, 2)->default(0);
            $table->decimal('line_refund', 12, 2)->default(0)->comment('unit_refund × quantity, rounded once');
            $table->string('status', 10)->default('pending')->comment('pending, accepted, rejected');
            $table->timestamps();

            $table->foreign('return_request_id')->references('id')->on('return_request')->cascadeOnDelete();
            $table->foreign('sales_order_item_id')->references('id')->on('sales_order_item')->cascadeOnDelete();
            $table->index(['sales_order_item_id', 'status'], 'return_request_item_order_item_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_item');
    }
};
