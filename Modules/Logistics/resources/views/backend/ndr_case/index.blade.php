@extends('theme::layouts.backend.master')

@section('title')
{{ trans('logistics::ndr_case.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('logistics::ndr_case.titles.list') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('logistics::backend.ndr_case.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('js-stack')
<script type="text/javascript">
    $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
    });

    jQuery(document).on('change', '#collection .ndr-action', function () {
        var form = jQuery(this).closest('form');
        form.find('.ndr-address').toggle(jQuery(this).val() === 'change_address');
    });
</script>
@endpush
