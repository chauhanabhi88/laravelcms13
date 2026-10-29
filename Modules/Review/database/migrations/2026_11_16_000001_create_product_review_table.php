<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A verified buyer's rating and review of one delivered order item (plan M16 §3.1). One per
     * order item; only "published" reviews count towards the product and seller aggregates.
     */
    public function up(): void
    {
        Schema::create('product_review', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variant_id')->nullable();
            $table->unsignedBigInteger('sales_order_item_id')->unique();
            $table->unsignedBigInteger('sales_order_id')->index();
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('seller_id');
            $table->unsignedTinyInteger('rating')->comment('1-5');
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('author_name', 80)->comment('Snapshot shown on the storefront: first name + last initial');
            $table->string('variant_label', 100)->nullable()->comment('Snapshot of the ordered variant, e.g. Size M');
            $table->string('status', 12)->default('pending')->comment('pending, published, rejected, hidden');
            $table->json('moderation_flags')->nullable()->comment('Auto-check findings: profanity, link, contact, duplicate, too_short');
            $table->string('rejection_reason', 255)->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);
            $table->unsignedTinyInteger('has_images')->default(2);
            $table->unsignedTinyInteger('is_returned')->default(2)->comment('The item was returned before the review');
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->unsignedInteger('seller_replied_by')->nullable()->comment('seller_user.id');
            $table->unsignedInteger('moderated_by')->nullable()->comment('Admin users.id');
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('product')->cascadeOnDelete();
            $table->foreign('sales_order_item_id')->references('id')->on('sales_order_item')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('seller');
            $table->index(['product_id', 'status', 'published_at'], 'product_review_product_status_published_index');
            $table->index(['seller_id', 'status'], 'product_review_seller_status_index');
            $table->index(['customer_id', 'created_at'], 'product_review_customer_created_index');
            $table->index(['status', 'report_count'], 'product_review_status_reports_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review');
    }
};
