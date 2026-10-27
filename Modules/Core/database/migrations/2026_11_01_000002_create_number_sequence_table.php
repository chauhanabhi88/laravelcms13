<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counters behind human-readable numbers (orders, invoices, returns, payouts).
     */
    public function up(): void
    {
        Schema::create('number_sequence', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('key', 80)->unique()->comment('e.g. order:2610, invoice:SELLER123:2026-27');
            $table->string('prefix', 40);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->tinyInteger('padding')->default(6);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequence');
    }
};
