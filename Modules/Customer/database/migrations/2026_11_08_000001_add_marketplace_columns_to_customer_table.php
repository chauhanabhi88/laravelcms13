<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phone-first accounts (M08): customers created by mobile OTP have no email or password,
     * plus profile, reseller, COD-risk and deletion columns.
     *
     * contact_number is kept (deprecated) and copied to phone where it is a valid Indian
     * mobile that no other customer already holds; collisions stay null and are logged.
     */
    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('phone', 15)->nullable()->unique()->after('email')->comment('E.164, e.g. +919876543210');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('preferred_locale', 5)->nullable()->after('date_of_birth');
            $table->tinyInteger('is_reseller')->default(2)->comment('1 = Yes, 2 = No');
            $table->string('reseller_business_name', 120)->nullable();
            $table->tinyInteger('marketing_opt_in')->default(1)->comment('1 = Yes, 2 = No');
            $table->tinyInteger('cod_blocked')->default(2)->comment('1 = Yes, 2 = No (set by COD risk rules)');
            $table->unsignedSmallInteger('rto_count')->default(0);
            $table->timestamp('deletion_requested_at')->nullable();
        });

        $this->backfillPhones();
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone', 'phone_verified_at', 'gender', 'date_of_birth', 'preferred_locale', 'is_reseller',
                'reseller_business_name', 'marketing_opt_in', 'cod_blocked', 'rto_count', 'deletion_requested_at',
            ]);
        });

        // Accounts created by OTP have no email/password: give them unusable placeholders so the
        // old NOT NULL columns can be restored without deleting anyone.
        DB::table('customer')->whereNull('email')->update(['email' => DB::raw("CONCAT('customer-', id, '@invalid.local')")]);
        DB::table('customer')->whereNull('password')->update(['password' => '!']);

        Schema::table('customer', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }

    private function backfillPhones(): void
    {
        $taken = [];
        $collisions = [];

        DB::table('customer')
            ->whereNotNull('contact_number')
            ->orderBy('id')
            ->select(['id', 'contact_number'])
            ->chunkById(500, function ($customers) use (&$taken, &$collisions): void {
                foreach ($customers as $customer) {
                    $phone = $this->normalise((string) $customer->contact_number);

                    if ($phone === null) {
                        continue;
                    }

                    if (isset($taken[$phone])) {
                        $collisions[] = $customer->id;

                        continue;
                    }

                    $taken[$phone] = true;
                    DB::table('customer')->where('id', $customer->id)->update(['phone' => $phone]);
                }
            });

        if ($collisions !== []) {
            Log::warning('M08 phone backfill: duplicate contact numbers left without a phone.', ['customer_ids' => $collisions]);
        }
    }

    /**
     * Same rule as Modules\Customer\Support\PhoneNumber, inlined so the migration never
     * depends on application code that may change later.
     */
    private function normalise(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? '+91'.$digits : null;
    }
};
