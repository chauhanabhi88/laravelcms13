@php
    $langPath = config('mail.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.mail_log.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $mailLog)
            <tr>
                @foreach ($columns as $column)
                    @php 
                        if($column['checkbox_checked'] == 0){
                            continue;
                        }
                        $columnCode = $column['code'];
                        $value = $mailLog[$columnCode] ?? null;
                    @endphp
                    @if(in_array($columnCode,['name','status','subject','cc','bcc','body','from_name','to_name','from_email','to_email','exception']))
                        @php
                            $value = $columnCode == 'status' 
                                    ? (isset($statusOptions) && $statusOptions[$mailLog['status']] ? $statusOptions[$mailLog['status']] : "")
                                    : $mailLog[$columnCode];
                        @endphp
                        <td>{{ wordWrapper($value) }}</td>
                    @elseif($columnCode == 'created_at')
                        <td>{{ getFormatedDate($mailLog->created_at, getGridDateFormat()) }}</td>
                    @else
                        <td>{{$value}}</td>
                    @endif
                @endforeach
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