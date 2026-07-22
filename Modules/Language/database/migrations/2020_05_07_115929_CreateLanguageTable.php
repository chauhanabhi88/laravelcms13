<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Language\Models\Language;

class CreateLanguageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $language = new Language();
        Schema::create($language->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('title');
            $table->string('locale');
            $table->string('is_default')->comment("1 = Yes, 2 = No");
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
        $language = new Language();
        Schema::dropIfExists($language->getTable());
    }
}
