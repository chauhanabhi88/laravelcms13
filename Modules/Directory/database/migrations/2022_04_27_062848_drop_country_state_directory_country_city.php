<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryCity;

class DropCountryStateDirectoryCountryCity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $directoryCountryCity = new DirectoryCountryCity();
        Schema::table($directoryCountryCity->getTable(),function(Blueprint $table){
        $table->dropColumn(["country","state"]);
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
        Schema::table($directoryCountryCity->getTable(),function(Blueprint $table){
            $table->string("country", 4)->nullable()->default(NULL)->after('id');
            $table->string("state", 4)->nullable()->default(NULL)->after('country');
        });
    }
}
