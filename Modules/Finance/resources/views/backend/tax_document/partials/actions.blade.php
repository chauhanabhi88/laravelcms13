@can('admin.tax_document.download')
    <a href="{{ route('admin.tax_document.download', updateUrlParams([$row->id])) }}" class="btn">
        <span data-placement="left" data-toggle="tooltip" title="{{ trans('finance::finance.buttons.download') }}"><i class="fas fa-file-pdf"></i></span>
    </a>
@endcan
@can('admin.tax_document.regenerate')
    <form method="POST" action="{{ route('admin.tax_document.regenerate', updateUrlParams([$row->id])) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn" title="{{ trans('finance::tax_document.buttons.regenerate') }}"><i class="fas fa-sync"></i></button>
    </form>
@endcan
