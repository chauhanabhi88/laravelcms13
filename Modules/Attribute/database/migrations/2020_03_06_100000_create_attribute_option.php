<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption as AttributeOptionEntity;

class CreateAttributeOption extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $AttributeOption = new AttributeOptionEntity();
        Schema::create($AttributeOption->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('attribute_id')->unsigned();
            $table->string('custom_option')->nullable();
            $table->tinyInteger('default');
            $table->integer('sort_order');
            $table->timestamps();
            $table->unique(['attribute_id', 'custom_option']);
            $attribute = new Attribute();
            $table->foreign('attribute_id')->references('id')->on($attribute->getTable())->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $AttributeOptionEntity = new AttributeOptionEntity();
        Schema::table($AttributeOptionEntity->getTable(), function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
        });
        Schema::dropIfExists($AttributeOptionEntity->getTable());
    }
}
