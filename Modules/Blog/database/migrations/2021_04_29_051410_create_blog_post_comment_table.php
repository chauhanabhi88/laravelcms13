<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Blog\Models\BlogPostComment;

class CreateBlogPostCommentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new BlogPostComment();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->integer('post_id')->comment('primarykeyofblog_post')->unsigned();
			$table->foreign("post_id")->references("id")->on("blog_post")->onDelete("cascade")->onUpdate("cascade");
			$table->integer('admin_id')->comment('primarykeyofadmin');
			$table->integer('customer_id')->comment('primarykeyofcustomer');
			$table->text('comment')->nullable()->default(NULL);
			$table->string('subject')->nullable()->default(NULL);
			$table->tinyInteger('status')->default('1')->comment('1=pending,2=approved,3=rejected');
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
        $module = new BlogPostComment();
         Schema::table($module->getTable(), function (Blueprint $table) {
$table->dropForeign(["post_id"]);
});
        Schema::dropIfExists($module->getTable());

    }
}
