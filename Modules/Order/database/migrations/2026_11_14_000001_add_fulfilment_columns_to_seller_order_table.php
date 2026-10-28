<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seller order desk state (plan M14 §5): the label print time (packing needs a label), the
     * SLA warning sent once, AWB booking retries after acceptance, and the seller's receipt of
     * an RTO package (condition, note, claim photos). Plus the desk's per-seller tab index.
     */
    public function up(): void
    {
        Schema::table('seller_order', function (Blueprint $table) {
            $table->timestamp('label_generated_at')->nullable()->after('packed_at');
            $table->timestamp('sla_warned_at')->nullable()->after('sla_dispatch_by');
            $table->unsignedTinyInteger('awb_failures')->default(0)->after('accepted_at')->comment('Failed AWB bookings since acceptance');
            $table->string('receipt_condition', 20)->nullable()->comment('RTO receipt: good | damaged | wrong_item');
            $table->timestamp('receipt_confirmed_at')->nullable();
            $table->string('receipt_confirmed_by', 20)->nullable()->comment('seller | system');
            $table->string('receipt_note', 500)->nullable();
            $table->json('receipt_photos')->nullable()->comment('Private-disk paths of claim photos');

            $table->index(['seller_id', 'status'], 'seller_order_seller_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('seller_order', function (Blueprint $table) {
            $table->dropIndex('seller_order_seller_status_index');
            $table->dropColumn(['label_generated_at', 'sla_warned_at', 'awb_failures', 'receipt_condition', 'receipt_confirmed_at', 'receipt_confirmed_by', 'receipt_note', 'receipt_photos']);
        });
    }
};
