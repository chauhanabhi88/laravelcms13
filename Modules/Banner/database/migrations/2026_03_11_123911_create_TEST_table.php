<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Banner\Models\TEST;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = new TEST();
        Schema::create($module->getTable(), function (Blueprint $table) {
            $table->id();
			$table->string('test' )->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = new TEST();

        Schema::dropIfExists($module->getTable());
    }
};
