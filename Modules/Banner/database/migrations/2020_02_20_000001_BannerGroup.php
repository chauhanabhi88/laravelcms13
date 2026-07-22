<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Banner\Models\BannerGroup as BannerGroupEntity;

class BannerGroup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bannerGroup = new BannerGroupEntity();
        Schema::create($bannerGroup->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code')->unique()->index();
            $table->tinyInteger('status')->comment("1 = Active, 2 = Inactive");
            $table->integer('sort_order');
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
       $bannerGroup = new BannerGroupEntity();
        Schema::dropIfExists($bannerGroup->getTable());
    }
}
