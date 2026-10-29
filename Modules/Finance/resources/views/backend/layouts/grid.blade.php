{{-- Finance console index page: title + header actions, optional content above the grid ($grid: grid key). --}}
@extends('theme::layouts.backend.master')

@section('title')
{{ trans('finance::finance.titles.'.$grid) }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('finance::finance.titles.'.$grid) }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @yield('header_actions')
                        @include('core::partials.grid-panel-toggles')
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        @yield('above_grid')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('finance::backend.partials.grid', ['grid' => $grid])
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
