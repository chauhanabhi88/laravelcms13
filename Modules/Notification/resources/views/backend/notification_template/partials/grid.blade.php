@php
    $langPath = 'notification::notification_template.columns';
@endphp
{{ formStart(null, 'POST', 'admin.notification_template.filters', updateUrlParams(), ['id' => 'search_frm']) }}
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
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => auth()->user()->can('admin.notification_template.mass_delete')])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $notificationTemplate)
                <tr>
                    @can('admin.notification_template.mass_delete')
                        <td>{{ normalCheckbox('selectedNotificationTemplate[]', '', $errors, $notificationTemplate->id, ['class' => 'select-item', 'data-id' => $notificationTemplate->id, 'grid' => true]) }}</td>
                    @endcan
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'event_key')
                            <td><code>{{ $notificationTemplate->event_key }}</code><br><small class="text-muted">{{ \Modules\Notification\Support\NotificationOptions::eventLabel($notificationTemplate->event_key) }}</small></td>
                        @elseif ($columnCode == 'channel')
                            <td>{{ $channels[$notificationTemplate->channel] ?? $notificationTemplate->channel }}</td>
                        @elseif ($columnCode == 'audience')
                            <td>{{ $audiences[$notificationTemplate->audience] ?? $notificationTemplate->audience }}</td>
                        @elseif ($columnCode == 'title')
                            <td>{{ wordWrapper((string) ($notificationTemplate->title ?: \Illuminate\Support\Str::limit($notificationTemplate->body, 60))) }}</td>
                        @elseif ($columnCode == 'provider_template_id')
                            <td>{{ $notificationTemplate->provider_template_id ?: '-' }}</td>
                        @elseif ($columnCode == 'status')
                            <td>
                                <span class="badge badge-{{ $notificationTemplate->isEnabled() ? 'success' : 'secondary' }}">{{ $statusOptions[$notificationTemplate->status] ?? '' }}</span>
                            </td>
                        @elseif ($columnCode == 'updated_at')
                            <td>{{ getFormatedDate($notificationTemplate->updated_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{ $notificationTemplate->$columnCode }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.notification_template.edit')
                            <button type="button" onclick="setLocation('{{ route('admin.notification_template.edit', updateUrlParams([$notificationTemplate->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.edit') }}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.notification_template.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.notification_template.delete', updateUrlParams([$notificationTemplate->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{ trans('core::core.labels.delete') }}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.notification_template.update_status')
                            <span data-placement="right" data-toggle="tooltip" title="{{ $statusOptions[$notificationTemplate->status] ?? '' }}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{ $notificationTemplate->id }}" {{ $notificationTemplate->isEnabled() ? 'checked' : '' }}>
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 2 }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
