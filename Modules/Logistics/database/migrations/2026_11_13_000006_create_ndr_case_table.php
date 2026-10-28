<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Failed delivery attempts waiting for an action (FR-LOG-008): one open case per shipment.
     */
    public function up(): void
    {
        Schema::create('ndr_case', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shipment_id');
            $table->string('reason', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->string('customer_action', 20)->nullable()->comment('reattempt | change_address | cancel');
            $table->json('action_payload')->nullable();
            $table->string('status', 10)->default('open')->comment('open | actioned | closed');
            $table->string('actioned_by', 40)->nullable()->comment('customer, admin:<id>, system');
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipment')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['shipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ndr_case');
    }
};
