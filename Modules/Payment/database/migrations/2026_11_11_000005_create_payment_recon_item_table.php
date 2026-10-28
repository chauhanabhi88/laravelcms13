<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settlement reconciliation mismatches (FR-PAY-008): one row per settled gateway entity
     * that does not match our records. Finance resolves them by hand.
     */
    public function up(): void
    {
        Schema::create('payment_recon_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->date('settlement_date')->index();
            $table->string('entity_type', 20)->comment('payment | refund | other');
            $table->string('entity_id', 40);
            $table->string('settlement_id', 40)->nullable();
            $table->string('issue', 30)->comment('missing_local, amount_mismatch, status_mismatch');
            $table->decimal('gateway_amount', 12, 2)->default(0);
            $table->decimal('local_amount', 12, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'issue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_recon_item');
    }
};
