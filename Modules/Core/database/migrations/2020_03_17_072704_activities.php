<?php

use Modules\User\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Models\Activities as ActivitiesEntity;

class Activities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try{
            $Activities = new ActivitiesEntity();
            Schema::create($Activities->getTable(), function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('admin_id')->unsigned();
                $table->string('ip_address');
                $table->string('module');
                $table->tinyInteger('action')->comment("1 = add, 2 = update, 3 = delete");
                $table->string('message');
                $table->timestamps();

                $user = new User();
                $table->foreign('admin_id')->references('id')->on($user->getTable())->onDelete('cascade')->onUpdate('cascade');
            });
        }
        catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {  
        try
        {
            $Activities = new ActivitiesEntity();
            Schema::dropIfExists($Activities->getTable());
        }
        catch(\Throwable $e){
            echo $e->getMessage();
        }
    }
}
