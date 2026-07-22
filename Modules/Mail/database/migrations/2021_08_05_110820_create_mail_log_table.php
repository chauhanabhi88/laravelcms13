<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Mail\Models\MailLog;

class CreateMailLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new MailLog();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->string('from_email')->nullable()->default(NULL);
			$table->string('from_name')->nullable()->default(NULL);
			$table->string('to_email')->nullable()->default(NULL);
            $table->string('to_name')->nullable()->default(NULL);
			$table->string('cc')->nullable()->default(NULL);
			$table->string('bcc')->nullable()->default(NULL);
			$table->string('subject')->nullable()->default(NULL);
			$table->longText('body')->nullable()->default(NULL);
            $table->longText('exception')->nullable()->default(NULL);
            $table->tinyInteger('status')->comment('1 = Success, 2 = Failed')->default(2);
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
        
        $module = new MailLog();
        Schema::dropIfExists($module->getTable());

    }
}
