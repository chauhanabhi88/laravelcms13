<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payment per checkout attempt of a sales order (FR-PAY-001…005). A switch from
     * prepaid to COD cancels the open payment and creates a new one, so an order can own
     * several rows; at most one of them is open.
     */
    public function up(): void
    {
        Schema::create('payment', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sales_order_id')->index()->comment('FK added by the Order module (M12)');
            $table->string('order_no', 24)->nullable()->index()->comment('Snapshot of sales_order.order_no');
            $table->unsignedInteger('customer_id')->index();
            $table->string('method', 20)->comment('razorpay | cod | fake');
            $table->string('status', 20)->comment('created, attempted, captured, failed, cancelled, refunded, partially_refunded; COD: pending, collected, remitted');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_refunded', 12, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->string('gateway_order_id', 40)->nullable()->unique()->comment('Razorpay order_xxx');
            $table->string('gateway_payment_id', 40)->nullable()->unique()->comment('Razorpay pay_xxx');
            $table->string('instrument', 20)->nullable()->comment('upi, card, netbanking, wallet');
            $table->json('instrument_meta')->nullable()->comment('Masked card network/last4, VPA handle, bank');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('End of the payment / COD OTP window');
            $table->string('failure_reason', 255)->nullable();
            $table->tinyInteger('cod_otp_required')->default(2)->comment('1 = Yes, 2 = No');
            $table->timestamp('cod_otp_verified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['customer_id', 'method', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
