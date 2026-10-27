<?php

namespace Modules\Directory\Repositories\Eloquent;

use Illuminate\Support\Arr;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;
use Modules\Directory\Support\DirectorySettings;

class EloquentDirectoryPincodeRepository extends EloquentBaseRepository implements DirectoryPincodeRepository
{
    private const SORTABLE = ['id', 'pincode', 'office_name', 'district', 'is_serviceable', 'cod_allowed', 'zone', 'updated_at'];

    public function sortColumns()
    {
        return [
            ['title' => trans('core::core.titles.id'), 'column' => 'id', 'default_sort' => true],
            ['title' => trans('directory::directory_pincode.labels.pincode'), 'column' => 'pincode'],
            ['title' => trans('directory::directory_pincode.labels.office_name'), 'column' => 'office_name'],
            ['title' => trans('directory::directory_pincode.labels.district'), 'column' => 'district'],
        ];
    }

    public function getFilters($request)
    {
        $sessionKey = config('directory.cache.pincode');
        $yesNo = $this->getYesNoOptions(true);

        return [
            [
                'type' => 'text',
                'name' => 'pincode',
                'row' => '1',
                'value' => $request->get('pincode', getSessionFilter($sessionKey, 'pincode')),
                'options' => ['placeholder' => trans('directory::directory_pincode.labels.pincode'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'office_name',
                'row' => '1',
                'value' => $request->get('office_name', getSessionFilter($sessionKey, 'office_name')),
                'options' => ['placeholder' => trans('directory::directory_pincode.labels.office_name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'state_id',
                'row' => '1',
                'value' => $request->get('state_id', getSessionFilter($sessionKey, 'state_id')),
                'select_options' => $this->getStateOptions(true),
                'options' => ['label' => trans('directory::directory_pincode.labels.state'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'is_serviceable',
                'row' => '2',
                'value' => $request->get('is_serviceable', getSessionFilter($sessionKey, 'is_serviceable')),
                'select_options' => $yesNo,
                'options' => ['label' => trans('directory::directory_pincode.labels.is_serviceable'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'cod_allowed',
                'row' => '2',
                'value' => $request->get('cod_allowed', getSessionFilter($sessionKey, 'cod_allowed')),
                'select_options' => $yesNo,
                'options' => ['label' => trans('directory::directory_pincode.labels.cod_allowed'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'select',
                'name' => 'zone',
                'row' => '2',
                'value' => $request->get('zone', getSessionFilter($sessionKey, 'zone')),
                'select_options' => $this->getZoneOptions(true),
                'options' => ['label' => trans('directory::directory_pincode.labels.zone'), 'class' => 'custom-select'],
            ],
            [
                'type' => 'action',
                'class' => 'col-action',
                'row' => '3',
                'buttons' => [
                    'submit' => [
                        'name' => 'search',
                        'type' => 'submit',
                        'onclick' => 'searchFilter(); return false;',
                        'class' => 'btn btn-info btn-flat',
                        'title' => trans('core::core.buttons.search'),
                    ],
                    'reset' => [
                        'name' => 'reset',
                        'type' => 'button',
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([$sessionKey]))."'",
                        'class' => 'btn btn-secondary btn-flat',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];
    }

    public function pagination($request)
    {
        $sessionKey = config('directory.cache.pincode');
        $perPage = $request->get('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $orderBy = in_array($request->get('order_by'), self::SORTABLE, true) ? $request->get('order_by') : 'id';
        $dir = strtoupper((string) $request->get('dir')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request)->with(['state.translations', 'city.translations'])->orderBy($orderBy, $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter($request)
    {
        $sessionKey = config('directory.cache.pincode');
        $collection = $this->allWithBuilder();
        $filter = fn (string $key) => $request->get($key, getSessionFilter($sessionKey, $key));

        if (($pincode = trim((string) $filter('pincode'))) !== '') {
            $collection->where('pincode', 'like', $pincode.'%');
        }

        if (($office = trim((string) $filter('office_name'))) !== '') {
            $collection->where('office_name', 'like', '%'.$office.'%');
        }

        foreach (['state_id', 'is_serviceable', 'cod_allowed', 'zone'] as $column) {
            if (($value = $filter($column)) !== null && $value !== '') {
                $collection->where($column, $value);
            }
        }

        return $collection;
    }

    public function findByPincode(string $pincode): ?DirectoryPincode
    {
        return $this->model->newQuery()
            ->with(['state.translations', 'city.translations'])
            ->where('pincode', $pincode)
            ->orderBy('id')
            ->first();
    }

    public function updateFlags(string $pincode, array $flags): int
    {
        $flags = Arr::only($flags, DirectoryPincode::PINCODE_LEVEL_FIELDS);

        if ($flags === []) {
            return 0;
        }

        return $this->model->newQuery()->where('pincode', $pincode)->update($flags + ['updated_at' => now()]);
    }

    public function getStateOptions(bool $withPlaceholder = false): array
    {
        $states = DirectoryCountryState::query()
            ->with('translations')
            ->where('country', DirectorySettings::defaultCountry())
            ->get()
            ->mapWithKeys(fn (DirectoryCountryState $state): array => [$state->id => (string) $state->name])
            ->sort()
            ->all();

        return ($withPlaceholder ? ['' => ' -- '.trans('core::core.labels.select').' -- '] : []) + $states;
    }

    public function getZoneOptions(bool $withPlaceholder = false): array
    {
        $zones = [];
        foreach (DirectoryPincode::ZONES as $zone) {
            $zones[$zone] = trans('directory::directory_pincode.zones.'.$zone);
        }

        return ($withPlaceholder ? ['' => ' -- '.trans('core::core.labels.select').' -- '] : []) + $zones;
    }
}
