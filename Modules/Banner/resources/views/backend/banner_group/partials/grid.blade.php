@php
    $langPath = config('banner.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.bannergroup.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
    @csrf
    @include('core::partials.columns')
    @include('core::partials.filters')
    <!-- /.card-body -->


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

                @forelse ($collection as $bannergroup)
                    <tr>
                        @can('admin.bannergroup.mass_delete')

                            <td>{{ normalCheckbox('selectedCategory[]','',$errors,$bannergroup->id  , ['class' => "select-item", 'data-id' => $bannergroup->id ,'grid' => true])}}</td>
                        @endcan
                        @foreach ($columns as $column)
                            @php
                                if($column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $column['code'];
                                $value = $bannergroup[$columnCode] ?? null;
                            @endphp
                            @if(in_array($columnCode,['name','code']))
                                <td>{{ wordWrapper($bannergroup[$columnCode]) }}</td>
                            @elseif($columnCode == 'created_at')
                                <td>{{ getFormatedDate($bannergroup->created_at, getGridDateFormat()) }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td class="sticky-action">
                            @can('admin.bannergroup.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.bannergroup.edit', updateUrlParams([$bannergroup->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                            @endcan
                            @can('admin.bannergroup.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.bannergroup.delete', updateUrlParams([$bannergroup->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                            @endcan
                            @can('admin.bannergroup.edit')
                            <span data-placement="right" data-toggle="tooltip"  title="{!! $statusOptions[$bannergroup->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{$bannergroup->id}}" {{ ($bannergroup->status == 1) ? "checked" : ""}}>
                                    <span class="slider round"></span>
                                </label>
                            </span>
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
{{ formEnd()}}
