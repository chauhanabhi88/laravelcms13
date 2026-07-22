<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Dashboard\Models\Demo;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new Demo();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('demo_field' )->nullable()->index()->comment('Test');

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
        $module = new Demo();

        Schema::dropIfExists($module->getTable());

    }
};