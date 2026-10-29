<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reseller margin wallet (FR-FIN-007): margins of delivered COD reseller orders, credited
     * once the return window closes, and withdrawals to a bank account or UPI id.
     */
    public function up(): void
    {
        Schema::create('reseller_withdrawal', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('withdrawal_no', 24)->unique()->comment('WDL-2611-000001');
            $table->decimal('amount', 12, 2);
            $table->string('mode', 5)->comment('upi, bank');
            $table->text('beneficiary')->comment('Encrypted');
            $table->string('beneficiary_label', 60)->nullable()->comment('Masked, for display');
            $table->string('status', 12)->index()->comment('pending, processing, paid, failed');
            $table->string('gateway', 12)->nullable();
            $table->string('gateway_payout_id', 60)->nullable()->unique();
            $table->string('utr', 40)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_wallet_entry', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('type', 15)->comment('margin_credit, withdrawal, reversal, adjustment');
            $table->string('direction', 6)->comment('credit, debit');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->index();
            $table->unsignedBigInteger('withdrawal_id')->nullable()->index();
            $table->string('description', 255)->nullable();
            $table->string('status', 10)->comment('pending, available, paid');
            $table->date('eligible_on')->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'eligible_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_wallet_entry');
        Schema::dropIfExists('reseller_withdrawal');
    }
};
