<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Banner\Models\BannerGroup;
use Modules\Banner\Models\Banner as BannerEntity;

class Banner extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $banner = new BannerEntity();
        Schema::create($banner->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('group_id')->unsigned();
            $table->string('image')->nullable();
            $table->string('code')->unique();
            $table->string('url')->nullable();
            $table->tinyInteger('is_featured')->comment("1 = Yes, 2 = No");
            $table->integer('sort_order');
            $table->tinyInteger('status')->comment("1 = Active, 2 = Inactive");
            $table->timestamps();
            
            $bannerGroup = new BannerGroup();
            $table->foreign('group_id')->references('id')->on($bannerGroup->getTable())->onDelete('cascade')->onUpdate('cascade');           
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         $banner = new BannerEntity();
        Schema::table($banner->getTable(), function (Blueprint $table) {
            $table->dropForeign(['group_id']);
        });
        Schema::dropIfExists($banner->getTable());
    }
}
