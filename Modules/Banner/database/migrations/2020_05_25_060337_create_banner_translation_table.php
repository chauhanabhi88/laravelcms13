<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Banner\Models\BannerTranslation;
use Modules\Banner\Models\Banner as BannerEntity;

class CreateBannerTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bannerTranslation = new BannerTranslation();
        Schema::create($bannerTranslation->getTable(), function (Blueprint $table) {
            $banner = new BannerEntity();
            $table->increments('id')->comment('primary and auto increment id');
            $table->integer('banner_id')->unsigned()->comment('primary_key of banner');
            $table->foreign('banner_id')->references('id')->on($banner->getTable())->onDelete('cascade')->onUpdate('cascade');
            $table->string('title');
            $table->longText('content');
            $table->string('locale')->index();
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
        $bannerTranslation = new BannerTranslation();
        Schema::table($bannerTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['banner_id']);
        });
        Schema::dropIfExists($bannerTranslation->getTable());
    }
}
