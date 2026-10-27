<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products a signed-in customer viewed (recorded by the PDP, M07), trimmed to the latest
     * storefront.recent_views_keep rows per customer. Guests keep theirs in localStorage.
     */
    public function up(): void
    {
        Schema::create('customer_recent_view', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('product_id');
            $table->timestamp('viewed_at');

            $table->primary(['customer_id', 'product_id']);
            $table->index(['customer_id', 'viewed_at']);
            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_recent_view');
    }
};
