<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Cron\Models\Cron;

class CreateCronTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $cron = new Cron();
        Schema::create($cron->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('title');
            $table->text('description');
            $table->string('command')->unique()->index();
            $table->string('cron_expression')->nullable();
            $table->tinyInteger('status')->comment("1 = Active, 2 = Inactive");
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
        $cron = new Cron();
        Schema::dropIfExists($cron->getTable());
    }
}