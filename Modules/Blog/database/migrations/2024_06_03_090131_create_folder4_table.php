<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\Folder4;

class CreateFolder4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new Folder4();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('name' );

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
        $module = new Folder4();

        Schema::dropIfExists($module->getTable());

    }
}
