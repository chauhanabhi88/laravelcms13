{{-- One cell of the commission_rule grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('scope')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('scopes', $row->scope) }}</td>
        @break
    @case('target')
        <td>{{ collect([$row->category?->name, $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : null])->filter()->implode(' / ') ?: '-' }}</td>
        @break
    @case('value')
        <td class="text-right">{{ $row->type === 'percent' ? rtrim(rtrim((string) $row->value, '0'), '.').' %' : 'Rs '.$row->value.' / item' }}</td>
        @break
    @case('applies_to')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('applies_to', $row->applies_to) }}</td>
        @break
    @case('effective_from')
        <td>{{ $row->effective_from ? getFormatedDate($row->effective_from, getGridDateFormat()) : '-' }}</td>
        @break
    @case('effective_to')
        <td>{{ $row->effective_to ? getFormatedDate($row->effective_to, getGridDateFormat()) : '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ (int) $row->status === (int) config('core.enabled') ? 'success' : 'secondary' }}">{{ \Modules\Finance\Support\FinanceOptions::enabledOptions()[(string) $row->status] ?? $row->status }}</span></td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
