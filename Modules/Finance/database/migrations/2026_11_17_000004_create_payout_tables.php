<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settlement cycles (FR-FIN-004): a payout batch per cycle with one payout per seller;
     * manual seller holds (FR-FIN-009); manual ledger adjustments awaiting a second admin
     * (maker-checker).
     */
    public function up(): void
    {
        Schema::create('payout_batch', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('batch_no', 24)->unique()->comment('PB-2611-0003');
            $table->date('cycle_start')->nullable();
            $table->date('cycle_end');
            $table->string('status', 20)->index()->comment('draft, approved, processing, completed, partially_failed');
            $table->string('gateway', 12)->comment('razorpayx, manual, fake');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedInteger('sellers_count')->default(0);
            $table->unsignedInteger('created_by')->nullable()->comment('Admin users.id; null = scheduled build');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_payout', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payout_batch_id');
            $table->string('payout_no', 24)->unique()->comment('PAY-2611-000001');
            $table->unsignedInteger('seller_id');
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 15)->index()->comment('scheduled, on_hold, processing, paid, failed, reversed');
            $table->string('gateway', 12);
            $table->string('gateway_payout_id', 60)->nullable()->unique();
            $table->string('utr', 40)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->char('idempotency_key', 36)->unique();
            $table->json('entry_ids')->nullable()->comment('Ledger entries the payout settles');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('payout_batch_id')->references('id')->on('payout_batch')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
            $table->foreign('bank_account_id')->references('id')->on('seller_bank_account')->nullOnDelete();
            $table->index(['seller_id', 'status']);
        });

        Schema::create('seller_payout_hold', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id');
            $table->string('reason', 500);
            $table->unsignedInteger('placed_by')->nullable();
            $table->timestamp('placed_at');
            $table->unsignedInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_note', 500)->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->index(['seller_id', 'released_at']);
        });

        Schema::create('seller_ledger_adjustment', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id');
            $table->unsignedBigInteger('seller_order_id')->nullable();
            $table->string('direction', 6)->comment('credit, debit');
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->string('status', 10)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_adjustment');
        Schema::dropIfExists('seller_payout_hold');
        Schema::dropIfExists('seller_payout');
        Schema::dropIfExists('payout_batch');
    }
};
