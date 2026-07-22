<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogPost;

class CreateBlogPostTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogPost();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('slug');
			$table->string('image')->nullable()->default(NULL);
			$table->string('author')->nullable()->default(NULL);
			$table->dateTime('post_date')->nullable()->default(NULL);
			$table->tinyInteger('is_featured')->default('2')->comment('1=Yes,2=No');
			$table->tinyInteger('status')->default('2')->comment('1=Enabled,2=Disabled');
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
        $module = new BlogPost();
         
        Schema::dropIfExists($module->getTable());

    }
}
