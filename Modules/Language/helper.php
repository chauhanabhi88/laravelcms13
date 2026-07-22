<?php

use Modules\Language\Repositories\LanguageRepository;

if(!function_exists("getLanguageOptions"))
{
    function getLanguageOptions($flag = false)
    {
        $options = [];
        if($flag) {
            $options[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        $language = app(LanguageRepository::class);
        $options += $language->getLanguageOptions();
        if(count($options)) {
            return $options;
        }
        return [];
    }
}

if(!function_exists("getLanguageUrl"))
{
    function getLanguageUrl($locale)
    {
        $currentLangCode = app()->getLocale();
        return str_replace("/{$currentLangCode}", "/{$locale}", url()->current());
    }
}

if(!function_exists("checkSupportedLocale"))
{
    function checkSupportedLocale($locale)
    {
        $localeOptions = getLanguageOptions();
        if(array_key_exists($locale, $localeOptions)) {
            return true;
        }
        return false;
    }
}