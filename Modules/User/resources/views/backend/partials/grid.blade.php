@php
    $langPath = config('user.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.user.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $user)
                <tr>
                    @can('admin.user.mass_delete')
                    <td>
                        @if(isset($user->role->slug) && !empty($user->role->slug) && $user->role->slug == \Config::get('role.master_admin_slug') && $user->status == config('core.yes'))
                        @else
                        {{ normalCheckbox('selectedCategory[]','',$errors,$user->id  , ['class' => "select-item", 'data-id' => $user->id ,'grid' => true])}}
                        </td>
                    @endif
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $user[$columnCode] ?? null;
                        @endphp
                        @if(in_array($columnCode,['name','email','status']))
                            @php
                                $value = $columnCode == 'status' 
                                        ? (isset($statusOptions) && $statusOptions[$user['status']] ? $statusOptions[$user['status']] : "")
                                        : $user[$columnCode];
                            @endphp
                            <td>{{ wordWrapper($value) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($user->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.user.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.user.edit', updateUrlParams([$user->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.user.delete')
                        @if(($user->id == Auth::id()) || (isset($user->role->slug) && !empty($user->role->slug) && $user->role->slug == \Config::get('role.master_admin_slug')))
                        @else
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.user.delete', updateUrlParams([$user->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endif
                        @endcan
                        @can('admin.user.edit')
                        
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$user->status] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$user->id}}" {{ ($user->status == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                        
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