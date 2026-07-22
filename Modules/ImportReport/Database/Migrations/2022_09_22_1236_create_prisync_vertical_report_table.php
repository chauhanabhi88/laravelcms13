<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\ImportReport\Entities\PrisyncVerticalReport;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prisync = new PrisyncVerticalReport();
        $prisyncTable =  $prisync->getTable();
        
        Schema::create($prisyncTable, function (Blueprint $table) {
            $table->increments('id');
            $table->string('product_name')->nullable();
            $table->string('product_code')->nullable();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->string('product_tags')->nullable();
            $table->integer('number_of_matches')->nullable();
            $table->integer('index')->nullable();
            $table->string('position')->nullable();
            $table->string('cheapest_site',1000)->nullable();
            $table->string('highest_site',1000)->nullable();
            $table->integer('minimum_price')->nullable();
            $table->integer('maximum_price')->nullable();
            $table->integer('average_price')->nullable();
            $table->integer('my_price')->nullable();
            $table->integer('product_cost')->nullable();
            $table->string('smart_price')->nullable();
            $table->string('last_update_cycle')->nullable();
            $table->string('site')->nullable();
            $table->integer('site_index')->nullable();
            $table->integer('price')->nullable();
            $table->string('change_direction')->nullable();
            $table->string('stock')->nullable();
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
        $prisync = new PrisyncVerticalReport();
        $prisyncTable =  $prisync->getTable();
        Schema::dropIfExists($prisyncTable);
    }
}


