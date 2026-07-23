<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Settings\Models\Settings;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Settings::insert([
            [
                'id' => '1',
                'name' => 'banner',
                'value' => '{"max_upload_size":"1","image_type":"jpeg,jpg,png"}',
                'created_at' => null,
                'updated_at' => '2021-12-01 11:33:19',
            ],
            [
                'id' => '2',
                'name' => 'blog',
                'value' => '{"max_upload_size":"1","image_type":"png,jpg,jpeg","min_upload_size":null}',
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => '3',
                'name' => 'core',
                'value' => '{"per_page":"20","default_per_page":"20","timezone":"Africa\/Abidjan","per_page_front_pagination":"2","import_translation_type":null,"escape_html_ignore_column":"body,content,terms_conditions,terms_and_conditions","max_delete_limit":"1000","maintenance_mode_message":null,"view_password":"1","show_google_map":null,"google_map_key":null,"email_verification":"2"}',
                'created_at' => null,
                'updated_at' => '2021-12-01 12:15:06',
            ],
            [
                'id' => '4',
                'name' => 'mail',
                'value' => '{"sender_name":"Alex Paul","sender_email":"alex.paul@gmail.com","recipient_admin_email":"jessy.loran@gmail.com"}',
                'created_at' => null,
                'updated_at' => '2021-12-01 11:35:16',
            ],
            [
                'id' => '6',
                'name' => 'customer',
                'value' => '{"max_upload_size":"1","image_type":"jpeg,jpg,png","min_password_length":"6","max_password_length":"20","ajax_call_after_seconds":null}',
                'created_at' => null,
                'updated_at' => '2021-12-01 11:33:56',
            ],
            [
                'id' => '8',
                'name' => 'cron',
                'value' => '{"cron_schedule_delete_time":"1440"}',
                'created_at' => '2021-09-16 06:22:49',
                'updated_at' => '2021-12-01 11:32:00',
            ],
            [
                'id' => '9',
                'name' => 'directory',
                'value' => '{"import_country_type":"xlsx"}',
                'created_at' => '2021-10-04 07:39:54',
                'updated_at' => '2021-12-01 11:34:08',
            ],
        ]);
    }
}
