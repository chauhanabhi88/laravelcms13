<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse shipments (M15): where the courier collects the return when the customer chose
     * another saved address (same pincode) than the order's shipping address. Null = shipping
     * address.
     */
    public function up(): void
    {
        Schema::table('shipment', function (Blueprint $table) {
            $table->json('pickup_address')->nullable()->after('pickup_location_code');
        });
    }

    public function down(): void
    {
        Schema::table('shipment', function (Blueprint $table) {
            $table->dropColumn('pickup_address');
        });
    }
};
