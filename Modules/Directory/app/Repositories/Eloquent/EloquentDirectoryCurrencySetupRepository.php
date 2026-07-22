<?php

namespace Modules\Directory\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Directory\Repositories\DirectoryCurrencySetupRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Repositories\Repository;
use Modules\Directory\Models\DirectoryCurrencySetup;
 
class EloquentDirectoryCurrencySetupRepository extends EloquentBaseRepository implements DirectoryCurrencySetupRepository
{
    public function getCountryCurrencySymbol($flag = false)
    {   
        $countrySetupModel = new DirectoryCurrencySetup();
        $currencySymbolData = $countrySetupModel->pluck('symbol','id')->all();
        if($flag) {
            $currencySymbolData[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($currencySymbolData);
        return $currencySymbolData;
    }

    public function getCurrencyOptions($flag = false)
    {   
        $currencySetupModel = new DirectoryCurrencySetup();
        $currencySetupData = $currencySetupModel->pluck('label','label')->all();
        if($flag) {
            $currencySetupData[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($currencySetupData);
        return $currencySetupData;
    }

    public function getCurrencyCode($currency)
    {
        return $currencySetupModel->where('label','=',$currency)->value('code');
    }

    public function getBaseCurrency()
    {
        return $this->where('is_base_currency','=',config("core.yes"))->first();
    }

    public function getDisplayCurrency()
    {
        return $this->where('is_display_currency','=',config("core.yes"))->first();
    }

    public function getAllowedCurrencies()
    {
        return $this->where('is_allowed_currency','=',config("core.yes"))->pluck('label')->toArray();
    }

   public function getAllowedCurrenciesRow()
   {
        return $this->where('is_allowed_currency','=',config('core.yes'))->get();
   }

   public function getCurrencies()
   {
        return $this->where('is_allowed_currency','=',config("core.yes"))->pluck('code')->toArray();
   }

}
