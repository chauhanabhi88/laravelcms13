<?php

namespace Modules\Directory\Repositories\Eloquent;

use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Repositories\DirectoryCountryCityRepository;
use Modules\Directory\Repositories\DirectoryCountryRepository;
use Modules\Directory\Repositories\DirectoryCountryStateRepository;

class EloquentDirectoryCountryCityRepository extends EloquentBaseRepository implements DirectoryCountryCityRepository
{
    public function export($request)
    {
        $perPage = $request->get('per_page', settings('core', 'default_per_page'));
        $orderBy = $request->get('order_by', 'id');
        $dir = $request->get('dir', 'DESC');
        $data = [];
        $languages = [];
        $collection = $this->allWithBuilder();

        $collection->orderBy($orderBy, $dir);
        $array = $collection->get();
        $countryOptions = app(DirectoryCountryRepository::class)->getCountryOptions();
        $stateOptions = app(DirectoryCountryStateRepository::class)->getStateOptions();
        if (count($array) > 0) {
            foreach ($array as $key => $value) {
                foreach ($value->translations as $languageValue) {
                    $languages['name_'.$languageValue->locale] = $languageValue->name;
                }
                $data[] = array_merge(['country' => isset($countryOptions) && ! empty($countryOptions) && isset($countryOptions[$value->country]) && ! empty($countryOptions[$value->country]) && isset($value->country) && ! empty($value->country) ?
                $countryOptions[$value->country] : '', 'state' => isset($stateOptions) && ! empty($stateOptions) && isset($stateOptions[$value->state]) && ! empty($stateOptions[$value->state]) && isset($value->state) && ! empty($value->state) ?
                $stateOptions[$value->state] : ''], $languages);
            }
        }

        return $data;
    }

    public function getSampleImportData()
    {
        $data =
            [
                'country' => 'Country Code',
                'state' => 'State Code',
            ];
        $languageOptions = getLanguageOptions();
        $languages = [];
        foreach ($languageOptions as $key => $value) {
            $languages['name_'.$key] = $value.' Name';
        }
        $data = array_merge($data, $languages);

        return [$data];
    }

    public function getAllCountryCity($flag = false)
    {
        $countryCityModel = new DirectoryCountryCity;
        $countryCityData = $countryCityModel->all();
        if ($flag) {
            $countryCityData[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($countryCityData);

        return $countryCityData;
    }

    public function getCountryCities($country, $flag = false)
    {
        $countryCityData = $this->where('country', '=', $country)->with('translations')->get();
        $countryCityData = getNameIdArray($countryCityData);
        if ($flag) {
            $countryCityData[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }
        ksort($countryCityData);

        return $countryCityData;
    }

    public function getCityNameById($id)
    {
        return $this->where('id', $id)->first();
    }
}
