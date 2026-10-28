<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cart lines: one row per variant and list (active / saved for later). price_at_add is the
     * selling price when the line was added or last confirmed, so price changes can be flagged.
     */
    public function up(): void
    {
        Schema::create('cart_item', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('cart_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variant_id');
            $table->unsignedInteger('seller_id')->comment('Denormalised for grouping by seller');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('price_at_add', 12, 2);
            $table->unsignedTinyInteger('is_saved_for_later')->default(2)->comment('1 = saved for later, 2 = in cart');
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id', 'is_saved_for_later']);
            $table->index('product_id');
            $table->index('variant_id');
            $table->foreign('cart_id')->references('id')->on('cart')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variant')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item');
    }
};
