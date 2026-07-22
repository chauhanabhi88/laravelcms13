@php
    $langPath = config('attribute.lang_path');
@endphp
{{ formStart(null,"post" ,'admin.attribute.filters',updateUrlParams(), ['id' => 'search_frm'])}}
    @csrf
    @include('core::partials.columns')
    @include('core::partials.filters')

    <div class="card-header">
        @include('core::partials.pagination')
    </div>
<!-- /.card-header -->
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead>
                <tr class="data-heading">
                    @include('core::partials.sorting')
                    <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>

                @forelse ($collection as $attribute)
                    <tr>
                        @can('admin.attribute.mass_delete')
                            <td>
                            {{ normalCheckbox('selectedCategory[]','',$errors,$attribute->id  , ['class' => "select-item", 'data-id' => $attribute->id ,'grid' => true])}}
                            </td>
                        @endcan
                        @foreach ($columns as $column)
                            @php 
                                if($column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $column['code'];
                                $value = $attribute[$columnCode] ?? null;
                            @endphp
                            @if(in_array($columnCode,['name','code','input_type','is_required','custom_value']))
                                @php
                                    $value = $columnCode == 'custom_value' || $columnCode == 'is_required' 
                                            ? (isset($yesNoOptions) && $yesNoOptions[$attribute[$columnCode]] ? $yesNoOptions[$attribute[$columnCode]] : "")
                                            : $attribute[$columnCode];
                                @endphp
                                <td>{{ wordWrapper($value) }}</td>
                            @elseif($columnCode == 'created_at')
                                <td>{{ getFormatedDate($attribute->created_at, getGridDateFormat()) }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td>{{ getFormatedDate($attribute->created_at, getGridDateFormat()) }}</td>
                        <td class="sticky-action">
                            @can('admin.attribute.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.attribute.edit', updateUrlParams([$attribute->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                            @endcan
                            @can('admin.attribute.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.attribute.delete', updateUrlParams([$attribute->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="8"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->

    {{ formEnd() }}
