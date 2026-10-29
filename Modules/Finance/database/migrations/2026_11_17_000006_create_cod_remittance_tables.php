<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COD remittance reconciliation (FR-FIN-008): the courier aggregator's remittances and the
     * AWBs they pay for, matched to delivered COD shipments.
     */
    public function up(): void
    {
        Schema::create('cod_remittance', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('provider', 20)->default('shiprocket');
            $table->string('remittance_ref', 60)->unique();
            $table->date('remittance_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('utr', 40)->nullable();
            $table->string('status', 12)->default('received')->comment('received, reconciled, mismatch');
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('cod_remittance_line', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cod_remittance_id');
            $table->string('awb', 40)->index();
            $table->unsignedBigInteger('shipment_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->tinyInteger('matched')->default(2)->comment('1 = Yes, 2 = No');
            $table->string('issue', 30)->nullable()->comment('unknown_awb, amount_mismatch, not_cod, not_delivered, duplicate');
            $table->timestamps();

            $table->foreign('cod_remittance_id')->references('id')->on('cod_remittance')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_remittance_line');
        Schema::dropIfExists('cod_remittance');
    }
};
