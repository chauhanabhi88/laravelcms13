<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A seller's dispute of a package that came back damaged, wrong, used or incomplete (plan
     * M15 §3.4): a customer return (return_request_id) or an RTO package confirmed as damaged in
     * the order desk (M14, return_request_id null). Admin decides; M17 credits accepted claims.
     */
    public function up(): void
    {
        Schema::create('return_claim', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('return_request_id')->nullable();
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedInteger('seller_id');
            $table->string('source', 10)->default('return')->comment('return, rto');
            $table->string('reason', 25)->comment('damaged_return, wrong_item_returned, used_item, missing_parts');
            $table->text('description')->nullable();
            $table->json('images')->nullable()->comment('Private-disk paths');
            $table->string('status', 10)->default('open')->index()->comment('open, accepted, rejected');
            $table->decimal('resolution_amount', 12, 2)->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('return_request_id')->references('id')->on('return_request')->cascadeOnDelete();
            $table->foreign('seller_order_id')->references('id')->on('seller_order')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_claim');
    }
};
