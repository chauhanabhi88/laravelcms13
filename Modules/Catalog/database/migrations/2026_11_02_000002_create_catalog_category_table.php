<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Category tree with a materialised path ("/1/7/23/") for descendant queries.
     * Commerce defaults left null are inherited from the nearest ancestor (CategoryDefaults).
     */
    public function up(): void
    {
        Schema::create('catalog_category', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable()->comment('null = root');
            $table->string('path', 255)->default('')->comment('materialised path /1/7/23/');
            $table->unsignedTinyInteger('depth')->default(0)->comment('0 = root');
            $table->string('slug', 160)->unique();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->string('banner_image')->nullable();
            $table->tinyInteger('is_leaf')->default(1)->comment('1 = Yes, 2 = No; maintained by CategoryTreeService');
            $table->decimal('gst_percent', 5, 2)->nullable();
            $table->string('hsn_code', 8)->nullable();
            $table->unsignedTinyInteger('return_window_days')->nullable();
            $table->string('return_type', 20)->nullable()->comment('return_exchange | exchange_only | none');
            $table->unsignedSmallInteger('dispatch_sla_hours')->nullable();
            $table->unsignedInteger('size_chart_id')->nullable();
            $table->decimal('min_selling_price', 12, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('show_in_menu')->default(1)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
            $table->index('path');
            $table->index('status');
            $table->foreign('parent_id')->references('id')->on('catalog_category')->restrictOnDelete();
            $table->foreign('size_chart_id')->references('id')->on('catalog_size_chart')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_category');
    }
};
