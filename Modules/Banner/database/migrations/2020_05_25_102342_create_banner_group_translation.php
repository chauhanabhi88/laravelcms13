<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Banner\Models\BannerGroup;
use Modules\Banner\Models\BannerGroupTranslation;

class CreateBannerGroupTranslation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bannerGroupTranslation = new  BannerGroupTranslation();
        Schema::create($bannerGroupTranslation->getTable(), function (Blueprint $table) {
            $bannerGroup = new BannerGroup();
            $table->increments('id')->comment('primary and auto increment id');
             $table->integer('banner_group_id')->unsigned()->comment('primary_key of banner_group');
            $table->foreign('banner_group_id')->references('id')->on($bannerGroup->getTable())->onDelete('cascade')->onUpdate('cascade');
            $table->string('name');
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
        $bannerGroupTranslation = new BannerGroupTranslation();
        Schema::table($bannerGroupTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['banner_group_id']);
        });
        Schema::dropIfExists($bannerGroupTranslation->getTable());
    }
}
