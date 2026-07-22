<?php

use Modules\Theme\Repositories\ThemeRepository; 

if(!function_exists("getThemes"))
{
    function getThemes()
    {
        $themes = app(ThemeRepository::class);
        $themesData = $themes->find(1);
        if($themesData) {
            return json_decode($themesData["setting"], true);
        }
        return [];
    }
}