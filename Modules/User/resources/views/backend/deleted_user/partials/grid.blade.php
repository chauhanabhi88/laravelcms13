@php
    $langPath = config('user.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.deleted_user.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                    <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>

                @forelse ($collection as $deleted_user)
                <tr>
                    @can('admin.deleted_user.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$deleted_user->id  , ['class' => "select-item", 'data-id' => $deleted_user->id ,'grid' => true])}}
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $deleted_user[$columnCode] ?? '-';
                        @endphp
                        @if(in_array($columnCode,['name','email']))
                            <td>{{ wordWrapper($deleted_user[$columnCode]) }}</td>
                        @elseif(in_array($columnCode,['deleated_at','created_at']))
                            <td>{{ getFormatedDate($deleted_user[$columnCode], getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.deleted_user.restore')
                        <button type="button" class="btn" data-message="{{ trans('user::deleted_user.messages.restore_modal') }}" data-toggle="modal" data-target="#modal-restore" data-action-target="{{ route('admin.deleted_user.restore',updateUrlParams([encrypt_It($deleted_user->id)])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.restore')}}"><i class='fas fa-trash-restore'></i></span></button>
                        @endcan
                        @can('admin.deleted_user.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.deleted_user.delete', updateUrlParams([$deleted_user->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="10"> {{ trans("core::core.messages.no_records") }} </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
{!! formEnd() !!}