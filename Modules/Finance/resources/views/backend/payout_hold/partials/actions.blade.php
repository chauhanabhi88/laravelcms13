@if ($row->isActive())
    @can('admin.payout_hold.release')
        <form method="POST" action="{{ route('admin.payout_hold.release', updateUrlParams([$row->id])) }}" class="d-inline" onsubmit="return confirm('{{ trans('finance::payout_hold.confirm_release') }}')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">{{ trans('finance::payout_hold.buttons.release') }}</button>
        </form>
    @endcan
@endif
