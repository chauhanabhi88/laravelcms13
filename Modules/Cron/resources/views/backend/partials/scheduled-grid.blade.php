
{{ formStart(null,"POST" ,'admin.cron_schedule.filters' ,updateUrlParams([$id]), ['id' => 'search_frm'])}}
    @csrf
    @include('core::partials.filters')

    <div class="card-header">
        @include('core::partials.pagination')
    </div>
    @can('admin.schedule.mass_delete')
        <button type="button" class="btn btn-danger btn-fw float-right" id="mass-delete" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.schedule.mass_delete', updateUrlParams([$cron->id])) }}">{{ trans('core::core.buttons.delete') }}</i></button>
        @endcan
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead>
                <tr class="data-heading">
                    
                    @include('core::partials.sorting',['langPath' => 'cron::cron_schedule.titles'])
                    <th data-sortable="false">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                    @forelse ($collection as $schedule)
                        <tr>
                            @can('admin.schedule.mass_delete')
                                <td>{!! normalCheckbox('selectedCategory[]','',$errors, $schedule->id, false, ['class' => "select-item", 'data-id' => $schedule->id]) !!}</td>
                            @endcan
                            <td>{{ $schedule->id }}</td>
                            <td>{{ wordWrapper($schedule->title) }}</td>
                            <td>{{ wordWrapper($schedule->command) }}</td>
                            @if ($schedule->message)
                                <td>{{ wordWrapper($schedule->message) }}</td>
                            @else
                                <td align="center"> - </td>
                            @endif
                            <td>{{ (isset($statusOptions[$schedule->status]) && $statusOptions[$schedule->status]) ? $statusOptions[$schedule->status] : "-" }}</td>
                            <td>{{ getFormatedDate($schedule->execute_date, getGridDateFormat()) }}</td>
                            <td>{{ getFormatedDate($schedule->finished_date, getGridDateFormat()) }}</td>
                            <td>{{ getFormatedDate($schedule->created_at, getGridDateFormat()) }}</td>
                            <td>
                                @can('admin.cron_schedule.delete')
                                    <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.cron_schedule.delete', updateUrlParams([$schedule->id])) }}"><i class="fas fa-trash"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td align="center" colspan="10">{{ trans("core::core.messages.no_records") }}</td>
                        </tr>
                    @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
{!! formEnd() !!}