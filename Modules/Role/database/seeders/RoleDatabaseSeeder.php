<?php

namespace Modules\Role\Database\Seeders;

use Modules\Role\Models\Role;
use Illuminate\Database\Seeder;

class RoleDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::create([
            'name' => 'Master Admin',
            'slug' => 'master_admin',
            'permissions' => '["admin.banner.index","admin.banner.filters","admin.banner.create","admin.banner.edit","admin.banner.delete","admin.banner.mass_delete","admin.bannergroup.index","admin.bannergroup.filters","admin.bannergroup.store","admin.bannergroup.create","admin.bannergroup.edit","admin.bannergroup.delete","admin.bannergroup.mass_delete","admin.block.index","admin.block.filters","admin.block.create","admin.block.edit","admin.block.delete","admin.block.mass_delete","admin.module.index","admin.module.update","admin.module.create","admin.module.clear_all_cache","admin.cron.index","admin.cron.filters","admin.cron.create","admin.cron.edit","admin.cron.delete","admin.cron_schedule.filters","admin.cron_schedule.delete","admin.cron_schedule.mass_delete","admin.dashboard.index","admin.mail.index","admin.mail.filters","admin.mail.create","admin.mail.edit","admin.mail.delete","admin.mail.mass_delete","admin.member.index","admin.member.filters","admin.member.create","admin.member.edit","admin.member.delete","admin.member.mass_delete","admin.page.index","admin.page.filters","admin.page.create","admin.page.edit","admin.page.delete","admin.page.mass_delete","admin.role.index","admin.role.filters","admin.role.create","admin.role.edit","admin.role.delete","admin.role.mass_delete","admin.settings.index","admin.settings.getModuleSetting","admin.settings.save","admin.theme.index","admin.theme.store","admin.theme.reset","admin.user.index","admin.user.filters","admin.user.create","admin.user.edit","admin.user.delete","admin.user.mass_delete","admin.user.editProfile"]'
        ]);
    }
}
