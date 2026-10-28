<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents of a seller order (plan M14 §3.3, shared with M17): our packing slip and tax
     * invoice PDFs on the private disk, and the courier's label as a provider URL.
     */
    public function up(): void
    {
        Schema::create('sales_order_document', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedBigInteger('label_batch_id')->nullable()->index();
            $table->string('type', 20)->comment('label | packing_slip | tax_invoice | credit_note | manifest');
            $table->string('path', 255)->nullable()->comment('Private disk');
            $table->string('url', 500)->nullable()->comment('Provider document');
            $table->string('number', 40)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('seller_order_id')->references('id')->on('seller_order')->cascadeOnDelete();
            $table->foreign('label_batch_id')->references('id')->on('label_batch')->nullOnDelete();
            $table->index(['seller_order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_document');
    }
};
