<?php

use Modules\Settings\Repositories\SettingsRepository;

if(!function_exists('settings'))
{
    function settings($module, $param = NULL)
    {
        $settings = app(SettingsRepository::class);
        $settings = $settings->getModuleSettings($module);
        if($settings){
            $settingData = json_decode($settings->value, true);
            if(!$param){
                return $settingData;
            }
            
            return isset($settingData[$param]) ? $settingData[$param] : NULL;
        }
    }
}
