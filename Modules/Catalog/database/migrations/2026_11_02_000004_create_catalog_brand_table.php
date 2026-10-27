<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brands. A brand requested by a seller (M05) carries requested_by_seller_id and
     * status 2 until an admin approves it. The seller foreign key is added by M03.
     */
    public function up(): void
    {
        Schema::create('catalog_brand', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('slug', 160)->unique();
            $table->string('logo')->nullable();
            $table->tinyInteger('is_verified')->default(2)->comment('1 = Yes, 2 = No');
            $table->unsignedInteger('size_chart_id')->nullable();
            $table->unsignedInteger('requested_by_seller_id')->nullable()->index();
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled or pending request');
            $table->timestamps();

            $table->index('status');
            $table->foreign('size_chart_id')->references('id')->on('catalog_size_chart')->nullOnDelete();
        });

        Schema::create('catalog_brand_translation', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('catalog_brand_id');
            $table->string('locale', 10)->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['catalog_brand_id', 'locale']);
            $table->foreign('catalog_brand_id')->references('id')->on('catalog_brand')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_brand_translation');
        Schema::dropIfExists('catalog_brand');
    }
};
