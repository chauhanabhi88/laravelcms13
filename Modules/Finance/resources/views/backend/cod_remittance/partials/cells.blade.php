{{-- One cell of the cod_remittance grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('remittance_ref')
        <td><a href="{{ route('admin.cod_remittance.show', updateUrlParams([$row->id])) }}">{{ $row->remittance_ref }}</a></td>
        @break
    @case('remittance_date')
        <td>{{ $row->remittance_date ? getFormatedDate($row->remittance_date, getGridDateFormat()) : '-' }}</td>
        @break
    @case('amount')
        <td class="text-right">{{ $row->amount?->format(true) ?? '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($row->status) }}">{{ \Modules\Finance\Support\FinanceOptions::label('remittance_statuses', $row->status) }}</span></td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
