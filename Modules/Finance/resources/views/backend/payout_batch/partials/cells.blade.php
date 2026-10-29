{{-- One cell of the payout_batch grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('batch_no')
        <td><a href="{{ route('admin.payout_batch.show', updateUrlParams([$row->id])) }}">{{ $row->batch_no }}</a></td>
        @break
    @case('cycle_end')
        <td>{{ $row->cycle_end ? getFormatedDate($row->cycle_end, getGridDateFormat()) : '-' }}</td>
        @break
    @case('total_amount')
        <td class="text-right">{{ $row->total_amount?->format(true) ?? '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($row->status) }}">{{ \Modules\Finance\Support\FinanceOptions::label('batch_statuses', $row->status) }}</span></td>
        @break
    @case('created_at')
        <td>{{ $row->created_at ? getFormatedDate($row->created_at, getGridDateFormat()) : '-' }}</td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
