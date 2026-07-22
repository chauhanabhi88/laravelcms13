<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Block\Models\Block;
use Modules\Block\Models\BlockTranslation;

class CreateBlockTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bloclTranslation = new BlockTranslation();
        Schema::create($bloclTranslation->getTable(), function (Blueprint $table) {
            $block = new Block();
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('block_id')->unsigned()->comment('primary_key of block');
            $table->foreign('block_id')->references('id')->on($block->getTable())->onDelete('cascade')->onUpdate('cascade');
            $table->string('title')->index();
            $table->longText('content')->nullable();
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
        $blockTranslation = new BlockTranslation();
        Schema::table($blockTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['block_id']);
        });
        Schema::dropIfExists($blockTranslation->getTable());
    }
}
