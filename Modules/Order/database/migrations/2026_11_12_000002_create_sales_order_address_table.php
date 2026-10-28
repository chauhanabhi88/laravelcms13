<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shipping and billing address snapshots of a sales order. For a reseller order the
     * shipping address is the end customer's (is_end_customer = 1); billing stays the reseller's.
     */
    public function up(): void
    {
        Schema::create('sales_order_address', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sales_order_id');
            $table->string('type', 10)->comment('shipping | billing');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address_line1', 200);
            $table->string('address_line2', 200)->nullable();
            $table->string('landmark', 120)->nullable();
            $table->string('pincode', 6);
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name', 100)->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->string('state_name', 100)->nullable();
            $table->tinyInteger('is_end_customer')->default(2)->comment('1 = Yes (reseller end customer), 2 = No');
            $table->timestamp('created_at')->nullable();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
            $table->unique(['sales_order_id', 'type']);
            $table->index('pincode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_address');
    }
};
