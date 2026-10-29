@can('admin.payout_batch.show')
    <a href="{{ route('admin.payout_batch.show', updateUrlParams([$row->id])) }}" class="btn">
        <span data-placement="left" data-toggle="tooltip" title="{{ trans('finance::finance.buttons.view') }}"><i class="fas fa-eye"></i></span>
    </a>
@endcan
