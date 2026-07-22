<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Pages\Models\Pages as PageEntity;

class CreatePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $page = new PageEntity;
        Schema::create($page->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string('slug');
            $table->boolean('status')->comment("1 = Enable, 2 = Disable")->default(1);
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
        $page = new PageEntity;
        Schema::drop($page->getTable());
    }
}
