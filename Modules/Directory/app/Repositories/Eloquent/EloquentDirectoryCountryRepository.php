<?php

namespace Modules\Directory\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Directory\Repositories\DirectoryCountryRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Repositories\Repository;
use Modules\Directory\Models\DirectoryCountry;
use Session;

class EloquentDirectoryCountryRepository extends EloquentBaseRepository implements DirectoryCountryRepository
{
    public function export($request)
    {
        $perPage = $request->get("per_page", settings("core", "default_per_page"));
        $orderBy = $request->get("order_by", "id");
        $dir = $request->get("dir", "DESC");
        $data = [];
        $languages = [];
        $collection = $this->allWithBuilder();

        $collection->orderBy($orderBy, $dir);
        $array = $collection->get();
        if (count($array) > 0) {
            foreach ($array as $key => $value) {
                foreach ($value->translations as $languageValue) {
                    $languages['name_' . $languageValue->locale] = $languageValue->name;
                }
                $data[] = array_merge(['code' => $value->code,  'iso2_code' => $value->iso2_code, 'iso3_code' => $value->iso3_code], $languages);
            }
        }
        return $data;
    }

    public function getSampleImportData()
    {
        $data =
            [
                'code' => 'Code',
                'iso2_code' => 'ISO2 Code',
                'iso3_code' => 'ISO3 Code'
            ];
        $languageOptions = getLanguageOptions();
        $languages = [];
        foreach ($languageOptions as $key => $value) {
            $languages['name_' . $key] = $value . ' Name';
        }
        $data = array_merge($data, $languages);
        return [$data];
    }


    public function getCountryOptions($flag = false, $active = false)
    {
        $collection = $this->allWithBuilder()->listsTranslations(['name']);
        if ($active) {
            $collection->where('is_allowed_country', config('core.enabled'));
        }
        $countryData = $collection->pluck('name', 'code')->all();
        if ($flag) {
            $countryData[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($countryData);

        return $countryData;
    }

    public function getAllowedCountries($flag = false, $backend = false)
    {

        $finalCountryData = [];

        if ($backend) {
            $countryData = $this->where('is_allowed_country', '=', config('core.yes'))->with('translations')->get();

            $finalCountryData = $this->getAllowedCountriesProcessedData($countryData);
        } else {

            if (empty(Session::get('allowed_countries'))) {

                $countryData = $this->where('is_allowed_country', '=', config('core.yes'))->with('translations')->get();

                // if(isset($countryData) && !empty($countryData)) {
                //     foreach ($countryData as $key => $value) {
                //         $finalCountryData[$value->code] = $value->name;
                //     }
                // }
                $finalCountryData = $this->getAllowedCountriesProcessedData($countryData);
                Session::put('allowed_countries', $finalCountryData);
            } else {

                $finalCountryData = Session::get('allowed_countries');
                if (empty($finalCountryData)) {
                    $countryData = $this->where('is_allowed_country', '=', config('core.yes'))->get();
                    // if(isset($countryData) && !empty($countryData)) {
                    //     foreach ($countryData as $key => $value) {
                    //         $finalCountryData[$value->code] = $value->name;
                    //     }
                    // }
                    $finalCountryData = $this->getAllowedCountriesProcessedData($countryData);
                    Session::put('allowed_countries', $finalCountryData);
                }
            }
        }

        if ($flag) {
            $finalCountryData[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }

        // if(isset($countryData) && !empty($countryData)) {
        //     foreach ($countryData as $key => $value) {
        //         $finalCountryData[$value->code] = $value->name;
        //     }
        // }

        ksort($finalCountryData);
        return $finalCountryData;
    }

    private function getAllowedCountriesProcessedData($countryData = [])
    {
        $finalCountryData = [];
        if (isset($countryData) && !empty($countryData)) {
            foreach ($countryData as $key => $value) {
                $finalCountryData[$value->code] = $value->name;
            }
        }
        return $finalCountryData;
    }

    public function getAllowedCountriesCode()
    {
        return $this->allWithBuilder()->where('is_allowed_country', '=', config("core.yes"))->pluck('code')->toArray();
    }

    public function getCountryList()
    {

        $countryList = [];
        $countries = $this->where('is_allowed_country', '=', config("core.yes"))->get();
        if (isset($countries) && !empty($countries)) {
            foreach ($countries as $key => $country) {
                $countryList[] = $country->name;
            }
        }
        return $countryList;
    }
    public function getCountryNameByCode($code, $display)
    {
        // return $this->where('code',$code)->pluck('name')->first();
        $country = $this->where('code', $code)->first();
        $countryName = [];
        if (isset($country) && !empty($country)) {
            $countryName = $country->getTranslation('en');
        }
        if ($display) {
            $countryName = $country;
        }
        return isset($countryName['name']) ? $countryName['name'] : '';
    }
}
