{{-- One cell of the tax_document grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('number')
        <td>{{ $row->number }}@if ($row->reference_number)<br><small class="text-muted">{{ $row->reference_number }}</small>@endif</td>
        @break
    @case('type')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('document_types', $row->type) }}</td>
        @break
    @case('seller')
        <td>{{ $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : '-' }}</td>
        @break
    @case('document_date')
        <td>{{ $row->document_date ? getFormatedDate($row->document_date, getGridDateFormat()) : '-' }}</td>
        @break
    @case('taxable_value')
        <td class="text-right">{{ $row->taxable_value?->format(true) ?? '-' }}</td>
        @break
    @case('tax')
        <td class="text-right">{{ $row->taxTotal()->format(true) }}</td>
        @break
    @case('total')
        <td class="text-right">{{ $row->total?->format(true) ?? '-' }}</td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
