<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One ordered variant with its snapshots: price, MRP, tax split, commission, reseller
     * price and return policy as they were at placement (FR-ORD-003, FR-ORD-007).
     */
    public function up(): void
    {
        Schema::create('sales_order_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedInteger('product_id')->index();
            $table->unsignedInteger('variant_id')->index();
            $table->string('sku', 64);
            $table->string('seller_sku', 64)->nullable();
            $table->string('name', 200);
            $table->string('variant_label', 100)->nullable();
            $table->string('image', 255)->nullable();
            $table->string('hsn_code', 8)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('mrp', 12, 2)->comment('Unit');
            $table->decimal('price', 12, 2)->comment('Unit selling price, GST inclusive');
            $table->decimal('discount_allocated', 12, 2)->default(0)->comment('Coupon share (M18)');
            $table->decimal('taxable_value', 12, 2)->comment('Line');
            $table->decimal('gst_percent', 5, 2);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0)->comment('Line');
            $table->unsignedInteger('commission_rule_id')->nullable();
            $table->decimal('customer_price', 12, 2)->nullable()->comment('Reseller final unit price');
            $table->decimal('reseller_margin', 12, 2)->default(0)->comment('Line: (customer_price - price) × qty, COD only');
            $table->unsignedTinyInteger('return_window_days')->default(0);
            $table->string('return_type', 15)->nullable();
            $table->string('status', 25)->default('active')->comment('active, cancelled, return_requested, returned, exchanged');
            $table->unsignedSmallInteger('cancelled_qty')->default(0);
            $table->unsignedSmallInteger('returned_qty')->default(0);
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
            $table->foreign('seller_order_id')->references('id')->on('seller_order')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_item');
    }
};
