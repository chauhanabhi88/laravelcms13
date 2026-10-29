{{-- One cell of the reseller_withdrawal grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('customer')
        <td>{{ $row->customer?->displayName() ?? '#'.$row->customer_id }}</td>
        @break
    @case('amount')
        <td class="text-right">{{ $row->amount?->format(true) ?? '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($row->status) }}">{{ \Modules\Finance\Support\FinanceOptions::label('withdrawal_statuses', $row->status) }}</span></td>
        @break
    @case('created_at')
        <td>{{ $row->created_at ? getFormatedDate($row->created_at, getGridDateFormat()) : '-' }}</td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
