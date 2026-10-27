<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_size_chart', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name', 120);
            $table->string('unit', 4)->default('cm')->comment('cm | in');
            $table->json('columns')->comment('measurement names, e.g. ["Bust","Waist","Length"]');
            $table->json('rows')->comment('[{"size":"S","values":[34,28,40]}]');
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1 = Enabled, 2 = Disabled');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_size_chart');
    }
};
