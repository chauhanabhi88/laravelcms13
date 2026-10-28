<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pickup batches (FR-FUL-005, plan M14 §3.1): packed shipments of one seller warehouse and
     * one courier handed over on a pickup date, with the manifest. shipment.pickup_batch_id
     * links the shipments.
     */
    public function up(): void
    {
        Schema::create('pickup_batch', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('seller_id')->index();
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->unsignedInteger('courier_id')->nullable();
            $table->string('carrier_name', 100)->nullable();
            $table->date('pickup_date');
            $table->string('manifest_no', 30)->unique();
            $table->string('manifest_url', 500)->nullable()->comment('Courier manifest');
            $table->string('pickup_reference', 60)->nullable();
            $table->string('status', 20)->default('open')->comment('open | manifested | picked_up | partially_picked');
            $table->unsignedSmallInteger('shipments_count')->default(0);
            $table->unsignedInteger('created_by')->nullable()->comment('seller_user id');
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->foreign('courier_id')->references('id')->on('courier')->nullOnDelete();
            $table->index(['status', 'pickup_date']);
        });

        Schema::table('shipment', function (Blueprint $table) {
            $table->unsignedBigInteger('pickup_batch_id')->nullable()->after('courier_rule_id');
            $table->foreign('pickup_batch_id')->references('id')->on('pickup_batch')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipment', function (Blueprint $table) {
            $table->dropForeign(['pickup_batch_id']);
            $table->dropColumn('pickup_batch_id');
        });

        Schema::dropIfExists('pickup_batch');
    }
};
