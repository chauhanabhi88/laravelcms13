<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryCityTranslation;
use Modules\Directory\Models\DirectoryCountryCity;

class CreateDirectoryCountryCityTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $translation = new DirectoryCountryCityTranslation();
        $translationTable = $translation->getTable();

        Schema::create($translationTable, function (Blueprint $table) {
            $table->increments('id');
            $table->integer('directory_country_city_id')->unsigned()->comment('primary key of directory_country_city');

            $city = new DirectoryCountryCity();
            $cityTable = $city->getTable();
            $table->foreign('directory_country_city_id', 'city_id')->references('id')->on($cityTable)->onDelete('cascade')->onUpdate('cascade');

            $table->string('name')->nullable()->default(NULL);
            $table->string('locale')->index();
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
        $translation = new DirectoryCountryCityTranslation();
        $translationTable = $translation->getTable();
        Schema::table($translationTable, function (Blueprint $table) {
            $table->dropForeign(['city_id']);
        });
        Schema::dropIfExists($translationTable);   
    }
}
