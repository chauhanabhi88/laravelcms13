<?php

namespace Modules\Directory\Http\Controllers\Backend;

use Excel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Http\Requests\CityImportRequest;
use Modules\Directory\Models\DirectoryCountryCity;
use Modules\Directory\Models\DirectoryCountryCityTranslation;
use Modules\Directory\Repositories\DirectoryCountryCityRepository;
use Modules\Directory\Repositories\DirectoryCountryRepository;
use Modules\Directory\Repositories\DirectoryCountryStateRepository;

class CityController extends BackendController
{
    /**
     * @var DirectoryCountryCityRepository
     */
    private $city;

    /**
     * @var DirectoryCountryCity
     */
    private $cityEntity;

    public function __construct(DirectoryCountryCityRepository $city, DirectoryCountryStateRepository $state, DirectoryCountryCity $cityEntity, DirectoryCountryCityTranslation $cityTrans)
    {
        parent::__construct();
        $this->city = $city;
        $this->state = $state;
        $this->cityEntity = $cityEntity;
        $this->cityTrans = $cityTrans;
    }

    /**
     * Export all Cities.
     *
     * @return Response
     */
    public function export(Request $request)
    {
        try {
            $data = $this->city->export($request);
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'name_'.$key;
            }
            $columnNames = array_merge(['country', 'state'], $languages);

            return $this->city->exportData($columnNames, $data, 'Cities.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importSample()
    {
        try {
            $data = $this->city->getSampleImportData();
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'Name_'.$key;
            }
            $columnNames = array_merge(['Country', 'State'], $languages);

            return $this->city->exportData($columnNames, $data, 'Import_Cities_Sample.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Import all Cities.
     *
     * @return Response
     */
    public function import(CityImportRequest $request)
    {
        try {
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'Name '.$key;
            }
            $headers = array_merge(['Country', 'State'], $languages);
            $response = $this->importData($request, $headers);

            return redirect()->route('admin.country.index', updateUrlParams())->with($response['type'], $response['message']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importData($request, $headersPara)
    {
        if (! empty($request->file('city_import_file'))) {
            // Validation for file
            $validator = \Validator::make(
                [
                    'file' => $request->file('city_import_file'),
                    'extension' => strtolower($request->file('city_import_file')->getClientOriginalExtension()),
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
            $importedFile = $request->file('city_import_file');
            $csvData = Excel::toArray([], $importedFile);
            $csvData = $csvData[0];
            $csvHeader = $csvData[0];
            $headers = $headersPara;
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
                $flag = 0;
                $tranlationCount = getIncrementedValue($this->cityEntity->getTable());
                $bulkData = [];
                $bulkTranslationData = [];
                $skipRow = '';
                $countryOptions = app(DirectoryCountryRepository::class)->getCountryOptions();
                $stateOptions = app(DirectoryCountryStateRepository::class)->getStateOptions();
                $stateData = $this->state->where('id', '!=', '')->pluck('country', 'code')->toArray();
                $cityData = $this->city->where('id', '!=', '')->select(DB::raw("CONCAT(country,'-',state) AS countrystate"))->pluck('countrystate')->toArray();
                if (empty($countryOptions)) {
                    return [
                        'type' => 'error',
                        'message' => trans('directory::country.messages.empty_country'),
                    ];
                }
                if (empty($stateOptions)) {
                    return [
                        'type' => 'error',
                        'message' => trans('directory::country.messages.empty_state'),
                    ];
                }
                unset($csvData[0]);

                foreach ($csvData as $key => $data) {
                    $flag = 0;
                    $finalData = array_combine($newHeader, $data);
                    $finalData['state'] = strtoupper(trim($finalData['state'], ''));
                    if (empty($finalData['state'])) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    } elseif (! array_key_exists($finalData['state'], $stateOptions)) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    $finalData['country'] = strtoupper(trim($finalData['country'], ''));
                    if (empty($finalData['country'])) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    } elseif (! array_key_exists($finalData['country'], $countryOptions)) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }

                    if (! array_key_exists($finalData['state'], $stateData) || $stateData[$finalData['state']] != $finalData['country']) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    if (in_array($finalData['country'].'-'.$finalData['state'], $cityData)) {
                        $flag = 1;
                        $skipRow .= $key + 1 .',';

                        continue;
                    }
                    $cityData[] = $finalData['country'].'-'.$finalData['state'];
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
                    $timestamp = $this->cityTrans->freshTimestampString();
                    foreach ($languageOptions as $languageKey => $languageValue) {
                        $bulkTranslationData[] = [
                            'directory_country_city_id' => $tranlationCount,
                            'name' => $finalData['name_'.$languageKey],
                            'locale' => $languageKey,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                        unset($finalData['name_'.$languageKey]);
                    }
                    $tranlationCount++;
                    $bulkData[] = $finalData;
                }
                DB::transaction(function () use ($bulkData, $bulkTranslationData) {
                    $this->city->insert($bulkData);
                    $this->cityTrans->insert(array_map('escapeHtml', $bulkTranslationData));
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
