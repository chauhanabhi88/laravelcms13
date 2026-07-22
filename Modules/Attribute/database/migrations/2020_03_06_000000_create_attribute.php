<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Attribute\Models\Attribute as AttributeEntity;

class CreateAttribute extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $attribute = new AttributeEntity();
        Schema::create($attribute->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('input_type');
            $table->tinyInteger('custom_value')->comment("1 = Yes, 2 = No");
            $table->tinyInteger('is_required')->comment("1 = Yes, 2 = No");
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
        $attribute = new AttributeEntity();
        Schema::dropIfExists($attribute->getTable());
    }
}
