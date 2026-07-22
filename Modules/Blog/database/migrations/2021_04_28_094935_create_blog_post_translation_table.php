<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogPostTranslation;

class CreateBlogPostTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogPostTranslation();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->integer('blog_post_id')->unsigned();
			$table->foreign("blog_post_id")->references("id")->on("blog_post")->onDelete("cascade")->onUpdate("cascade");
			$table->string('locale')->index();
			$table->text('title')->nullable()->default(NULL);
			$table->text('short_content')->nullable()->default(NULL);
			$table->longText('content')->nullable()->default(NULL);
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
        $module = new BlogPostTranslation();
         Schema::table($module->getTable(), function (Blueprint $table) {
$table->dropForeign(["blog_post_id"]);
});
        Schema::dropIfExists($module->getTable());

    }
}
