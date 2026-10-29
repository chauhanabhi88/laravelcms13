@can('admin.cod_remittance.show')
    <a href="{{ route('admin.cod_remittance.show', updateUrlParams([$row->id])) }}" class="btn">
        <span data-placement="left" data-toggle="tooltip" title="{{ trans('finance::finance.buttons.view') }}"><i class="fas fa-eye"></i></span>
    </a>
@endcan
