<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCurrencySetup;


class CreateDirectoryCurrencySetupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $directoryCurrencySetup = new DirectoryCurrencySetup();
        Schema::create($directoryCurrencySetup->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string('code',4);
            $table->string('label',50);
            $table->string('symbol',10)->nullable();
            $table->tinyInteger('is_base_currency')->default(2)->comment("1 = Yes, 2 = No");
            $table->tinyInteger('is_allowed_currency')->default(1)->comment("1 = Yes, 2 = No");
            $table->tinyInteger('is_display_currency')->default(2)->comment("1 = Yes, 2 = No");
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
        $directoryCurrencySetup = new DirectoryCurrencySetup();
        Schema::dropIfExists($directoryCurrencySetup->getTable());
    }
}
