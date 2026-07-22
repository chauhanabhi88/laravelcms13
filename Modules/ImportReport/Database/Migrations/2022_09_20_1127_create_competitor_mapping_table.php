<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\ImportReport\Entities\CompetitorMapping;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $competitor = new CompetitorMapping();
        $competitorTable =  $competitor->getTable();
        
        Schema::create($competitorTable, function (Blueprint $table) {
            $table->increments('id');
            $table->string('sku')->nullable();
            $table->string('ref_sku')->nullable();
            $table->string('ref_url')->nullable();
            $table->string('ref_name')->nullable();
            $table->integer('ref_product_exists')->nullable();
            $table->integer('ignor')->nullable();
            $table->integer('send_in_feed')->nullable();
            $table->integer('priority')->nullable();
            $table->integer('piece_multiplier')->nullable();
            $table->integer('piece_count')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('name')->nullable();
            $table->string('brand_value')->nullable();
            $table->string('mpn')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $competitor = new CompetitorMapping();
        $competitorTable =  $competitor->getTable();
        Schema::dropIfExists($competitorTable);
    }
}


