@php
    $langPath = config('customer.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.customerloginlog.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
<div class="table-responsive card-body">
    <table class="table table-bordered table-striped table-hover text-nowrap">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting')
            </tr>
        </thead>
        <tbody>

            @forelse ($collection as $customerlog)
            <tr class="spacer"></tr>
            <tr class="tr-shadow">
                @foreach ($columns as $column)
                    @php 
                        if($column['checkbox_checked'] == 0){
                            continue;
                        }
                        $columnCode = $column['code'];
                        $value = $customerlog[$columnCode] ?? null;
                    @endphp
                    @if(in_array($columnCode,['first_name','email']))
                        @php
                            $value = $columnCode == 'first_name' 
                                    ? $customerlog->first_name." ".$customerlog->last_name
                                    : $customerlog[$columnCode];
                        @endphp
                        <td>{{ wordWrapper($value) }}</td>
                    @elseif( in_array($columnCode,['created_at','last_login_date']) )
                        <td>{{ getFormatedDate($customerlog[$columnCode], getGridDateFormat()) }}</td>
                    @else
                        <td>{{$value}}</td>
                    @endif
                @endforeach
            </tr>
            @empty
            <tr>
                <td align="center" colspan="10"> {{ trans("core::core.messages.no_records") }} </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}