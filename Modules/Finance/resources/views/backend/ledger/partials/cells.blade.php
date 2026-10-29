{{-- One cell of the ledger grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('posted_at')
        <td>{{ $row->posted_at ? getFormatedDate($row->posted_at, getGridDateFormat()) : '-' }}</td>
        @break
    @case('seller')
        <td>{{ $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : '-' }}</td>
        @break
    @case('order')
        <td>{{ $row->sellerOrder?->sub_order_no ?? '-' }}</td>
        @break
    @case('type')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('ledger_types', $row->type) }}</td>
        @break
    @case('description')
        <td class="text-wrap" style="min-width: 220px">{{ $row->description }}</td>
        @break
    @case('amount')
        <td class="text-right {{ $row->isCredit() ? 'text-success' : 'text-danger' }}">{{ $row->isCredit() ? '+' : '-' }}{{ $row->amount->format(true) }}</td>
        @break
    @case('tax_amount')
        <td class="text-right">{{ $row->tax_amount?->format(true) ?? '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($row->status) }}">{{ \Modules\Finance\Support\FinanceOptions::label('ledger_statuses', $row->status) }}</span></td>
        @break
    @case('eligible_on')
        <td>{{ $row->eligible_on ? getFormatedDate($row->eligible_on, getGridDateFormat()) : '-' }}</td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
