@php
    $langPath = 'directory::directory_pincode.columns';
@endphp
{{ formStart(null, "POST", 'admin.directory_pincode.filters', updateUrlParams(), ['id' => 'search_frm', 'enctype' => 'multipart/form-data']) }}

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
                    <th data-sortable="false">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collection as $directoryPincode)
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                if ($column['checkbox_checked'] == 0) {
                                    continue;
                                }
                                $columnCode = $column['code'];
                            @endphp
                            @if ($columnCode == 'state_id')
                                <td>{{ $directoryPincode->state?->name }}</td>
                            @elseif ($columnCode == 'city_id')
                                <td>{{ $directoryPincode->city?->name }}</td>
                            @elseif (in_array($columnCode, ['is_serviceable', 'cod_allowed']))
                                <td>
                                    @can('admin.directory_pincode.edit')
                                        <span data-placement="right" data-toggle="tooltip" title="{{ $yesNoOptions[$directoryPincode->$columnCode] ?? '' }}">
                                            <label class="switch">
                                                <input type="checkbox" class="pincode-flag" data-id="{{ $directoryPincode->id }}" data-field="{{ $columnCode }}" {{ $directoryPincode->$columnCode == config('core.yes') ? 'checked' : '' }}>
                                                <span class="slider round"></span>
                                            </label>
                                        </span>
                                    @else
                                        {{ $yesNoOptions[$directoryPincode->$columnCode] ?? '' }}
                                    @endcan
                                </td>
                            @elseif ($columnCode == 'zone')
                                <td>{{ $zoneOptions[$directoryPincode->zone] ?? '' }}</td>
                            @elseif ($columnCode == 'updated_at')
                                <td>{{ getFormatedDate($directoryPincode->updated_at, getGridDateFormat()) }}</td>
                            @else
                                <td>{{ $directoryPincode->$columnCode }}</td>
                            @endif
                        @endforeach
                        <td>
                            @can('admin.directory_pincode.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.directory_pincode.edit', updateUrlParams([$directoryPincode->id])) }}');" class="btn"><i class="fas fa-edit"></i></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="{{ count($columns) + 1 }}"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
{!! formEnd() !!}
