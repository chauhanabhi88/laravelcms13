<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A seller business (supplier). Logins live in seller_user; status follows the
     * Seller state machine (status history in Core's status_history).
     */
    public function up(): void
    {
        Schema::create('seller', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code', 12)->unique()->comment('S + 6 digits, from SequenceService');
            $table->string('legal_name', 200)->nullable();
            $table->string('store_name', 80)->nullable()->unique()->comment('Public display name, set in onboarding step 1');
            $table->string('store_slug', 100)->nullable()->unique()->comment('/s/{store_slug}');
            $table->string('business_type', 30)->nullable()->comment('proprietorship, partnership, llp, pvt_ltd, individual');
            $table->text('pan')->nullable()->comment('Encrypted');
            $table->string('pan_last4', 4)->nullable();
            $table->string('gstin', 15)->nullable()->unique();
            $table->string('gst_status', 20)->nullable()->comment('unverified, verified, mismatch, not_applicable');
            $table->string('gst_legal_name', 200)->nullable()->comment('Legal name returned by the GSTIN verifier');
            $table->string('enrolment_id', 20)->nullable()->comment('Non-GST sellers');
            $table->unsignedInteger('state_id')->nullable()->comment('directory_state.id from the GSTIN state code');
            $table->string('email')->nullable();
            $table->string('phone', 15);
            $table->string('logo')->nullable();
            $table->string('status', 30)->index()->comment('onboarding, pending_verification, changes_requested, active, rejected, suspended, deactivated');
            $table->tinyInteger('onboarding_step')->default(1)->comment('1..5, resume point of the wizard');
            $table->string('agreement_version', 20)->nullable();
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->string('agreement_accepted_ip', 45)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable()->comment('Admin users.id');
            $table->string('status_reason', 500)->nullable()->comment('Latest reject / changes-requested reason shown to the seller');
            $table->string('suspended_reason', 500)->nullable();
            $table->tinyInteger('auto_accept_orders')->default(2)->comment('1 = Yes, 2 = No (M14)');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('follower_count')->default(0);
            $table->tinyInteger('payout_hold')->default(2)->comment('1 = Yes, 2 = No (M17)');
            $table->timestamps();
            $table->softDeletes();

            $table->index('state_id');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller');
    }
};
