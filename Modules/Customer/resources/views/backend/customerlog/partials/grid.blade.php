@php
    $langPath = config('customer.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.customerLog.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                @include('core::partials.sorting',['displayMassDeleteCheckbox' => false])
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
                    @if($columnCode == 'first_name')
                        <td>{{ isset($customerlog->first_name) && !empty($customerlog->first_name) ? wordWrapper($customerlog->first_name." ".$customerlog->last_name) : '-' }}</td>
                    @elseif($columnCode=='email')
                        <td>{{ wordWrapper($customerlog[$columnCode]) }}</td>
                    @elseif($columnCode == 'status')
                        @if(isset($customerlog->id) && $fileStore->get('customer-is-online-' . $customerlog->id))
                            <td><span class="{{getStatusLabelClass(config('customer.customer_log.online'))}}">{{ trans("customer::customer_online_offline.customer_log.online") }}</td>
                        @else
                            <td><span class="{{getStatusLabelClass(config('customer.customer_log.offline'))}}">{{ trans("customer::customer_online_offline.customer_log.offline") }}</td>
                        @endif
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