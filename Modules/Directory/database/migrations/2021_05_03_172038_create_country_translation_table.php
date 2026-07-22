<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryTranslation;
use Modules\Directory\Models\DirectoryCountry;

class CreateCountryTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new DirectoryCountryTranslation();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
            $table->integer('directory_country_id')->unsigned();
            $directoryCountry = new DirectoryCountry();
            $table->foreign("directory_country_id")->references("id")->on($directoryCountry->getTable())->onDelete("cascade")->onUpdate("cascade");
            $table->string('name')->nullable()->default(NULL);
            $table->string('locale')->index()->nullable();
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
        $module = new DirectoryCountryTranslation();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->dropForeign(["country_id"]);
        });
        Schema::dropIfExists($module->getTable());
    }
}
