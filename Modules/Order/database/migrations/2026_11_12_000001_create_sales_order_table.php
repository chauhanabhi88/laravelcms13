<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The customer's order (FR-ORD-003). "order" is a reserved word in MySQL, hence sales_order.
     * The status is derived from its seller orders (OrderStatusService::refresh()); money
     * columns are the snapshot taken at placement.
     */
    public function up(): void
    {
        Schema::create('sales_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('order_no', 20)->unique()->comment('ORD-2610-000123 (SequenceService)');
            $table->unsignedInteger('customer_id');
            $table->string('status', 25)->index()->comment('placed, pending_payment, pending_cod_verification, confirmed, partially_shipped, shipped, delivered, partially_cancelled, cancelled, completed');
            $table->string('payment_method', 20)->comment('razorpay | cod | fake');
            $table->string('payment_status', 20)->nullable()->comment('Mirrors the current payment');
            $table->unsignedSmallInteger('items_count');
            $table->decimal('total_mrp', 12, 2);
            $table->decimal('total_discount', 12, 2)->comment('MRP - selling price');
            $table->string('coupon_code', 40)->nullable()->comment('M18');
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('cod_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->comment('What the platform collects');
            $table->tinyInteger('is_reseller_order')->default(2)->comment('1 = Yes, 2 = No');
            $table->decimal('reseller_margin_total', 12, 2)->default(0);
            $table->tinyInteger('cod_allowed')->default(1)->comment('Every item allowed COD at placement: 1 = Yes, 2 = No');
            $table->string('placed_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('channel', 10)->default('web');
            $table->char('placed_token', 36)->unique()->comment('Checkout idempotency token');
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer');
            $table->index(['customer_id', 'placed_at']);
            $table->index(['status', 'placed_at']);
            $table->index(['payment_method', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order');
    }
};
