@php
    $langPath = config('role.lang_path');
@endphp
{{ formStart(null,"post" ,'admin.role.filters',updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $role)
                    <tr>
                        @can('admin.role.mass_delete')
                        <td>
                        @if($role->slug != \Config::get('role.master_admin_slug'))
                        {{ normalCheckbox('selectedCategory[]','',$errors,$role->id  , ['class' => "select-item", 'data-id' => $role->id ,'grid' => true])}}
                        @endif
                        </td>
                        @endcan
                        @foreach ($columns as $column)
                            @php 
                                if($column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $column['code'];
                                $value = $role[$columnCode] ?? null;
                            @endphp
                            @if(in_array($columnCode,['name','slug']))
                                <td>{{ wordWrapper($role[$columnCode]) }}</td>
                            @elseif($columnCode == 'created_at')
                                <td>{{ getFormatedDate($role->created_at, getGridDateFormat()) }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td class="sticky-action">
                            @can('admin.role.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.role.edit', updateUrlParams([$role->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                            @endcan
                            @can('admin.role.delete')
                            @if($role->slug != \Config::get('role.master_admin_slug'))
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.role.delete', updateUrlParams([$role->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="6"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->

{{ formEnd() }}
