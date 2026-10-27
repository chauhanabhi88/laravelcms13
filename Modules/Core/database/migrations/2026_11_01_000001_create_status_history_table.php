<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per status change of any model using Modules\Core\Concerns\HasStateMachine.
     */
    public function up(): void
    {
        Schema::create('status_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('model_type', 150);
            $table->unsignedBigInteger('model_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('actor_type', 20)->nullable()->comment('admin, seller, customer, system, courier');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model_type', 'model_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_history');
    }
};
