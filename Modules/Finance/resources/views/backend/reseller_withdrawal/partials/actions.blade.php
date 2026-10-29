@if ($row->isOpen())
    @can('admin.reseller_withdrawal.mark_paid')
        <form method="POST" action="{{ route('admin.reseller_withdrawal.mark_paid', updateUrlParams([$row->id])) }}" class="d-inline-flex">
            @csrf
            <input type="text" name="utr" class="form-control form-control-sm mr-1" placeholder="{{ trans('finance::finance.labels.utr') }}" required minlength="6" maxlength="40" style="width: 140px">
            <button type="submit" class="btn btn-sm btn-success">{{ trans('finance::finance.buttons.mark_paid') }}</button>
        </form>
    @endcan
    @can('admin.reseller_withdrawal.mark_failed')
        <form method="POST" action="{{ route('admin.reseller_withdrawal.mark_failed', updateUrlParams([$row->id])) }}" class="d-inline-flex ml-1">
            @csrf
            <input type="hidden" name="reason" value="{{ trans('finance::wallet.failed_by_finance') }}">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ trans('finance::wallet.confirm_fail') }}')">{{ trans('finance::finance.buttons.mark_failed') }}</button>
        </form>
    @endcan
@elseif ($row->failure_reason)
    <small class="text-muted">{{ $row->failure_reason }}</small>
@endif
