{{-- One cell of the coupon grid ($row, $columnCode). --}}
@switch($columnCode)
    @case('code')
        <td><strong>{{ $row->code }}</strong></td>
        @break
    @case('type')
        <td>{{ \Modules\Promotion\Support\PromotionOptions::label('types', $row->type) }}</td>
        @break
    @case('value')
        <td>{{ $row->benefitText() }}</td>
        @break
    @case('scope')
        <td>{{ \Modules\Promotion\Support\PromotionOptions::label('scopes', $row->scope) }}@if ($row->scope !== 'all') ({{ count($row->scopeIdList()) }})@endif</td>
        @break
    @case('funded_by')
        <td>{{ \Modules\Promotion\Support\PromotionOptions::label('funding', $row->funded_by) }}</td>
        @break
    @case('is_auto')
        <td>{{ $row->isAutomatic() ? trans('promotion::coupon.labels.yes') : trans('promotion::coupon.labels.no') }}</td>
        @break
    @case('used_count')
        <td class="text-right">{{ $row->used_count }}@if ($row->usage_limit_total) / {{ $row->usage_limit_total }}@endif</td>
        @break
    @case('seller')
        <td>{{ $row->seller ? $row->seller->code.' · '.$row->seller->displayName() : '-' }}</td>
        @break
    @case('starts_at')
    @case('ends_at')
    @case('created_at')
        <td>{{ $row->$columnCode ? getFormatedDate($row->$columnCode, getGridDateFormat()) : '-' }}</td>
        @break
    @case('status')
        <td><span class="badge badge-{{ $row->isEnabled() ? 'success' : 'secondary' }}">{{ \Modules\Promotion\Support\PromotionOptions::enabled()[(string) $row->status] ?? $row->status }}</span></td>
        @break
    @default
        <td>{{ $row->$columnCode ?? '-' }}</td>
@endswitch
