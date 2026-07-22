@php
    $langPath = config('mail.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.mail.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $mail)
                <tr>
                    @can('admin.mail.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$mail->id  , ['class' => "select-item", 'data-id' => $mail->id,'checked' => false ,'grid' => true])}}
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $mail[$columnCode] ?? null;
                        @endphp
                        @if(in_array($columnCode,['name','status','subject','slug','cc','bcc','body']))
                            @php
                                $value = $columnCode == 'status' 
                                        ? (isset($statusOptions) && $statusOptions[$mail['status']] ? $statusOptions[$mail['status']] : "")
                                        : $mail[$columnCode];
                            @endphp
                            <td>{{ wordWrapper($value) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($mail->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.mail.preview')
                        <a class="btn" target="_blank" href="{{ route('admin.mail.preview', updateUrlParams([$mail->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.preview')}}"><i class="fas fa-eye"></i></span></a>
                        @endcan
                        @can('admin.mail.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.mail.edit', updateUrlParams([$mail->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.mail.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.mail.delete', updateUrlParams([$mail->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.mail.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! wordWrapper($statusOptions[$mail->status]) !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$mail->id}}" {{ ($mail->status == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="7">{{ trans("core::core.messages.no_records") }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
{!! formEnd() !!}
