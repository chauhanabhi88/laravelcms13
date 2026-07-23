<?php

namespace Modules\Directory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Directory\Repositories\DirectoryCountryCityRepository;

class CountryController extends Controller
{
    public function __construct(DirectoryCountryCityRepository $city)
    {
        $this->city = $city;
    }

    public function getCountriesCode(Request $request)
    {
        $allowedCountries = getAllowedCountriesOptions();
        if (! empty($allowedCountries)) {
            return json_encode(array_keys($allowedCountries));
        }

        return json_encode([]);
    }

    public function getCities(Request $request)
    {
        try {
            $params = $request->all();
            $city = (isset($params['city']) && ! empty($params['city'])) ? $params['city'] : '';
            $html = '<option value="">'.trans('directory::country.titles.city').'</option>';
            $countryCityData = $this->city->getCountryCities($params['country']);
            if (! empty($countryCityData)) {
                foreach ($countryCityData as $code => $data) {
                    $selected = '';
                    if ($city == $code) {
                        $selected = 'selected';
                    }
                    $html .= '<option value='.$code.' '.$selected.'>'.e($data).'</option>';
                }
            }

            return response()->json([
                'type' => 'success',
                'html' => $html,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

    }
}
