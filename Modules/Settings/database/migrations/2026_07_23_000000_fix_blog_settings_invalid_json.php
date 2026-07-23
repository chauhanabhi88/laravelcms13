<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixBlogSettingsInvalidJson extends Migration
{
    /**
     * Run the migrations.
     *
     * The seeder originally wrote the 'blog' settings row as invalid,
     * single-quoted pseudo-JSON, so json_decode() silently returns null
     * for it and Blog's module settings are unreadable. Replace it with
     * the same values encoded as valid JSON.
     *
     * @return void
     */
    public function up()
    {
        DB::table('settings')
            ->where('name', 'blog')
            ->update([
                'value' => json_encode([
                    'max_upload_size' => '1',
                    'image_type' => 'png,jpg,jpeg',
                    'min_upload_size' => null,
                ]),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally left as-is: reverting would restore invalid JSON.
    }
}
