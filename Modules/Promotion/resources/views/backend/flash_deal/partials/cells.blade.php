{{-- One cell of the flash_deal grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('items_count')
        <td class="text-right">{{ $row->items_count ?? 0 }}</td>
        @break
    @case('state')
        @php
            $state = $row->state();
        @endphp
        <td><span class="badge badge-{{ $state === 'running' ? 'success' : ($state === 'scheduled' ? 'info' : 'secondary') }}">{{ \Modules\Promotion\Support\PromotionOptions::label('deal_states', $state) }}</span></td>
        @break
    @case('starts_at')
    @case('ends_at')
    @case('created_at')
        <td>{{ $row->$columnCode ? $row->$columnCode->format('d-m-Y H:i') : '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ $row->isEnabled() ? 'success' : 'secondary' }}">{{ \Modules\Promotion\Support\PromotionOptions::enabled()[(string) $row->status] ?? $row->status }}</span></td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
