<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pickup addresses. One default per seller; courier_pickup_ref is filled by M13.
     */
    public function up(): void
    {
        Schema::create('seller_warehouse', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->string('name', 80);
            $table->string('contact_name', 120);
            $table->string('contact_phone', 15);
            $table->string('address_line1', 200);
            $table->string('address_line2', 200)->nullable();
            $table->string('landmark', 120)->nullable();
            $table->char('pincode', 6)->index();
            $table->unsignedInteger('city_id')->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->string('city_name', 120)->nullable()->comment('Snapshot from the pincode master');
            $table->string('state_name', 120)->nullable()->comment('Snapshot from the pincode master');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->tinyInteger('is_default')->default(2)->comment('1 = Yes, 2 = No');
            $table->string('courier_pickup_ref', 100)->nullable()->comment('Courier pickup location name (M13)');
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamps();

            $table->index(['seller_id', 'is_default']);
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_warehouse');
    }
};
