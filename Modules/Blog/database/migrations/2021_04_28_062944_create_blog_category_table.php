<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogCategory;

class CreateBlogCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogCategory();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('slug');
			$table->integer('sort_order')->nullable()->default(NULL);
			$table->integer('status')->default('2')->comment('1=Enabled,2=Disabled');
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
        $module = new BlogCategory();
         
        Schema::dropIfExists($module->getTable());

    }
}
