<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Block\Models\Block;

class CreateBlockTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $block = new Block;
        Schema::create($block->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->comment('Primary and auto increment id');
            $table->string('slug');
            $table->tinyInteger('is_enabled')->comment("1 = Active, 2 = Inactive");
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
        $block = new Block;
        Schema::drop($block->getTable());
    }
}
