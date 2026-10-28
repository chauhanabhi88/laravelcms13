<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Courier master (FR-LOG-002). With the Shiprocket aggregator one row covers 25+ carriers;
     * the actual carrier is stored per shipment. Seeds the "shiprocket" and "manual" couriers.
     */
    public function up(): void
    {
        Schema::create('courier', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('code', 40)->unique()->comment('shiprocket, manual, delhivery_direct…');
            $table->string('name', 100);
            $table->string('driver', 20)->comment('shiprocket | manual | fake');
            $table->text('credentials')->nullable()->comment('Encrypted JSON; blank = .env');
            $table->tinyInteger('supports_cod')->default(1)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('supports_reverse')->default(1)->comment('1 = Yes, 2 = No');
            $table->integer('priority')->default(0);
            $table->tinyInteger('status')->default(1)->comment('1 = Active, 2 = Inactive');
            $table->timestamps();
        });

        $now = now();

        DB::table('courier')->insert([
            ['code' => 'shiprocket', 'name' => 'Shiprocket', 'driver' => 'shiprocket', 'credentials' => null, 'supports_cod' => 1, 'supports_reverse' => 1, 'priority' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manual', 'name' => 'Manual (Logistics Ops)', 'driver' => 'manual', 'credentials' => null, 'supports_cod' => 1, 'supports_reverse' => 1, 'priority' => 99, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('courier');
    }
};
