<?php

namespace Modules\Theme\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Theme\Models\Theme;

class ThemeDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {  
        try{
            $theme = new Theme();
            $theme->setting = '{".main-header":"navbar-light navbar-white",".nav-sidebar":"nav-child-indent"}';
            $theme->save();
        }
        catch (\Throwable $e) 
        {
            echo "error", $e->getMessage();
        }
    }
}
