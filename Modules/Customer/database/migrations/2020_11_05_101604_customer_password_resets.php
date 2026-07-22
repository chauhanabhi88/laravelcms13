<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Modules\Customer\Models\CustomerResetPassword;

class CustomerPasswordResets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $customerResetPassword = new CustomerResetPassword();
        
        Schema::create($customerResetPassword->getTable(), function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $customerResetPassword = new CustomerResetPassword();
        Schema::table($customerResetPassword->getTable(), function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
        Schema::dropIfExists($customerResetPassword->getTable());
    }
}
