<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payout bank accounts. One primary per seller (kept by BankAccountService).
     */
    public function up(): void
    {
        Schema::create('seller_bank_account', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->string('account_holder_name', 120);
            $table->text('account_number')->comment('Encrypted');
            $table->string('account_last4', 4);
            $table->string('ifsc', 11);
            $table->string('bank_name', 120)->nullable();
            $table->string('branch', 150)->nullable();
            $table->string('account_type', 10)->default('savings')->comment('savings, current');
            $table->string('verification_status', 20)->default('pending')->comment('pending, verified, failed, manual_review');
            $table->string('verification_method', 20)->nullable()->comment('penny_drop, cheque');
            $table->string('verified_name', 150)->nullable()->comment('Name returned by the penny drop');
            $table->unsignedTinyInteger('name_match_score')->nullable()->comment('0-100');
            $table->string('provider_ref', 60)->nullable()->comment('RazorpayX validation id (fav_xxx)');
            $table->string('razorpayx_contact_id', 60)->nullable();
            $table->string('razorpayx_fund_account_id', 60)->nullable();
            $table->string('verification_note', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable()->comment('Admin users.id for manual verification');
            $table->tinyInteger('is_primary')->default(2)->comment('1 = Yes, 2 = No');
            $table->unsignedInteger('cheque_document_id')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'is_primary']);
            $table->index('provider_ref');
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
            $table->foreign('cheque_document_id')->references('id')->on('seller_kyc_document')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_bank_account');
    }
};
