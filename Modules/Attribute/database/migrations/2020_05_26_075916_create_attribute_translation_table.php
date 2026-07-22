<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Attribute\Models\Attribute as AttributeEntity;
use Modules\Attribute\Models\AttributeTranslation;

class CreateAttributeTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $attributeTranslation = new AttributeTranslation();
        Schema::create($attributeTranslation->getTable(), function (Blueprint $table) {
            $attribute = new AttributeEntity();
            $table->increments('id')->comment('primary and auto increment id');
            $table->integer('attribute_id')->unsigned()->comment('primary_key of attribute');
            $table->foreign('attribute_id')->references('id')->on($attribute->getTable())->onDelete('cascade')->onUpdate('cascade');
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
        $attributeTranslation = new AttributeTranslation();
        Schema::table($attributeTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
        });
        Schema::dropIfExists($attributeTranslation->getTable());
    }
}
