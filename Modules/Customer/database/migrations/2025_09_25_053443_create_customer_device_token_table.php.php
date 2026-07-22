<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Customer\Models\CustomerDeviceToken;

class CreateCustomerDeviceTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new CustomerDeviceToken();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->increments('id');
			$table->integer('customer_id')->nullable()->default(NULL)->comment('primarykeyofcustomertable.')->unsigned();
			$table->foreign("customer_id")->references("id")->on("customer")->onDelete("cascade")->onUpdate("cascade");
			$table->string('device_token')->nullable()->default(NULL);
			$table->string('device_type')->nullable()->default(NULL);

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
        $module = new CustomerDeviceToken();
         Schema::table($module->getTable(), function (Blueprint $table) {
$table->dropForeign(["customer_id"]);
});
        Schema::dropIfExists($module->getTable());

    }
}
