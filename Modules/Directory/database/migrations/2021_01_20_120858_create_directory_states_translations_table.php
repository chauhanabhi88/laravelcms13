<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Directory\Models\DirectoryCountryStateTranslation;
use Modules\Directory\Models\DirectoryCountryState;

class CreateDirectoryStatesTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $translation = new DirectoryCountryStateTranslation();
        $translationTable = $translation->getTable();

        Schema::create($translationTable, function (Blueprint $table) {
            $state = new DirectoryCountryState();
            $stateTable = $state->getTable();

            $table->increments('id');
            $table->integer('directory_country_state_id')->unsigned()->comment('primary key of directory_states table');
            $table->foreign('directory_country_state_id')->references('id')->on($stateTable)->onDelete('cascade')->onUpdate('cascade');
            $table->string('name')->nullable()->default(null);
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
        $translation = new DirectoryCountryStateTranslation();
        $translationTable = $translation->getTable();
        Schema::table($translationTable, function (Blueprint $table) {
            $table->dropForeign(['directory_country_state_id']);
        });
        Schema::dropIfExists($translationTable);
    }
}
