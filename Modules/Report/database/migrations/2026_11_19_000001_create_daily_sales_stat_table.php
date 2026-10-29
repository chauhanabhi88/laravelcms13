<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily sales aggregate (plan M19 §3.1), rebuildable with report:rebuild. Stored grains:
     * platform total (0/0/0/all), platform × L1 category, platform × shipping state, platform ×
     * payment (cod | prepaid) and seller × all. Cohorts: placement date for orders, GMV and
     * cancellations; ship date for shipped/RTO; delivery date for delivered/returns.
     */
    public function up(): void
    {
        Schema::create('daily_sales_stat', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->date('date');
            $table->unsignedInteger('seller_id')->default(0)->comment('0 = platform total');
            $table->unsignedInteger('catalog_category_id')->default(0)->comment('0 = all; L1 category');
            $table->unsignedInteger('state_id')->default(0)->comment('0 = all; shipping state');
            $table->string('payment_method', 10)->default('all')->comment('all | cod | prepaid');

            // Placement cohort.
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('seller_orders')->default(0);
            $table->unsignedInteger('units')->default(0);
            $table->decimal('gmv', 14, 2)->default(0)->comment('Σ price × qty − discount_allocated');
            $table->decimal('net_revenue', 14, 2)->default(0)->comment('Commission + platform fees, excl. GST');
            $table->decimal('discount', 14, 2)->default(0)->comment('Coupon discount allocated to items');
            $table->unsignedInteger('cod_orders')->default(0);
            $table->unsignedInteger('new_customers')->default(0)->comment('Orders that are the customer\'s first');
            $table->unsignedInteger('cancelled_orders')->default(0)->comment('Seller orders cancelled after confirmation');
            $table->decimal('cancelled_gmv', 14, 2)->default(0);
            $table->unsignedInteger('cancelled_by_customer')->default(0);
            $table->unsignedInteger('cancelled_by_seller')->default(0);
            $table->unsignedInteger('cancelled_by_platform')->default(0)->comment('admin + system');
            $table->unsignedInteger('status_open')->default(0)->comment('Seller orders placed this day, now before delivery');
            $table->unsignedInteger('status_delivered')->default(0);
            $table->unsignedInteger('status_returned')->default(0);
            $table->unsignedInteger('status_rto')->default(0);

            // Ship-date cohort.
            $table->unsignedInteger('shipped_orders')->default(0);
            $table->unsignedInteger('rto_orders')->default(0)->comment('RTO initiated or delivered back');

            // Delivery-date cohort.
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('delivered_units')->default(0);
            $table->unsignedInteger('returned_units')->default(0);
            $table->decimal('return_value', 14, 2)->default(0);

            $table->timestamp('aggregated_at')->nullable();

            $table->primary(['date', 'seller_id', 'catalog_category_id', 'state_id', 'payment_method'], 'daily_sales_stat_primary');
            $table->index(['seller_id', 'date']);
            $table->index(['catalog_category_id', 'date']);
            $table->index(['state_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_stat');
    }
};
