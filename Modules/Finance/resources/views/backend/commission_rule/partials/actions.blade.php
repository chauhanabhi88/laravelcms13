@can('admin.commission_rule.edit')
    <a href="{{ route('admin.commission_rule.edit', updateUrlParams([$row->id])) }}" class="btn">
        <span data-placement="left" data-toggle="tooltip" title="{{ trans('finance::finance.buttons.edit') }}"><i class="fas fa-edit"></i></span>
    </a>
@endcan
