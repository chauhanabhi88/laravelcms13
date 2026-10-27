<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KYC uploads. Files live on the private disk and are streamed only after authorisation.
     */
    public function up(): void
    {
        Schema::create('seller_kyc_document', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->string('type', 30)->comment('gst_certificate, pan_card, cancelled_cheque, signature, address_proof, other');
            $table->string('disk', 20)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size')->comment('Bytes');
            $table->string('status', 20)->default('pending')->comment('pending, accepted, rejected');
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'type']);
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_kyc_document');
    }
};
