@php
    $langPath = config('cron.lang_path');
@endphp
{{ formStart(null,"post" ,'admin.cron.filters',updateUrlParams(), ['id' => 'search_frm'])}}

@csrf
@include('core::partials.columns')
@include('core::partials.filters')


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

            @forelse ($collection as $cron)
            <tr>
                @can('admin.cron.mass_delete')
                <td>{{ normalCheckbox('selectedCategory[]','',$errors,$cron->id, ['class' => "select-item", 'id' => $cron->id ,'grid' => true])}}
                </td>
                @endcan
                @foreach ($columns as $column)
                    @php 
                        if($column['checkbox_checked'] == 0){
                            continue;
                        }
                        $columnCode = $column['code'];
                        $value = $cron[$columnCode] ?? "-";
                    @endphp
                    @if(in_array($columnCode,['title','status','command','description']))
                        @php
                            $value = $columnCode == 'status' 
                                    ? (isset($statusOptions) && $statusOptions[$cron['status']] ? $statusOptions[$cron['status']] : "")
                                    : $cron[$columnCode];
                        @endphp
                        <td>{{ wordWrapper($value) }}</td>
                    @elseif($columnCode == 'created_at')
                        <td>{{ getFormatedDate($cron->created_at, getGridDateFormat()) }}</td>
                    @else
                        <td>{{$value}}</td>
                    @endif
                @endforeach
                <td class="sticky-action">
                    @can('admin.cron.runCron')
                    <button type="button" onclick="setLocation('{{ route('admin.cron.runCron', updateUrlParams([$cron->command])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('cron::cron.labels.runCron')}}"><i class="fas fa-play"></i></span></button>
                    @endcan
                    @can('admin.cron.edit')
                    <button type="button" onclick="setLocation('{{ route('admin.cron.edit', updateUrlParams([$cron->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                    @endcan
                    @can('admin.cron.delete')
                    <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.cron.delete', updateUrlParams([$cron->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                    @endcan
                    @can('admin.cron.edit')
                    <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$cron->status] !!}">
                        <label class="switch">
                            <input type="checkbox" class="status" data-id="{{$cron->id}}" {{ ($cron->status == 1) ? "checked" : ""}}>
                            
                            <span class="slider round"></span>
                        </label>
                    </span>
                    @endcan
                </td>
            </tr>
            @empty
            <tr>
                <td align="center" colspan="8">{{ trans("core::core.messages.no_records") }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<!-- /.card-body -->

{{ formEnd()}}