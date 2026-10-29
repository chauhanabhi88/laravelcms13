<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flash deals (FR-PRM-003, plan M18 §3.3): time-boxed selling prices on variants. A deal unit
     * cap (stock_cap) limits how many units sell at the deal price; after it the price falls back.
     */
    public function up(): void
    {
        Schema::create('flash_deal', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name', 150);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('banner', 255)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_deal_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('flash_deal_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variant_id');
            $table->decimal('deal_price', 12, 2);
            $table->unsignedInteger('stock_cap')->nullable()->comment('Deal units; null = no cap');
            $table->unsignedInteger('sold')->default(0);
            $table->timestamps();

            $table->unique(['flash_deal_id', 'variant_id']);
            $table->index('variant_id');
            $table->index('product_id');
            $table->foreign('flash_deal_id')->references('id')->on('flash_deal')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variant')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_deal_item');
        Schema::dropIfExists('flash_deal');
    }
};
