<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The part of a sales order one seller fulfils (SRS §7.1): the authoritative fulfilment
     * status, its own SLA, invoice number and totals over its active items.
     */
    public function up(): void
    {
        Schema::create('seller_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedInteger('seller_id');
            $table->string('sub_order_no', 24)->unique()->comment('ORD-2610-000123-1');
            $table->unsignedInteger('warehouse_id')->nullable()->comment('Default seller_warehouse at placement');
            $table->string('status', 25)->index();
            $table->decimal('items_subtotal', 12, 2)->comment('Σ active item price × qty');
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('delivery_charge_share', 12, 2)->default(0);
            $table->string('invoice_no', 40)->nullable()->unique()->comment('Assigned at ready_to_ship (M14)');
            $table->date('invoice_date')->nullable();
            $table->timestamp('sla_dispatch_by')->nullable();
            $table->timestamp('expected_delivery_at')->nullable()->comment('EDD shown at checkout (latest across the package)');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->string('cancelled_by', 10)->nullable()->comment('customer, seller, admin, system');
            $table->timestamp('return_window_ends_at')->nullable()->comment('Set on delivered (M13)');
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
            $table->foreign('warehouse_id')->references('id')->on('seller_warehouse')->nullOnDelete();
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['status', 'sla_dispatch_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_order');
    }
};
