<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions (M18) on order items: who funds the coupon share (the seller-funded part lowers
     * the seller's sale credit, M17) and the flash-deal row the item was sold under (its units
     * go back to the deal when the item is cancelled).
     */
    public function up(): void
    {
        Schema::table('sales_order_item', function (Blueprint $table) {
            $table->string('discount_funded_by', 10)->nullable()->after('discount_allocated')->comment('platform | seller | mixed');
            $table->decimal('seller_discount', 12, 2)->default(0)->after('discount_funded_by')->comment('Seller-funded part of discount_allocated');
            $table->unsignedInteger('flash_deal_item_id')->nullable()->after('seller_discount');

            $table->index('flash_deal_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_item', function (Blueprint $table) {
            $table->dropIndex(['flash_deal_item_id']);
            $table->dropColumn(['discount_funded_by', 'seller_discount', 'flash_deal_item_id']);
        });
    }
};
