<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GST documents (FR-FIN-006): the seller's tax invoice per seller order, credit notes for
     * refunded returns and the platform's monthly fee invoice to the seller. PDFs are rendered
     * in the queue onto the private disk.
     */
    public function up(): void
    {
        Schema::create('tax_document', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('type', 15)->comment('tax_invoice, credit_note, fee_invoice');
            $table->string('number', 40)->unique();
            $table->string('reference_number', 40)->nullable()->comment('Credit note: the original invoice number');
            $table->unsignedInteger('seller_id');
            $table->unsignedBigInteger('seller_order_id')->nullable()->index();
            $table->unsignedBigInteger('return_request_id')->nullable()->unique();
            $table->char('period', 7)->nullable()->comment('YYYY-MM, fee invoices');
            $table->date('document_date');
            $table->decimal('taxable_value', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('lines')->nullable()->comment('Credit note / fee invoice lines');
            $table->string('path', 255)->nullable()->comment('Private disk');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('seller_id')->references('id')->on('seller');
            $table->index(['seller_id', 'type']);
            $table->unique(['seller_id', 'type', 'period'], 'tax_document_seller_type_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_document');
    }
};
