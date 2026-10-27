<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Models\DirectoryCountryState;

return new class extends Migration
{
    /**
     * India Post pincode master: one row per post office (several offices share a pincode).
     */
    public function up(): void
    {
        Schema::create('directory_pincode', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->char('pincode', 6);
            $table->string('office_name', 150);
            $table->string('district', 100);
            $table->unsignedInteger('state_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->tinyInteger('is_serviceable')->default(1)->comment('1 = yes, 2 = no');
            $table->tinyInteger('cod_allowed')->default(1)->comment('1 = yes, 2 = no');
            $table->string('zone', 10)->nullable()->comment('metro, roi, ne_jk, special');
            $table->timestamps();

            $table->unique(['pincode', 'office_name']);
            $table->index('pincode');
            $table->index('state_id');
            $table->foreign('state_id')->references('id')->on((new DirectoryCountryState)->getTable())->nullOnDelete();
            $table->foreign('city_id')->references('id')->on((new DirectoryCountryCity)->getTable())->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_pincode');
    }
};
