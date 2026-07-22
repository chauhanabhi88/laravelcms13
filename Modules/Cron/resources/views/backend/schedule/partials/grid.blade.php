@php
    $langPath = 'cron::cron_schedule.titles';
@endphp
{{ formStart(null,"POST" ,'admin.schedule.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                    @forelse ($collection as $schedule)
                        <tr>
                            @can('admin.schedule.mass_delete')
                                <td>{!! normalCheckbox('selectedCategory[]','',$errors, $schedule->id, false, ['class' => "select-item", 'data-id' => $schedule->id]) !!}</td>
                            @endcan
                            @foreach ($columns as $column)
                                @php 
                                    if($column['checkbox_checked'] == 0){
                                        continue;
                                    }
                                    $columnCode = $column['code'];
                                    $value = $schedule[$columnCode] ?? "-";
                                @endphp
                                @if(in_array($columnCode,['title','status','command','message']))
                                    @php
                                        $value = $columnCode == 'status' 
                                                ? (isset($statusOptions) && $statusOptions[$schedule['status']] ? $statusOptions[$schedule['status']] : "")
                                                : $value;
                                    @endphp
                                    <td>{{ wordWrapper($value) ?? "-" }}</td>
                                @elseif(in_array($columnCode,['created_at','execute_date','finished_date']))
                                    <td>{{ getFormatedDate($schedule[$columnCode], getGridDateFormat()) }}</td>
                                @else
                                    <td>{{$value}}</td>
                                @endif
                            @endforeach
                            <td class="sticky-action">
                                @can('admin.schedule.delete')
                                    <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.schedule.delete', updateUrlParams([$schedule->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td align="center" colspan="11">{{ trans("core::core.messages.no_records") }}</td>
                        </tr>
                    @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
{!! formEnd() !!}