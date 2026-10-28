<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal admin notes on a sales order (FR-ORD-008). Never shown to the customer.
     */
    public function up(): void
    {
        Schema::create('sales_order_note', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedInteger('user_id')->nullable()->comment('Admin who wrote it');
            $table->string('author_name', 100)->nullable();
            $table->text('note');
            $table->timestamp('created_at')->nullable();

            $table->foreign('sales_order_id')->references('id')->on('sales_order')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_note');
    }
};
