<?php

namespace Modules\Directory\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Directory\Repositories\DirectoryCountryStateRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Repositories\Repository;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Repositories\DirectoryCountryRepository;

class EloquentDirectoryCountryStateRepository extends EloquentBaseRepository implements DirectoryCountryStateRepository
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
        $countryOptions = app(DirectoryCountryRepository::class)->getCountryOptions();
        if (count($array) > 0) {
            foreach ($array as $key => $value) {
                foreach ($value->translations as $languageValue) {
                    $languages['name_' . $languageValue->locale] = $languageValue->name;
                }
                $data[] = array_merge(['country' => isset($countryOptions) && !empty($countryOptions) && isset($countryOptions[$value->country]) && !empty($countryOptions[$value->country]) && isset($value->country) && !empty($value->country) ?
                    $value->country : '', 'code' => $value->code], $languages);
            }
        }
        return $data;
    }

    public function getSampleImportData()
    {
        $data =
            [
                'country' => 'Country Code',
                'code' => 'Code',
            ];
        $languageOptions = getLanguageOptions();
        $languages = [];
        foreach ($languageOptions as $key => $value) {
            $languages['name_' . $key] = $value . ' Name';
        }
        $data = array_merge($data, $languages);
        return [$data];
    }

    public function getStateOptions($flag = false, $countryCode = [])
    {
        $collection = $this->allWithBuilder()->listsTranslations(['name']);
        if (!empty($countryCode)) {
            $collection->whereIn('country', $countryCode);
        }
        $stateData = $collection->pluck('name', 'code')->all();
        if ($flag) {
            $stateData[''] = ' -- ' . trans('core::core.labels.select') . ' -- ';
        }
        ksort($stateData);

        return $stateData;
    }
}
