<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountry;

class CreateCountryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new DirectoryCountry();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('code')->nullable()->default(NULL);
			$table->string('iso2_code')->nullable()->default(NULL);
			$table->string('iso3_code')->nullable()->default(NULL);
			$table->tinyInteger('is_allowed_country')->default('2')->comment('1=yes,2=no');
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
        $module = new DirectoryCountry();

        Schema::dropIfExists($module->getTable());

    }
}
