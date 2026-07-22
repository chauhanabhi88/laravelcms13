<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Attribute\Models\AttributeOptionTranslation;
use Modules\Attribute\Models\AttributeOption;

class CreateAttributeOptionTranslationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $attributeOptionTranslation = new AttributeOptionTranslation();
        Schema::create($attributeOptionTranslation->getTable(), function (Blueprint $table) {
            $attributeOption = new AttributeOption();
            $table->increments('id')->comment('primary and auto increment id');
            $table->integer('attribute_option_id')->unsigned()->comment('primary_key of attribute_option');
            $table->foreign('attribute_option_id')->references('id')->on($attributeOption->getTable())->onDelete('cascade')->onUpdate('cascade');
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
        $attributeOptionTranslation = new AttributeOptionTranslation();
        Schema::table($attributeOptionTranslation->getTable(), function (Blueprint $table) {
            $table->dropForeign(['attribute_option_id']);
        });
        Schema::dropIfExists($attributeOptionTranslation->getTable());
    }
}
