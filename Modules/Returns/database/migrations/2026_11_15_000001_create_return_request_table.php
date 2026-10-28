<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer's return or exchange of items from one seller order (plan M15 §3.1). The
     * status follows the pickup → receipt → QC path; refund_status tracks the money on its own
     * track, because a trusted customer is refunded at pickup, before the seller's QC.
     */
    public function up(): void
    {
        Schema::create('return_request', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('return_no', 20)->unique()->comment('RET-2611-000045');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedInteger('customer_id')->index();
            $table->unsignedInteger('seller_id');
            $table->string('type', 10)->comment('return, exchange');
            $table->string('status', 25)->index();
            $table->string('reason_code', 30);
            $table->text('comment')->nullable();
            $table->string('refund_mode', 10)->nullable()->comment('source, bank, upi');
            $table->text('refund_beneficiary')->nullable()->comment('Encrypted json: {account_holder, account_number, ifsc} or {vpa}');
            $table->decimal('refund_amount', 12, 2)->default(0)->comment('Computed: items (minus coupon share) + delivery_refund');
            $table->decimal('delivery_refund', 12, 2)->default(0)->comment('Delivery share refunded on a seller-fault return');
            $table->string('refund_status', 12)->nullable()->comment('initiated, refunded, failed');
            $table->string('refund_on', 10)->default('qc')->comment('pickup (trusted customer), qc');
            $table->json('pickup_address')->comment('Snapshot, defaults to the shipping address');
            $table->unsignedBigInteger('reverse_shipment_id')->nullable();
            $table->unsignedBigInteger('exchange_seller_order_id')->nullable();
            $table->unsignedTinyInteger('auto_approved')->default(2);
            $table->unsignedTinyInteger('needs_attention')->default(2);
            $table->string('attention_reason', 255)->nullable();
            $table->string('decided_by', 20)->nullable()->comment('system, admin');
            $table->unsignedInteger('decided_by_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->string('qc_note', 500)->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('qc_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
            $table->foreign('seller_order_id')->references('id')->on('seller_order')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
            $table->foreign('reverse_shipment_id')->references('id')->on('shipment')->nullOnDelete();
            $table->foreign('exchange_seller_order_id')->references('id')->on('seller_order')->nullOnDelete();
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request');
    }
};
