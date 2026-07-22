<?php

use Modules\Role\Models\Role;
use Modules\User\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         $module = new User();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->dropForeign('admin_role_id_foreign');
            $table->dropIndex('admin_role_id_foreign');
            $table->dropColumn('role_id');
        });
        Schema::table($module->getTable(), function (Blueprint $table) use ($module) {
            $role = new Role();
            $table->integer('role_id')->unsigned()->nullable()->comment('Foriegn key role table')->after('id');
            $table->foreign('role_id')->references('id')->on($role->getTable())->onDelete('SET NULL')->onUpdate('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $module = new user();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->dropForeign('admin_role_id_foreign');
            $table->dropIndex('admin_role_id_foreign');
            $table->dropColumn('role_id');
        });
        Schema::table($module->getTable(), function (Blueprint $table) use ($module) {
            $role = new Role();
            $table->integer('role_id')->unsigned()->nullable()->comment('Foriegn key role table')->after('id');
            $table->foreign('role_id')->references('id')->on($role->getTable())->onDelete('cascade')->onUpdate('cascade');
        });
    }
}
