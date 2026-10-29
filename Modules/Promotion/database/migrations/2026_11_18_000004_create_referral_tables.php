<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referrals (FR-PRM-005, plan M18 §3.4). Each customer has one share code (referral_code);
     * a friend who signs up through it gets a referral row, which qualifies on the friend's
     * first delivered order and is rewarded with a personal coupon for the referrer.
     */
    public function up(): void
    {
        Schema::create('referral_code', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('customer_id')->unique();
            $table->string('code', 20)->unique();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customer')->cascadeOnDelete();
        });

        Schema::create('referral', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('referrer_customer_id');
            $table->unsignedInteger('referred_customer_id')->unique()->comment('A customer is referred once');
            $table->string('code', 20);
            $table->string('status', 10)->default('pending')->comment('pending | qualified | rewarded');
            $table->decimal('reward_amount', 12, 2)->default(0);
            $table->unsignedInteger('reward_coupon_id')->nullable();
            $table->unsignedBigInteger('qualified_order_id')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_customer_id', 'status']);
            $table->foreign('referrer_customer_id')->references('id')->on('customer')->cascadeOnDelete();
            $table->foreign('referred_customer_id')->references('id')->on('customer')->cascadeOnDelete();
            $table->foreign('reward_coupon_id')->references('id')->on('coupon')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral');
        Schema::dropIfExists('referral_code');
    }
};
