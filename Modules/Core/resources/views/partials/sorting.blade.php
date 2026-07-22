@if ($columns)
    @if(!isset($displayMassDeleteCheckbox) || $displayMassDeleteCheckbox !== false)
    <th>
        <div class="custom-control custom-checkbox custom-checkbox-th">
            <input class="custom-control-input" type="checkbox" id="massDeleteCheckbox" value="option1">
            <label for="massDeleteCheckbox" class="custom-control-label"></label>
            <a id="massSelectOptions" href="javascript:void(0)" data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false" class="dropdown-toggle"></a>
            <ul aria-labelledby="massSelectOptions" id="massSelectDropdown" class="dropdown-menu border-0 shadow">
                <li><a href="javascript:void(0)" id="selectVisible" onclick="selectVisible()" class="dropdown-item">
                        {{ trans('core::core.labels.select_visible') }} </a></li>
                <li><a href="javascript:void(0)" onclick="selectAll(true)" class="dropdown-item">
                        {{ trans('core::core.labels.select_all') }} </a></li>
            </ul>
        </div>
    </th>
    @endif
    @foreach ($columns as $column)
        @if($column['checkbox_checked'] == 0 )
            @php continue @endphp
        @endif
        @php
            $isColumnMatched = (($request->get('order_by') == $column["code"]) || ($request->get('order_by') == '' && $column['code'] == 'id')) ? true : false;
            $dir = strtolower($request->input('dir', 'desc'));
            if (isset($column['dir']) && !empty($column['dir'])) {
                $dir = strtolower($column["dir"]);
            }
        @endphp
        @if($column['is_sortable'] == 2)
            <th>{{ ($langPath . '.' . $column['code'] == trans($langPath . '.' . $column['code'])) ? $column['name'] : trans($langPath . '.' . $column['code']) }}</th>
        @else
            <th data-field="{{ $column['code'] }}" class="default-sort  {{ ($isColumnMatched) ? "sorting_{$dir}" : "sorting" }}">
                {{ ($langPath . '.' . $column['code'] == trans($langPath . '.' . $column['code'])) ? $column['name'] : trans($langPath . '.' . $column['code']) }}
            </th>
        @endif
    @endforeach
@endif