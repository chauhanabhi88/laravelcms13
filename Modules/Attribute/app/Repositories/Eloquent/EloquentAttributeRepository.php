<?php

namespace Modules\Attribute\Repositories\Eloquent;

use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeTranslation;
use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;

class EloquentAttributeRepository extends EloquentBaseRepository implements AttributeRepository
{
    public function sortColumns($request)
    {
        $columns = [
            [
                'title' => trans('core::core.titles.id'),
                'column' => 'id',
            ],
            [
                'title' => trans('attribute::attribute.titles.name'),
                'column' => 'name',
            ],
            [
                'title' => trans('attribute::attribute.titles.code'),
                'column' => 'code',
            ],
            [
                'title' => trans('attribute::attribute.titles.input_type'),
                'column' => 'input_type',
            ],
            [
                'title' => trans('attribute::attribute.titles.is_required'),
                'column' => 'is_required',
            ],
            [
                'title' => trans('core::core.titles.created_at'),
                'column' => 'created_at',
            ],
        ];

        if ($this->getAuthUser()->can('admin.attribute.mass_delete')) {
            $massDeleteCheckbox = [
                'column' => 'massDelete',
                'type' => 'massDelete',
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }
        $orderBy = getSessionFilter(config('attribute.name'), 'order_by') ? getSessionFilter(config('attribute.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('attribute.name'), 'dir') ? getSessionFilter(config('attribute.name'), 'dir') : $request->get('dir', 'desc');
        $columns = $this->defaultSort($columns, $orderBy, $dir);

        return $columns;
    }

    public function getFilters($request)
    {
        $inputOption = $this->getInputTypeOptions(true);
        $yesNoOptions = $this->getYesNoOptions(true);

        $fields = [
            [
                'type' => 'number_range',
                'name' => ['from', 'to'],
                'row' => '1',
                'value' => [
                    'from' => $request->get('from', getSessionFilter(config('attribute.name'), 'from')),
                    'to' => $request->get('to', getSessionFilter(config('attribute.name'), 'to')),
                ],
                'options' => [
                    'from' => ['label' => trans('core::core.labels.id'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ],
            [
                'type' => 'text',
                'name' => 'name',
                'row' => '1',
                'value' => $request->get('name', getSessionFilter(config('attribute.name'), 'name')),
                'options' => ['placeholder' => trans('attribute::attribute.titles.name'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'code',
                'row' => '1',
                'value' => $request->get('code', getSessionFilter(config('attribute.name'), 'code')),
                'options' => ['placeholder' => trans('attribute::attribute.titles.code'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'input_type',
                'row' => '1',
                'value' => $request->get('input_type', getSessionFilter(config('attribute.name'), 'input_type')),
                'select_options' => $inputOption,
                'options' => ['label' => 'Input Type', 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'is_required',
                'row' => '1',
                'value' => $request->get('is_required', getSessionFilter(config('attribute.name'), 'is_required')),
                'select_options' => $yesNoOptions,
                'options' => ['label' => 'Is requried', 'class' => 'form-control'],
            ],
            [
                'type' => 'date_range',
                'row' => '1',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => [
                    'created_at_from' => $request->get('created_at_from', getSessionFilter(config('attribute.name'), 'created_at_from')),
                    'created_at_to' => $request->get('created_at_to', getSessionFilter(config('attribute.name'), 'created_at_to')),
                ],
                'options' => [
                    'created_at_from' => ['label' => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker'],
                ],
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
                        'class' => 'btn btn-primary btn-fw',
                        'title' => trans('core::core.buttons.search'),
                    ],
                    'reset' => [
                        'name' => 'reset',
                        'type' => 'button',
                        'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([config('attribute.name')]))."'",
                        'class' => 'btn btn-secondary btn-fw',
                        'title' => trans('core::core.buttons.reset'),
                    ],
                ],
            ],
        ];

        return $fields;
    }

    public function pagination($request)
    {
        $perPage = $request->get('per_page', settings('core', 'default_per_page'));
        $orderBy = getSessionFilter(config('attribute.name'), 'order_by') ? getSessionFilter(config('attribute.name'), 'order_by') : $request->get('order_by', 'id');
        $dir = getSessionFilter(config('attribute.name'), 'dir') ? getSessionFilter(config('attribute.name'), 'dir') : $request->get('dir', 'desc');
        $collection = $this->filter($request);
        $collection->orderBy($orderBy, $dir);
        updateSessionFilterPage(config('attribute.name'), $collection, $perPage);

        return $collection->paginate($perPage, ['*'], 'page', getSessionFilter(config('attribute.name'), 'page'));
    }

    public function filter($request)
    {
        $timezoneOffset = getTimezoneOffset();

        $attribute = new Attribute;
        $attributeTrans = new AttributeTranslation;

        $collection = $this->allWithBuilder();

        $collection->join($attributeTrans->getTable().' AS AT', $attribute->getTable().'.id', '=', 'AT.attribute_id')->where('AT.locale', app()->getLocale())
            ->select($attribute->getTable().'.*', 'AT.name AS name');

        $whereCond = $request->get('from', getSessionFilter(config('attribute.name'), 'from'));
        if ($whereCond !== null) {
            $collection->where($attribute->getTable().'.id', '>=', $whereCond);
        }

        $whereCond = $request->get('to', getSessionFilter(config('attribute.name'), 'to'));
        if ($whereCond !== null) {
            $collection->where($attribute->getTable().'.id', '<=', $whereCond);
        }
        $whereCond = $request->get('name', getSessionFilter(config('attribute.name'), 'name'));
        if ($whereCond !== null) {
            $collection->where('AT.name', 'LIKE', "%{$whereCond}%");
        }

        $whereCond = $request->get('code', getSessionFilter(config('attribute.name'), 'code'));
        if ($whereCond !== null) {
            $code = $whereCond;
            $collection->where('code', 'LIKE', "%{$code}%");
        }

        $whereCond = $request->get('input_type', getSessionFilter(config('attribute.name'), 'input_type'));
        if ($whereCond !== null) {
            $inputType = $whereCond;
            $collection->where('input_type', 'LIKE', "%{$inputType}%");
        }

        $whereCond = $request->get('is_required', getSessionFilter(config('attribute.name'), 'is_required'));
        if ($whereCond !== null) {
            $isRequired = $whereCond;
            $collection->where('is_required', 'LIKE', "%{$isRequired}%");
        }

        $whereCond = $request->get('created_at_from', getSessionFilter(config('attribute.name'), 'created_at_from'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$attribute->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", date('Y-m-d', strtotime($whereCond)));
        }

        $whereCond = $request->get('created_at_to', getSessionFilter(config('attribute.name'), 'created_at_to'));
        if ($whereCond !== null) {
            $collection->whereRaw('DATE('.$attribute->getTable().".created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", date('Y-m-d', strtotime($whereCond)));
        }

        return $collection;
    }

    public function getInputTypeOptions($flag = false)
    {
        $inputTypeOptions = [];

        if ($flag) {
            $inputTypeOptions = ['' => ' -- '.trans('core::core.labels.select').' -- '];
        }

        return $inputTypeOptions + [
            'boolean' => trans('attribute::attribute.titles.boolean'),
            'checkbox' => trans('attribute::attribute.titles.checkbox'),
            'multiselect' => trans('attribute::attribute.titles.multiselect'),
            'radio' => trans('attribute::attribute.titles.radio'),
            'select' => trans('attribute::attribute.titles.select'),
            'textarea' => trans('attribute::attribute.titles.textarea'),
            'textbox' => trans('attribute::attribute.titles.textbox'),
        ];
    }

    public function getAttributeData($code, $flag = true)
    {
        $data = [];
        $getAttributeData = Attribute::with('attributeOption.attributeTranslation')->where('code', $code)->get();
        if (isset($getAttributeData) && ! empty($getAttributeData)) {
            foreach ($getAttributeData as $key => $value) {
                if (isset($value['attributeOption']) && ! empty($value['attributeOption'])) {
                    foreach ($value['attributeOption'] as $optionKey => $optionValue) {
                        $optionVal = $optionValue->custom_option;
                        if (! empty($optionVal) && isset($optionValue['translations']) && ! empty($optionValue['translations'])) {
                            foreach ($optionValue['translations'] as $transKey => $transValue) {
                                if ($flag) {
                                    $data[''] = '--'.trans('Select').'--';
                                }
                                $data[$optionVal] = $transValue->name;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
