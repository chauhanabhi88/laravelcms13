<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refunds (FR-PAY-006): prepaid to source through the gateway, COD to a bank account or
     * UPI id through a payout. idempotency_key makes a re-run of the same return or
     * cancellation return the existing row instead of refunding twice.
     */
    public function up(): void
    {
        Schema::create('payment_refund', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('return_request_id')->nullable()->index()->comment('Returns module (M15)');
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('mode', 10)->comment('source | bank | upi');
            $table->string('status', 20)->index()->comment('pending, processing, processed, failed');
            $table->string('gateway_refund_id', 40)->nullable()->unique()->comment('Razorpay rfnd_xxx');
            $table->string('payout_id', 40)->nullable()->comment('RazorpayX payout for COD refunds (M17)');
            $table->string('utr', 40)->nullable()->comment('Bank reference when Finance marks it paid');
            $table->text('beneficiary')->nullable()->comment('Encrypted: account/ifsc or vpa');
            $table->string('reason', 255)->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payment')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refund');
    }
};
