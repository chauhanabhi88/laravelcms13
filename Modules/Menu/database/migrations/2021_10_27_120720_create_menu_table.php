<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Menu\Models\Menu;

class CreateMenuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new Menu();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->unsigned()->default('0');
			$table->string('label')->nullable()->default(NULL);
			$table->longText('link')->nullable()->default(NULL);
			$table->tinyInteger('link_target')->default('2');
			$table->string('css_class')->nullable()->default(NULL);
            $table->string('icon')->nullable()->default(NULL);
			$table->integer('sort_order')->unsigned()->default('0');
			$table->tinyInteger('is_system')->default('2');
			$table->tinyInteger('status')->default('2');

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
        $module = new Menu();
         
        Schema::dropIfExists($module->getTable());

    }
}
