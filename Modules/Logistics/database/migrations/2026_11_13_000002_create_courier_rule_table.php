<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Courier assignment rules (FR-LOG-003): the first active rule by priority whose
     * conditions all match picks the courier and the carrier strategy.
     */
    public function up(): void
    {
        Schema::create('courier_rule', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name', 120);
            $table->integer('priority')->default(0)->comment('Lower is evaluated first');
            $table->json('conditions')->nullable()->comment('zones, state_ids, pincode_prefixes, weight_min_g, weight_max_g, payment, seller_ids, category_ids');
            $table->unsignedInteger('courier_id');
            $table->json('preferred_carrier_ids')->nullable()->comment('Aggregator carrier ids in preference order');
            $table->string('strategy', 20)->default('recommended')->comment('cheapest | fastest | recommended | preferred_list');
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 2 = Inactive');
            $table->timestamps();

            $table->foreign('courier_id')->references('id')->on('courier');
            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_rule');
    }
};
