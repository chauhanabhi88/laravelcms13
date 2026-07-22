<?php

use Modules\Directory\Repositories\DirectoryCountryRepository;
use Modules\Directory\Repositories\DirectoryCountryCityRepository;
use Modules\Directory\Repositories\DirectoryCurrencySetupRepository;
use Modules\Directory\Repositories\DirectoryLanguageRepository;
use Modules\Directory\Repositories\DirectoryCountryStateRepository;

if(!function_exists('getCountryList'))
{ 
    function getCountryList()
    {
        $countryRepo = app(DirectoryCountryRepository::class);
        $countries = $countryRepo->getCountryList();
        return $countries;
    }
}

if(!function_exists('getCurrencies'))
{
    function getCurrencies()
    {
        $currencyRepo = app(DirectoryCurrencySetupRepository::class);
        $currencies = $currencyRepo->getCurrencies();
        return $currencies;
    }
}

if(!function_exists('getAllTourLanguages'))
{
    function getAllTourLanguages()
    {
        $languageRepo = app(DirectoryLanguageRepository::class);
        $tourLanguages = $languageRepo->getAllTourLanguages();
        return $tourLanguages;
    }
}



if(!function_exists('getAllowedCountriesOptions'))
{
    function getAllowedCountriesOptions($select=false, $backend = false)
    {
        
        $countryRepo = app(DirectoryCountryRepository::class);
        return $countryRepo->getAllowedCountries($select, $backend);
    }
}



if(!function_exists('getAllCountryCity'))
{
    function getAllCountryCity()
    {
        $cityRepo = app(DirectoryCountryCityRepository::class);
        $cities = $cityRepo->getAllCountryCity();
        return $cities;
    }
}

if(!function_exists('getCountryNameByCode'))
{
    function getCountryNameByCode($code, $display = false)
    {
       
        $countryRepo = app(DirectoryCountryRepository::class);
        $countryName = $countryRepo->getCountryNameByCode($code, $display);
        return $countryName;
    }
}

if(!function_exists('getCityNameById'))
{
    function getCityNameById($id)
    {
        $cityRepo = app(DirectoryCountryCityRepository::class);
        $cityName = $cityRepo->getCityNameById($id);
        return $cityName;
    }
}

if(!function_exists('getCurrencySymbol'))
{
    function getCurrencySymbol($currencyCode) {

        $currencyRepo = app(DirectoryCurrencySetupRepository::class);
        if(empty(Session::get('currency_symbols'))) {
            $currenciesSymbols = $currencyRepo->all()->pluck('symbol', 'code')->toArray();
            Session::put('currency_symbols', $currenciesSymbols);
        }
        $currenciesSymbols = Session::get('currency_symbols');
        $symbol = (isset($currenciesSymbols[$currencyCode]) && !empty($currenciesSymbols[$currencyCode]) ? $currenciesSymbols[$currencyCode]:null);
        if(is_null($symbol)) {
            return $currencyRepo->where('code','=',$currencyCode)->value('symbol');
        }
        return $symbol;
        // return $currencyRepo->where('code','=',$currencyCode)->value('symbol');
    }
}


if(!function_exists('getCountryStates')) {

    function getCountryStates($country) {

        $stateRepo = app(DirectoryCountryStateRepository::class);
        $countryStates = [];
        if(isset($country) && !empty($country)) {

            $countryStates = $stateRepo->where('country', '=', $country)->with('translations')->get();
            return $countryStates;
        }
        return $countryStates;
    }
}


if(!function_exists('getStateNameById')) {

    function getStateNameById($id, $display = false ) {

        $stateRepo = app(DirectoryCountryStateRepository::class);
        $state = '';

        if(isset($id) && !empty($id)) {
            $state = $stateRepo->find($id);

            if($state) {
                if(!$display) {
                    $state = $state->getTranslation('en'); 
                } 
            }
        }
       
        return (isset($state['name']) && !empty($state['name'])) ? $state['name'] : '';
    }
}


if(!function_exists('getStateCities')) {
    function getStateCities($stateId) {
        $cityRepo = app(DirectoryCountryCityRepository::class);
        $stateName = getStateNameById($stateId);

        $cities = [];
        if(isset($stateName) && !empty($stateName)) {

            $cities = $cityRepo->where('state', '=', $stateName)->with('translations')->get();
           
            return $cities;

        }
        return $cities;
    }
}


if(!function_exists('getNameIdArray')) {

    function getNameIdArray($collection = []) {

        $finalArray = [];
        if(isset($collection) && !empty($collection)) {
            foreach ($collection as $key => $value) {

                if(isset($value->id) && !empty($value->id) && isset($value->name) && !empty($value->name)) {
                    $finalArray[$value->id] = $value->name;
                }
            }
        }

        return $finalArray;
    }
}


if(!function_exists('flushDirectoryCache')) {
    
    function flushDirectoryCache() {
        $repo = app(DirectoryCountryCityRepository::class);
        $repo->flushCache(\config("directory.cache.city"));
        $repo->flushCache(\config("directory.cache.country"));
        $repo->flushCache(\config("directory.cache.state"));
        $repo->flushCache(\config("directory.cache.currency_rate"));
        $repo->flushCache(\config("directory.cache.currency_setup"));
        $repo->flushCache(\config("directory.cache.language"));

    }
}