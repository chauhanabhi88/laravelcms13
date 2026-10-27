<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CLIENT_NAME = 'Customer App Personal Access Client';

    private const COMMAND = 'customer:process-deletions';

    /**
     * 1. The Passport personal access client for the "customer" provider. The mobile OTP API
     *    (POST /api/v1/customer/otp/verify) issues tokens through it. Written for both the legacy
     *    oauth_clients columns (personal_access_client flag) and the Passport 13 shape (grant_types).
     *    Signing keys still come from `php artisan passport:keys`.
     * 2. The Cron row that anonymises accounts whose deletion grace period is over (FR-CUS-008).
     */
    public function up(): void
    {
        if (Schema::hasTable('oauth_clients') && ! DB::table('oauth_clients')->where('name', self::CLIENT_NAME)->exists()) {
            $columns = Schema::getColumnListing('oauth_clients');
            $now = now();
            $row = [
                'id' => (string) Str::orderedUuid(),
                'name' => self::CLIENT_NAME,
                'secret' => Hash::make(Str::random(40)),
                'provider' => 'customer',
                'revoked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (in_array('grant_types', $columns, true)) {
                $row['grant_types'] = json_encode(['personal_access']);
                $row['redirect_uris'] = json_encode([]);
            } else {
                $row['user_id'] = null;
                $row['redirect'] = '';
                $row['personal_access_client'] = 1;
                $row['password_client'] = 0;
            }

            DB::table('oauth_clients')->insert(array_intersect_key($row, array_flip($columns)));
        }

        if (Schema::hasTable('cron') && ! DB::table('cron')->where('command', self::COMMAND)->exists()) {
            DB::table('cron')->insert([
                'title' => 'Process customer account deletions',
                'description' => 'Anonymises customer accounts whose deletion request has passed its grace period.',
                'command' => self::COMMAND,
                'cron_expression' => '30 2 * * *',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('oauth_clients')) {
            DB::table('oauth_clients')->where('name', self::CLIENT_NAME)->delete();
        }

        if (Schema::hasTable('cron')) {
            DB::table('cron')->where('command', self::COMMAND)->delete();
        }
    }
};
