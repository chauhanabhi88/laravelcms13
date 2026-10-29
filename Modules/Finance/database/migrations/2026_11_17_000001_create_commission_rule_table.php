<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission rules (FR-FIN-001): the most specific active rule wins
     * (seller_category > seller > category, nearest ancestor > global). The order item keeps the
     * amount and rule id as they were at placement.
     */
    public function up(): void
    {
        Schema::create('commission_rule', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('scope', 20)->comment('global, category, seller, seller_category');
            $table->unsignedInteger('catalog_category_id')->nullable();
            $table->unsignedInteger('seller_id')->nullable();
            $table->string('type', 20)->default('percent')->comment('percent, flat_per_item');
            $table->decimal('value', 8, 2)->default(0);
            $table->string('applies_to', 20)->default('item_price')->comment('item_price, taxable_value');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 2 = Inactive');
            $table->unsignedInteger('created_by')->nullable()->comment('Admin users.id');
            $table->timestamps();

            $table->index(['scope', 'catalog_category_id', 'seller_id', 'effective_from'], 'commission_rule_lookup_index');
            $table->foreign('catalog_category_id')->references('id')->on('catalog_category')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rule');
    }
};
