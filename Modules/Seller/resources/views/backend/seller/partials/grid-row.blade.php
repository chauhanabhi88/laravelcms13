{{-- One grid cell of a seller, shared by the seller and application grids. --}}
@php
    $bank = $seller->primaryBankAccount;
    $bankBadge = match ($bank?->verification_status) {
        'verified' => 'success',
        'failed' => 'danger',
        'manual_review' => 'warning',
        'pending' => 'info',
        default => 'secondary',
    };
@endphp
@if ($columnCode == 'status')
    <td><span class="badge badge-{{ $seller->status->badge() }}">{{ $seller->status->label() }}</span></td>
@elseif ($columnCode == 'gst_status')
    <td>{{ $seller->gst_status ? ($gstStatusOptions[$seller->gst_status] ?? $seller->gst_status) : '' }}</td>
@elseif ($columnCode == 'bank_status')
    <td>
        @if ($bank)
            <span class="badge badge-{{ $bankBadge }}">{{ $bank->statusLabel() }}</span>
        @endif
    </td>
@elseif ($columnCode == 'store_name')
    <td>{{ wordWrapper((string) $seller->store_name) }}</td>
@elseif ($columnCode == 'legal_name')
    <td>{{ wordWrapper((string) $seller->legal_name) }}</td>
@elseif (in_array($columnCode, ['submitted_at', 'verified_at', 'created_at'], true))
    <td>{{ $seller->$columnCode ? getFormatedDate($seller->$columnCode, getGridDateFormat()) : '' }}</td>
@else
    <td>{{ $seller->$columnCode }}</td>
@endif
