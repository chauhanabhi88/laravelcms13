<?php

use Modules\Pages\Models\Pages;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Modules\Pages\Models\PagesTranslation;
use Illuminate\Database\Migrations\Migration;

class CreatePagesTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pageTranslation = new PagesTranslation();
        Schema::create($pageTranslation->getTable(), function (Blueprint $table) {
            $page = new Pages();
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('page_id')->unsigned()->comment('primary_key of page');
            $table->foreign('page_id')->references('id')->on($page->getTable())->onDelete('cascade')->onUpdate('cascade');
            $table->string('title')->index();
            $table->text('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('locale');
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
       $pagesTranslation = new PagesTranslation();
        Schema::table($pagesTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['page_id']);
        });
        Schema::dropIfExists($pagesTranslation->getTable());
    }
}
