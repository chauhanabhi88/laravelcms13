<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The zero-commission model (plan M17 §3.1/§3.2): one global 0 % commission rule, and every
     * fee code at 0 and charged to the platform.
     */
    public function up(): void
    {
        if (! Schema::hasTable('commission_rule') || ! Schema::hasTable('fee_rule')) {
            return;
        }

        $now = now();
        $from = '2026-01-01';

        if (! DB::table('commission_rule')->where('scope', 'global')->exists()) {
            DB::table('commission_rule')->insert([
                'scope' => 'global',
                'type' => 'percent',
                'value' => 0,
                'applies_to' => 'item_price',
                'effective_from' => $from,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ((array) config('finance.fee_codes', []) ?: $this->codes() as $code) {
            if (DB::table('fee_rule')->where('code', $code)->where('scope', 'global')->exists()) {
                continue;
            }

            DB::table('fee_rule')->insert([
                'code' => $code,
                'scope' => 'global',
                'type' => 'flat',
                'value' => 0,
                'charged_to' => 'platform',
                'status' => 1,
                'effective_from' => $from,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commission_rule')) {
            DB::table('commission_rule')->where('scope', 'global')->where('value', 0)->delete();
        }

        if (Schema::hasTable('fee_rule')) {
            DB::table('fee_rule')->where('scope', 'global')->where('value', 0)->delete();
        }
    }

    /**
     * @return list<string>
     */
    private function codes(): array
    {
        return ['fixed_closing_fee', 'payment_gateway_fee', 'forward_shipping', 'rto_shipping', 'reverse_shipping', 'seller_cancel_penalty', 'sla_breach_penalty'];
    }
};
