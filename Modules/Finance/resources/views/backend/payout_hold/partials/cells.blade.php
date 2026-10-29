{{-- One cell of the payout_hold grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('seller')
        <td>{{ $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : '-' }}</td>
        @break
    @case('reason')
        <td class="text-wrap" style="min-width: 240px">{{ $row->reason }}@if ($row->release_note)<br><small class="text-muted">{{ $row->release_note }}</small>@endif</td>
        @break
    @case('placed_at')
        <td>{{ $row->placed_at ? getFormatedDate($row->placed_at, getGridDateFormat()) : '-' }}</td>
        @break
    @case('released_at')
        <td>{{ $row->released_at ? getFormatedDate($row->released_at, getGridDateFormat()) : '-' }}</td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
