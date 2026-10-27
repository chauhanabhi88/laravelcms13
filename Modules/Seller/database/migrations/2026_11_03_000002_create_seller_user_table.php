<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logins of a seller business (provider of the "seller" guard) and their password resets.
     */
    public function up(): void
    {
        Schema::create('seller_user', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('seller_id');
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('phone', 15)->unique()->comment('E.164, e.g. +919876543210');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('owner')->comment('owner, catalog, orders, finance');
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['seller_id', 'role']);
            $table->foreign('seller_id')->references('id')->on('seller')->cascadeOnDelete();
        });

        Schema::create('seller_password_resets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_password_resets');
        Schema::dropIfExists('seller_user');
    }
};
