<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Mail\Models\MailTemplate;

class CreateMail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $mailTemplate = new MailTemplate();
        Schema::create($mailTemplate->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->string('name')->index()->comment('Index Key');
            $table->string('subject')->index()->comment('Index Key');
            $table->string('slug')->index()->comment('Index Key');
            $table->string('cc')->nullable();
            $table->string('bcc')->nullable();
            $table->text('body');
            $table->tinyInteger('status')->comment('1 = Enable, 2 = Disable')->default(1);

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
        $mailTemplate = new MailTemplate();
        Schema::dropIfExists($mailTemplate->getTable());
    }
}
