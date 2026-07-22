<?php

use Modules\Core\Models\Entity;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEntity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $entity = new Entity();
        Schema::create($entity->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->tinyInteger('join_type')->comment("1 = oneToOne, 2 = oneToMany, 3 = manyToOne, 4 = ManyToMany");
            $table->string('base_module');
            $table->string('base_entity');
            $table->string('target_module');
            $table->string('target_entity');
            $table->string('target_foreign_key');
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
        $entity = new Entity();
        Schema::dropIfExists($entity->getTable());
    }
}
