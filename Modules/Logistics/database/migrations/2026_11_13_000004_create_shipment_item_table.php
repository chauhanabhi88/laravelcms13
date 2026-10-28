<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The order lines (and quantities) packed in a shipment.
     */
    public function up(): void
    {
        Schema::create('shipment_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('sales_order_item_id');
            $table->unsignedSmallInteger('quantity');

            $table->primary(['shipment_id', 'sales_order_item_id']);
            $table->foreign('shipment_id')->references('id')->on('shipment')->cascadeOnDelete();
            $table->foreign('sales_order_item_id')->references('id')->on('sales_order_item')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_item');
    }
};
