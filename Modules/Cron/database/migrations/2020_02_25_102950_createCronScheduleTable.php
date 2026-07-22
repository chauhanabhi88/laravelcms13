<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Cron\Models\CronSchedule;
use Modules\Cron\Models\Cron;

class CreateCronScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $cronSchedule = new CronSchedule();
        Schema::create($cronSchedule->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->increments('id');
            $table->integer('cron_id')->unsigned();
            $table->string('title');
            $table->string('command');
            $table->timestamp('execute_date')->nullable();
            $table->timestamp('finished_date')->nullable();
            $table->string('message')->nullable();
            $table->tinyInteger('status')->comment("1 = Pending, 2 = Success, 3 = Fail");
            $cron = new Cron();
            $table->foreign('cron_id')->references('id')->on($cron->getTable())->onDelete('cascade')->onUpdate('cascade');
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
        $cronSchedule = new CronSchedule();
        Schema::table($cronSchedule->getTable(), function (Blueprint $table) {
            $table->dropForeign(['cron_id']);
        });
        Schema::dropIfExists($cronSchedule->getTable());
    }
}