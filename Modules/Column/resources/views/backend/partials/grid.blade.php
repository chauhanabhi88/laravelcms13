@php
    $langPath = config('column.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.column.filters' ,updateUrlParams(), ['id' => 'search_frm','enctype'=>'multipart/form-data'])}}

    @csrf
    @include('core::partials.columns')
    @include('core::partials.filters')


    <!-- /.card-header -->
    <div class="card-header">
        @include('core::partials.pagination')
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead>
                <tr class="data-heading">
                    @include('core::partials.sorting')
                    <th data-sortable="false">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>

                @forelse ($collection as $column)
                    <tr>
                        @can('admin.column.mass_delete')
                            <td>
                            {{ normalCheckbox("selectedCategory[]","", $errors,$column->id,['class' => "select-item", 'data-id' => $column->id ,'grid' => true])}}
                            </td>
                        @endcan
                        @foreach ($columns as $_column)
                            @php 
                                if($_column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $_column['code'];
                                $value = $column[$columnCode] ?? null;
                            @endphp
                            @if($columnCode == 'created_at')
                                <td>{{ getFormatedDate($column->created_at, getGridDateFormat()) }}</td>
                            @elseif(in_array($columnCode,['name','code','description','menu']))
                                <td>{{ wordWrapper($column[$columnCode]) }}</td>
                            @elseif(in_array($columnCode,['is_sortable','is_default']))
                                <td>{{ (isset($yesNoOptions[$column[$columnCode]]) && $yesNoOptions[$column[$columnCode]]) ? $yesNoOptions[$column[$columnCode]] : "-" }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td>
                            @can('admin.column.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.column.edit', updateUrlParams([$column->id])) }}');" class="btn"><i class="fas fa-edit"></i></button>
                            @endcan
                            @can('admin.column.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.column.delete', updateUrlParams([$column->id])) }}"><i class="fas fa-trash"></i></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="7"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
{!! formEnd() !!}
