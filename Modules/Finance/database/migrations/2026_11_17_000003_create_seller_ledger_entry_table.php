<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The append-only seller ledger (FR-FIN-002): every rupee from sale to payout. Amounts are
     * always positive; direction says who owes whom. Corrections are new reversing entries;
     * only status / payout_id move (pending → eligible | on_hold → settled).
     */
    public function up(): void
    {
        Schema::create('seller_ledger_entry', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id');
            $table->unsignedBigInteger('seller_order_id')->nullable()->index();
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->index();
            $table->unsignedBigInteger('return_request_id')->nullable()->index();
            $table->unsignedBigInteger('payout_id')->nullable()->index()->comment('seller_payout.id once batched');
            $table->string('type', 30);
            $table->string('direction', 6)->comment('credit (owed to the seller), debit');
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('GST on fees');
            $table->decimal('base_amount', 12, 2)->nullable()->comment('What a fee or tax was computed on (e.g. the taxable value for TCS)');
            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->date('eligible_on')->nullable();
            $table->string('status', 15)->comment('pending, eligible, on_hold, settled');
            $table->timestamp('posted_at');
            $table->string('idempotency_key', 80)->unique();
            $table->string('created_by_type', 10)->default('system')->comment('system, admin');
            $table->unsignedInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('seller_id')->references('id')->on('seller');
            $table->index(['seller_id', 'status']);
            $table->index(['status', 'eligible_on']);
            $table->index(['type', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entry');
    }
};
