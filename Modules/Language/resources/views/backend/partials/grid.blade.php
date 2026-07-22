@php
    $langPath = config('language.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.language.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                    @can('admin.language.mass_delete')
                        <th></th>
                    @endcan
                    @include('core::partials.sorting',['displayMassDeleteCheckbox' => false])
                    <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collection as $language)
                    <tr>
                        @can('admin.language.mass_delete')
                            <td>{!! normalCheckbox('selectedCategory[]','',$errors, $language->id, ['class' => "select-item", 'data-id' => $language->id]) !!}</td>
                        @endcan
                        @foreach ($columns as $column)
                            @php 
                                if($column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $column['code'];
                                $value = $language[$columnCode] ?? null;
                            @endphp
                            @if(in_array($columnCode,['title','locale','status']))
                                @php
                                    $value = $columnCode == 'status' 
                                            ? (isset($statusOptions) && $statusOptions[$language['status']] ? $statusOptions[$language['status']] : "")
                                            : $language[$columnCode];
                                @endphp
                                <td>{{ wordWrapper($value) }}</td>
                            @elseif($columnCode == 'created_at')
                                <td>{{ getFormatedDate($language->created_at, getGridDateFormat()) }}</td>
                            @elseif($columnCode == 'is_default')
                                <td>{{ (isset($yesNoOptions[$language->is_default]) && $yesNoOptions[$language->is_default]) ? $yesNoOptions[$language->is_default] : "-" }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td class="sticky-action">
                            @can('admin.language.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.language.edit', updateUrlParams([$language->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                            @endcan
                            {{-- @can('admin.language.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.language.delete', updateUrlParams([$language->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                            @endcan --}}
                            @can('admin.language.edit')
                            <span data-placement="right" data-toggle="tooltip"  title="{!! $statusOptions[$language->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{$language->id}}" {{ ($language->status == 1) ? "checked" : ""}}>
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
