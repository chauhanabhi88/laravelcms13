<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryCity;

class CreateDirectoryCountryCityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $directoryCountryCity = new DirectoryCountryCity();
        Schema::create($directoryCountryCity->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string("country", 4)->nullable()->default(NULL);
            $table->string("state", 4)->nullable()->default(NULL);
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
        $directoryCountryCity = new DirectoryCountryCity();
        Schema::dropIfExists($directoryCountryCity->getTable());
    }
}
