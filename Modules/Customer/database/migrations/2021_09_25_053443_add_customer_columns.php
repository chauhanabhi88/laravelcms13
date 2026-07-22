<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Customer\Models\CustomerLoginLog;
use Modules\Customer\Models\Customer;

class addCustomerColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new Customer();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->string('facebook_id')->nullable();
            $table->string('apple_token')->nullable()->after('status');
            $table->integer('api_version')->nullable();
        });

        $module = new CustomerLoginLog();
        Schema::table($module->getTable(), function (Blueprint $table) {
            $table->boolean('is_loggedin')->default('1');
            $table->string('device');
            $table->string('app_version')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $module = new Customer();
        Schema::table($module->getTable(), function (Blueprint $table) {
			$table->dropColumn('facebook_id');
			$table->dropColumn('apple_token');

        });
        $module = new CustomerLoginLog();
        Schema::table($module->getTable(), function (Blueprint $table) {
			$table->dropColumn('is_loggedin');
			$table->dropColumn('device');
            $table->dropColumn('app_version');
            $table->dropColumn('last_login_at');
        });
    }
}
