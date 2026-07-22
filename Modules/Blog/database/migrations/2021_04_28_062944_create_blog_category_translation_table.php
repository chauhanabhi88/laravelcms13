<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogCategoryTranslation;

class CreateBlogCategoryTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogCategoryTranslation();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->integer('blog_category_id')->unsigned();
			$table->foreign("blog_category_id")->references("id")->on("blog_category")->onDelete("cascade")->onUpdate("cascade");
			$table->string('locale')->index();
			$table->string('title')->nullable()->default(NULL);
			$table->text('description')->nullable()->default(NULL);
			$table->string('meta_keywords')->nullable()->default(NULL);
			$table->text('meta_description')->nullable()->default(NULL);

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
        $module = new BlogCategoryTranslation();
         Schema::table($module->getTable(), function (Blueprint $table) {
$table->dropForeign(["blog_category_id"]);
});
        Schema::dropIfExists($module->getTable());

    }
}
