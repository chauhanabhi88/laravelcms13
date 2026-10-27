<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time passwords for customers and sellers. Codes are stored hashed only.
     */
    public function up(): void
    {
        Schema::create('otp_code', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('channel', 10)->comment('sms, email');
            $table->string('identifier', 150)->comment('E.164 phone or lower-cased email');
            $table->string('purpose', 40)->comment('customer_login, seller_register, seller_login, cod_confirm, phone_change');
            $table->string('code_hash', 255);
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['identifier', 'purpose', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_code');
    }
};
