{{-- One cell of the fee_rule grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('code')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('fee_codes', $row->code) }}</td>
        @break
    @case('scope')
        <td>{{ \Modules\Finance\Support\FinanceOptions::label('scopes', $row->scope) }}</td>
        @break
    @case('target')
        <td>{{ collect([$row->category?->name, $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : null])->filter()->implode(' / ') ?: '-' }}</td>
        @break
    @case('value')
        <td class="text-right">{{ $row->type === 'percent' ? rtrim(rtrim((string) $row->value, '0'), '.').' %' : 'Rs '.$row->value }}</td>
        @break
    @case('charged_to')
        <td><span class="badge badge-{{ \Modules\Finance\Support\FinanceOptions::badge($row->charged_to) }}">{{ \Modules\Finance\Support\FinanceOptions::label('charged_to', $row->charged_to) }}</span></td>
        @break
    @case('effective_from')
        <td>{{ $row->effective_from ? getFormatedDate($row->effective_from, getGridDateFormat()) : '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ (int) $row->status === (int) config('core.enabled') ? 'success' : 'secondary' }}">{{ \Modules\Finance\Support\FinanceOptions::enabledOptions()[(string) $row->status] ?? $row->status }}</span></td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
