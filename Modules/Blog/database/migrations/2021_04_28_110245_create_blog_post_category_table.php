<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogPostCategory;

class CreateBlogPostCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogPostCategory();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->integer('post_id')->comment('primarykeyofblogpost')->unsigned();
			$table->foreign("post_id")->references("id")->on("blog_post")->onDelete("cascade")->onUpdate("cascade");
			$table->integer('category_id')->comment('primarykeyofblogcategory')->unsigned();
			$table->foreign("category_id")->references("id")->on("blog_category")->onDelete("cascade")->onUpdate("cascade");

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
        $module = new BlogPostCategory();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->dropForeign(["post_id"]);
            $table->dropForeign(["category_id"]);
        });
        Schema::dropIfExists($module->getTable());

    }
}
