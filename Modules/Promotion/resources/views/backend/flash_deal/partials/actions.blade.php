@can('admin.flash_deal.edit')
    <a href="{{ route('admin.flash_deal.edit', updateUrlParams([$row->id])) }}" class="btn">
        <span data-placement="left" data-toggle="tooltip" title="{{ trans('promotion::flash_deal.buttons.edit') }}"><i class="fas fa-edit"></i></span>
    </a>
@endcan
