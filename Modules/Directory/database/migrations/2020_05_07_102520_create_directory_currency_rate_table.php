<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCurrencyRate;

class CreateDirectoryCurrencyRateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $directoryCurrencyRate = new DirectoryCurrencyRate();
        Schema::create($directoryCurrencyRate->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string('currency_from',3);
            $table->string('currency_to',3);
            $table->decimal('rate',10,2);
            $table->softDeletes();
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
        $directoryCurrencyRate = new DirectoryCurrencyRate();
        Schema::dropIfExists($directoryCurrencyRate->getTable());
    }
}
