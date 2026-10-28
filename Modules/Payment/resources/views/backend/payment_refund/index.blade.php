@extends('theme::layouts.backend.master')

@section('title')
{{ trans('payment::payment_refund.titles.list') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('payment::payment_refund.titles.list') }}</h4>
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
                    @include('payment::backend.payment_refund.partials.grid')
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
     * The grid sits inside the filter form, so row actions post through a form built here.
     * askUtr: prompt for the bank reference (required for COD refunds paid by hand).
     */
    function paymentRefundAction(url, confirmText, askUtr, utrRequired) {
        var fields = {_token: @json(csrf_token())};

        if (askUtr) {
            var utr = window.prompt(@json(trans('payment::payment_refund.labels.utr')) + (utrRequired ? '' : ' (optional)'));
            if (utr === null || (utrRequired && utr.trim() === '')) {
                return;
            }
            fields.utr = utr.trim();
        } else if (!window.confirm(confirmText)) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        Object.keys(fields).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
