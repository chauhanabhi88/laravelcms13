<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Attribute\Models\AttributeOption;

class AddImage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         
        $attributeOption = new AttributeOption();
        Schema::table($attributeOption->getTable(), function (Blueprint $table) {
            $attributeOption = new AttributeOption();
            $table->string('image')->nullable()->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $attributeOption = new AttributeOption();
        Schema::table($attributeOption->getTable(), function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
}
