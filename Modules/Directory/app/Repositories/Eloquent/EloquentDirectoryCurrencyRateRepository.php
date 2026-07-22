<?php

namespace Modules\Directory\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Directory\Repositories\DirectoryCurrencyRateRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Repositories\Repository;
use Modules\Directory\Models\DirectoryCurrencyRate;
use Modules\Directory\Models\DirectoryCurrencySetup;

class EloquentDirectoryCurrencyRateRepository extends EloquentBaseRepository implements DirectoryCurrencyRateRepository
{
    public function getCountryCurrencyRate($flag = false)
    {
        $countryRateModel = new DirectoryCurrencyRate();
        $currencyRateData = $countryRateModel->pluck('rate', 'rate')->all();
        if ($flag) {
            $currencyRateData[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($currencyRateData);
        return $currencyRateData;
    }

    public function getAllowedCurrenciesRate()
    {
        $currencySetupModel = new DirectoryCurrencySetup();
        $baseCurrencyRow = $currencySetupModel->where('is_base_currency', '=', config("core.yes"))->first();
        $otherOptions = [];
        $rateData = [];
        if (!empty($baseCurrencyRow)) {
            $otherOptions = $currencySetupModel->where('id', '!=', $baseCurrencyRow->id)->where('is_allowed_currency', '=', config("core.yes"))->pluck('code')->toArray();
            $rateData = $this->where('currency_from', '=', $baseCurrencyRow->code)->whereIn('currency_to', $otherOptions)->pluck('rate', 'currency_to')->toArray();
        }
        if (empty($rateData) && !empty($otherOptions)) {
            foreach ($otherOptions as $key => $value) {
                $rateData[$value] = 0;
            }
        }
        return $rateData;
    }
}
