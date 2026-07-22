<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Customer\Models\CustomerAddress;
use Modules\Customer\Models\Customer;

class CreateCustomerAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $customerAddress = new CustomerAddress();
        $customerAddressTable = $customerAddress->getTable();

        Schema::create($customerAddressTable, function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('customer_id')->unsigned();
            $table->text('street_name')->nullable();
            $table->string('building')->nullable();
            $table->string('unit_no')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('tag')->nullable();
            $table->tinyInteger('is_default_address')->comment("1 = Yes, 0 = No")->default(0);

            $table->timestamps();

            $customer = new Customer();
            $table->foreign('customer_id')->references('id')->on($customer->getTable())->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $customerAddress = new CustomerAddress();
        $customerAddressTable = $customerAddress->getTable();
        Schema::table($customerAddressTable, function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::dropIfExists($customerAddressTable);
    }
}
