<?php

namespace Modules\Directory\Http\Controllers\Backend;

use Excel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Http\Requests\CountryImportRequest;
use Modules\Directory\Http\Requests\CreateRequest;
use Modules\Directory\Models\DirectoryCountry;
use Modules\Directory\Models\DirectoryCountryTranslation;
use Modules\Directory\Repositories\DirectoryCountryCityRepository;
use Modules\Directory\Repositories\DirectoryCountryRepository;

class CountryController extends BackendController
{
    /**
     * @var LanguageRepository
     */
    private $country;

    /**
     * @var UserEntity
     */
    private $countryEntity;

    private $countryTransEntity;

    public function __construct(DirectoryCountryRepository $country, DirectoryCountryCityRepository $city, DirectoryCountry $countryEntity, DirectoryCountryTranslation $countryTransEntity)
    {
        parent::__construct();
        $this->country = $country;
        $this->city = $city;
        $this->countryEntity = $countryEntity;
        $this->countryTransEntity = $countryTransEntity;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $this->_assetManager->addAsset('modules/theme/backend/select2/css/select2.min.css');
            $this->_assetManager->addAsset('modules/theme/backend/select2-bootstrap4-theme/select2-bootstrap4.min.css');
            $this->_assetManager->addAsset('modules/theme/backend/select2/js/select2.full.min.js');
            $countryOptions = $this->country->getCountryOptions();
            $allowedCountryOptions = $this->country->getAllowedCountriesCode();

            return view('directory::backend.country.index', compact('request', 'countryOptions', 'allowedCountryOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Save a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function save(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $this->countryEntity->whereIn('code', $params['country'])->update(['is_allowed_country' => config('core.yes')]);
            $this->countryEntity->whereNotIn('code', $params['country'])->update(['is_allowed_country' => config('core.no')]);
            $this->country->flushCache(config('directory.cache.country'));

            return redirect()->route('admin.country.index', updateUrlParams())->with('success', trans('directory::country.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function getCities(Request $request)
    {
        try {
            $data = $this->city->getCountryCities($request->get('country'));

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getStates(Request $request)
    {
        $selCountry = $request->get('country');
        $selState = $request->get('state');
        $states = getCountryStates($selCountry);
        $stateOptions = '<option value >-- '.trans('directory::directory.labels.select_state').' -- </option>';
        if (isset($states) && ! empty($states)) {
            foreach ($states as $key => $value) {
                if ($key == $value->id) {
                    $option = "<option value='".$value->id."' selected>".e($value->name).'</option>';
                } else {
                    $option = "<option value='".$value->id."' >".e($value->name).'</option>';

                }
                $stateOptions = $stateOptions.$option;
            }
        }

        return response()->json(['states' => $stateOptions]);
    }

    /**
     * Export all country.
     *
     * @return Response
     */
    public function export(Request $request)
    {
        try {
            $data = $this->country->export($request);
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'name_'.$key;
            }
            $columnNames = array_merge(['code', 'iso2_code', 'iso3_code'], $languages);

            return $this->country->exportData($columnNames, $data, 'Countries.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importSample()
    {
        try {
            $data = $this->country->getSampleImportData();
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'name_'.$key;
            }
            $columnNames = array_merge(['code', 'iso2_code', 'iso3_code'], $languages);

            return $this->country->exportData($columnNames, $data, 'Import_Countries_Sample.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function import(CountryImportRequest $request)
    {
        try {
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'Name '.$key;
            }
            $headers = array_merge(['Code', 'Iso2 code', 'Iso3 code'], $languages);
            $response = $this->importData($request, $headers);

            return redirect()->route('admin.country.index', updateUrlParams())->with($response['type'], $response['message']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importData($request, $headersPara)
    {
        if (! empty($request->file('country_import_file'))) {
            // Validation for file
            $validator = Validator::make(
                [
                    'file' => $request->file('country_import_file'),
                    'extension' => strtolower($request->file('country_import_file')->getClientOriginalExtension()),
                ],
                [
                    'file' => 'required',
                    'extension' => 'required|in:xlsx',
                ],
                [
                    'extension.in' => trans('core::core.import_xlsx_modal.xlsx_only'),
                ]
            );
            if ($validator->fails()) {
                return [
                    'type' => 'error',
                    'message' => trans('core::core.import_xlsx_modal.xlsx_only'),
                ];
            }
            // end validation for xlsx file
            $importedFile = $request->file('country_import_file');
            $csvData = Excel::toArray([], $importedFile);
            $csvData = $csvData[0];
            $csvHeader = $csvData[0];
            $headers = $headersPara;
            $flag = 0;
            $newHeader = [];
            $headerDiff = array_diff($csvHeader, $headers);
            if (isset($csvHeader) && ! empty($csvHeader)) {
                foreach ($csvHeader as $column) {
                    $newHeader[] = strtolower(str_replace(' ', '_', $column));
                }
            }
            if (! empty($headerDiff)) {
                return [
                    'type' => 'error',
                    'message' => trans('core::core.import_xlsx_modal.mentioned_headers'),
                ];
            }
            if (! empty($csvData)) {
                $tranlationCount = getIncrementedValue($this->countryEntity->getTable());
                $bulkData = [];
                $bulkTranslationData = [];
                $skipRow = '';
                $codeData = $this->country->where('id', '!=', '')->pluck('code')->toArray();

                unset($csvData[0]);

                foreach ($csvData as $key => $data) {
                    $flag = 0;
                    $finalData = array_combine($newHeader, $data);
                    $finalData['code'] = strtoupper(trim($finalData['code'], ''));
                    if (empty($finalData['code'])) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    } elseif (in_array($finalData['code'], $codeData)) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    if (empty($finalData['iso2_code'])) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    if (empty($finalData['iso3_code'])) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    $languageOptions = getLanguageOptions();
                    foreach ($languageOptions as $languageKey => $languageValue) {
                        if (! array_key_exists('name_'.$languageKey, $finalData)) {
                            $flag = 1;
                            $skipRow .= $key + 1 .',';
                            break;
                        } elseif (empty($finalData['name_'.$languageKey])) {
                            $flag = 1;
                            $skipRow .= $key + 1 .',';
                            break;
                        }
                    }
                    if ($flag == 1) {
                        continue;
                    }
                    $timestamp = $this->countryTransEntity->freshTimestampString();
                    foreach ($languageOptions as $languageKey => $languageValue) {
                        $bulkTranslationData[] = [
                            'directory_country_id' => $tranlationCount,
                            'name' => $finalData['name_'.$languageKey],
                            'locale' => $languageKey,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                        unset($finalData['name_'.$languageKey]);
                    }
                    $tranlationCount++;

                    $finalData['is_allowed_country'] = config('core.enabled');

                    $codeData[] = $finalData['code'];
                    $bulkData[] = $finalData;
                }
                DB::transaction(function () use ($bulkData, $bulkTranslationData) {
                    $this->country->insert($bulkData);
                    $this->countryTransEntity->insert(array_map('escapeHtml', $bulkTranslationData));
                });
            }
            $row_message = (! empty($skipRow)) ? trans('core::core.import_xlsx_modal.skip_row_no').$skipRow : '';
            if ($row_message) {
                return [
                    'type' => 'success',
                    'message' => trans('core::core.import_xlsx_modal.imported').rtrim($row_message, ','),
                ];
            } elseif (empty($bulkData)) {
                return [
                    'type' => 'success',
                    'message' => trans('core::core.import_xlsx_modal.no_insert_data').rtrim($row_message, ','),
                ];
            } else {
                return [
                    'type' => 'success',
                    'message' => trans('core::core.import_xlsx_modal.imported').rtrim($row_message, ','),
                ];
            }
        }
    }
}
