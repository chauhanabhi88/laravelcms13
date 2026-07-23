<?php

namespace Modules\Directory\Http\Controllers\Backend;

use Excel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Http\Requests\StateImportRequest;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryCountryStateTranslation;
use Modules\Directory\Repositories\DirectoryCountryRepository;
use Modules\Directory\Repositories\DirectoryCountryStateRepository;

class StateController extends BackendController
{
    /**
     * @var DirectoryCountryState
     */
    private $stateEntity;

    private $stateTransEntity;

    public function __construct(DirectoryCountryStateRepository $state, DirectoryCountryState $stateEntity, DirectoryCountryStateTranslation $stateTransEntity)
    {
        parent::__construct();
        $this->state = $state;
        $this->stateEntity = $stateEntity;
        $this->stateTransEntity = $stateTransEntity;
    }

    /**
     * Export all Cities.
     *
     * @return Response
     */
    public function export(Request $request)
    {
        try {
            $data = $this->state->export($request);
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'name_'.$key;
            }
            $columnNames = array_merge(['country', 'code'], $languages);

            return $this->state->exportData($columnNames, $data, 'States.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importSample()
    {
        try {
            $data = $this->state->getSampleImportData();
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'name_'.$key;
            }
            $columnNames = array_merge(['country', 'code'], $languages);

            return $this->state->exportData($columnNames, $data, 'Import_States_Sample.xlsx');
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Import all Cities.
     *
     * @return Response
     */
    public function import(StateImportRequest $request)
    {
        try {
            $languageOptions = getLanguageOptions();
            $languages = [];
            foreach ($languageOptions as $key => $value) {
                $languages[] = 'Name '.$key;
            }
            $headers = array_merge(['Country', 'Code'], $languages);
            $response = $this->importData($request, $headers);

            return redirect()->route('admin.country.index', updateUrlParams())->with($response['type'], $response['message']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.country.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function importData($request, $headersPara)
    {
        if (! empty($request->file('state_import_file'))) {
            // Validation for file
            $validator = \Validator::make(
                [
                    'file' => $request->file('state_import_file'),
                    'extension' => strtolower($request->file('state_import_file')->getClientOriginalExtension()),
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
            $importedFile = $request->file('state_import_file');
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
                $tranlationCount = getIncrementedValue($this->stateEntity->getTable());
                $bulkData = [];
                $bulkTranslationData = [];
                $skipRow = '';
                $codeData = $this->state->where('id', '!=', '')->pluck('code')->toArray();
                $countryOptions = app(DirectoryCountryRepository::class)->getCountryOptions();
                if (empty($countryOptions)) {
                    return [
                        'type' => 'error',
                        'message' => trans('directory::country.messages.empty_country'),
                    ];
                }
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
                    $timestamp = $this->stateTransEntity->freshTimestampString();
                    foreach ($languageOptions as $languageKey => $languageValue) {
                        $bulkTranslationData[] = [
                            'directory_country_state_id' => $tranlationCount,
                            'name' => $finalData['name_'.$languageKey],
                            'locale' => $languageKey,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                        unset($finalData['name_'.$languageKey]);
                    }
                    $tranlationCount++;
                    $codeData[] = $finalData['code'];
                    $bulkData[] = $finalData;
                }
                DB::transaction(function () use ($bulkData, $bulkTranslationData) {
                    $this->state->insert($bulkData);
                    $this->stateTransEntity->insert(array_map('escapeHtml', $bulkTranslationData));
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

    public function getStateCities(Request $request)
    {
        $stateName = $request->get('state');
        $selCity = $request->get('city');
        $stateCities = getStateCities($stateName);

        $stateCitiesOptions = '<option value> -- '.trans('directory::directory.labels.select_city').' -- </option>';
        if (isset($stateCities) && ! empty($stateCities)) {
            foreach ($stateCities as $key => $value) {
                if ($selCity == $value->id) {
                    $option = '<option value='.$value->id.' selected>'.e($value->name).'</option>';
                } else {

                    $option = '<option value='.$value->id.'>'.e($value->name).'</option>';
                }
                $stateCitiesOptions = $stateCitiesOptions.$option;
            }
        }

        return response()->json(['stateCities' => $stateCitiesOptions]);
    }
}
