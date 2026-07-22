<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryState;

class CreateDirectoryStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new DirectoryCountryState();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string("country",4)->nullable()->default(NULL);
            $table->string("code")->nullable()->default(NULL);
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
        $module = new DirectoryCountryState();
        Schema::dropIfExists($module->getTable());
    }
}
