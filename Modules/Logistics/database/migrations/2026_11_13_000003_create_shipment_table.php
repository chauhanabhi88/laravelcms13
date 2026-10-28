<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shipments (FR-LOG-005): one forward shipment per seller order (v1, one package), and
     * reverse shipments for returns (M15). Charges feed the seller ledger (M17).
     */
    public function up(): void
    {
        Schema::create('shipment', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seller_order_id')->index();
            $table->string('direction', 10)->default('forward')->comment('forward | reverse');
            $table->unsignedBigInteger('return_request_id')->nullable()->comment('Reverse shipments (M15)');
            $table->unsignedInteger('courier_id')->nullable();
            $table->unsignedInteger('courier_rule_id')->nullable();
            $table->string('strategy', 20)->nullable();
            $table->string('carrier_id', 20)->nullable()->comment('Aggregator carrier id');
            $table->string('carrier_name', 100)->nullable();
            $table->string('provider_order_id', 40)->nullable();
            $table->string('provider_shipment_id', 40)->nullable();
            $table->string('awb', 40)->nullable()->unique();
            $table->string('status', 30)->index();
            $table->string('payment_mode', 10)->comment('cod | prepaid');
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->decimal('length_cm', 6, 1)->nullable();
            $table->decimal('breadth_cm', 6, 1)->nullable();
            $table->decimal('height_cm', 6, 1)->nullable();
            $table->unsignedInteger('charged_weight_grams')->nullable();
            $table->decimal('freight_charge', 12, 2)->default(0);
            $table->decimal('cod_charge', 12, 2)->default(0);
            $table->decimal('rto_charge', 12, 2)->default(0);
            $table->string('label_url', 500)->nullable();
            $table->string('manifest_url', 500)->nullable();
            $table->string('invoice_url', 500)->nullable();
            $table->string('pickup_location_code', 100)->nullable();
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('rto_delivered_at')->nullable();
            $table->date('edd')->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Delivery attempts');
            $table->tinyInteger('needs_attention')->default(2)->comment('1 = Yes, 2 = No');
            $table->string('attention_reason', 255)->nullable();
            $table->string('last_status_label', 60)->nullable()->comment('Latest courier scan label');
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->tinyInteger('rto_restocked')->nullable()->comment('Seller confirmation of an RTO: 1 = restocked, 2 = claim');
            $table->timestamps();

            $table->foreign('seller_order_id')->references('id')->on('seller_order')->cascadeOnDelete();
            $table->foreign('courier_id')->references('id')->on('courier')->nullOnDelete();
            $table->index(['direction', 'seller_order_id']);
            $table->index(['status', 'last_synced_at']);
            $table->index(['needs_attention', 'status']);
            $table->index('provider_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment');
    }
};
