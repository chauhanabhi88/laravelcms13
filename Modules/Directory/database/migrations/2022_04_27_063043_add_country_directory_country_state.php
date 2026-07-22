<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryState;


class AddCountryDirectoryCountryState extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $directoryCountrystate = new DirectoryCountryState();
        Schema::table($directoryCountrystate->getTable(),function(Blueprint $table){
            $table->string("country", 8)->nullable()->default(NULL)->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $directoryCountrystate = new DirectoryCountryState();
        Schema::table($directoryCountrystate->getTable(),function(Blueprint $table){
        $table->dropColumn("country");
        });
    }
}
