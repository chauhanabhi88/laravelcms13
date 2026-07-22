<?php

use Modules\User\Models\User;
use Modules\Role\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $user = new User();
        Schema::create($user->getTable(), function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('role_id')->unsigned();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->tinyInteger('status')->comment("1 = Active, 2 = Inactive");
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $role = new Role();
            $table->foreign('role_id')->references('id')->on($role->getTable())->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $user = new User();
        Schema::table($user->getTable(), function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists($user->getTable());
    }
}
