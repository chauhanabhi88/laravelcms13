<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Push tokens (FR-NTF-001). The table comes from the Customer module's 2025 migration;
     * this makes each token unique and records when the app last reported it. Environments
     * where that migration never ran get the whole table here.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customer_device_token')) {
            Schema::create('customer_device_token', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('customer_id')->unsigned()->nullable();
                $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade')->onUpdate('cascade');
                $table->string('device_token')->nullable();
                $table->string('device_type')->nullable();
                $table->timestamps();
            });
        }

        // Keep the newest row of any duplicated token before adding the unique index.
        $duplicates = DB::table('customer_device_token')
            ->select('device_token', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('device_token')
            ->groupBy('device_token')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('customer_device_token')
                ->where('device_token', $duplicate->device_token)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('customer_device_token', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('device_type');
            $table->unique('device_token', 'customer_device_token_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_device_token', function (Blueprint $table) {
            $table->dropUnique('customer_device_token_token_unique');
            $table->dropColumn('last_used_at');
        });
    }
};
