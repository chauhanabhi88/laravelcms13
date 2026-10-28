@extends('theme::layouts.backend.master')

@section('title')
{{ trans('payment::payment_recon.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('payment::payment_recon.titles.list') }}</h4>
                    <p class="text-muted small mb-0">{{ trans('payment::payment_recon.labels.help') }}</p>
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
                    @include('payment::backend.payment_recon.partials.grid')
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

    /*
     * The grid sits inside the filter form, so "resolve" posts through a form built here.
     */
    function paymentReconResolve(url) {
        var note = window.prompt(@json(trans('payment::payment_recon.labels.confirm_resolve')) + ' ' + @json(trans('payment::payment_recon.labels.note')) + ':');
        if (note === null) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        [['_token', @json(csrf_token())], ['note', note]].forEach(function (field) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = field[0];
            input.value = field[1];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
