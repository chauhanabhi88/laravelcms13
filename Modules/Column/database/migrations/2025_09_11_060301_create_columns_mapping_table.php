<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Column\Models\ColumnsMapping;
use Modules\User\Models\User;
use Modules\Column\Models\Column;
use Modules\Menu\Models\Menu;

class CreateColumnsMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new ColumnsMapping();
        $columns = new Column();
        $user = new User();
        Schema::create($module->getTable(), function (Blueprint $table) use ($columns, $user,) {
            $table->increments('id');
            $table->tinyInteger('checkbox_checked')->default('1');
            $table->unsignedInteger('column_id');
            $table->unsignedInteger('admin_id');

            $table->foreign('column_id')
                ->references('id')
                ->on($columns->getTable())
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('admin_id')
                ->references('id')
                ->on($user->getTable())
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            
            $table->unique(['column_id', 'admin_id']);
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
        $module = new ColumnsMapping();

        Schema::dropIfExists($module->getTable());

    }
}
