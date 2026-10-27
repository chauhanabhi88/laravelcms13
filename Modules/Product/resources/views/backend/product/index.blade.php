@extends('theme::layouts.backend.master')

@section('title')
{{ trans('product::product.titles.product_list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('product::product.titles.product_list') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.product.export')
                        <button type="button" class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.product.export', updateUrlParams()) }}')">{{ trans('product::product.buttons.export') }}</button>
                        @endcan
                        @can('admin.product_qc.index')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.product_qc.index', updateUrlParams()) }}')">{{ trans('product::product.buttons.qc_queue') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('product::backend.product.partials.grid')
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
</script>
@endpush
