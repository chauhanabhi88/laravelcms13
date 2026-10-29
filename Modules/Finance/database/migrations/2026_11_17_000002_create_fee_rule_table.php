<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform fees other than commission (plan M17 §3.2), scoped like commission rules. The
     * zero-commission defaults charge shipping to the platform and no penalties.
     */
    public function up(): void
    {
        Schema::create('fee_rule', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code', 30)->comment('fixed_closing_fee, payment_gateway_fee, forward_shipping, rto_shipping, reverse_shipping, seller_cancel_penalty, sla_breach_penalty');
            $table->string('scope', 20)->default('global')->comment('global, category, seller, seller_category');
            $table->unsignedInteger('catalog_category_id')->nullable();
            $table->unsignedInteger('seller_id')->nullable();
            $table->string('type', 10)->default('flat')->comment('percent, flat');
            $table->decimal('value', 8, 2)->default(0);
            $table->string('charged_to', 10)->default('platform')->comment('seller, platform');
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 2 = Inactive');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['code', 'scope', 'effective_from'], 'fee_rule_lookup_index');
            $table->foreign('catalog_category_id')->references('id')->on('catalog_category')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_rule');
    }
};
