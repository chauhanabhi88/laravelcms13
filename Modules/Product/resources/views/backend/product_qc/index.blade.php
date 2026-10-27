@extends('theme::layouts.backend.master')

@section('title')
{{ trans('product::product.titles.qc_queue') }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans('product::product.titles.qc_queue') }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @include('core::partials.grid-panel-toggles')
                        @can('admin.product.index')
                        <button type="button" class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.product.index', updateUrlParams()) }}')">{{ trans('product::product.titles.product_list') }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        @stop

        @section('content')
        @can('admin.product_qc.bulk')
            <form method="POST" action="{{ route('admin.product_qc.bulk', updateUrlParams()) }}" id="qc-bulk-form" class="border rounded p-3 mb-3">
                @csrf
                <div class="d-flex flex-wrap align-items-center mb-2">
                    <strong class="mr-3"><span id="qc-selected-count">0</span> {{ trans('product::product.labels.selected') }}</strong>
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm mr-2">{{ trans('product::product.buttons.bulk_approve') }}</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">{{ trans('product::product.buttons.bulk_reject') }}</button>
                </div>
                <details>
                    <summary class="small text-muted">{{ trans('product::product.labels.templates') }}</summary>
                    <div class="d-flex flex-wrap mt-2">
                        @foreach ($reasonTemplates as $code => $label)
                            <div class="custom-control custom-checkbox mr-3 mb-1">
                                <input type="checkbox" class="custom-control-input" name="reason_codes[]" value="{{ $code }}" id="bulk-reason-{{ $code }}">
                                <label class="custom-control-label small" for="bulk-reason-{{ $code }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <textarea name="reason" rows="2" maxlength="1000" class="form-control form-control-sm mt-2" placeholder="{{ trans('product::product.labels.reason_placeholder') }}"></textarea>
                </details>
            </form>
        @endcan

        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('product::backend.product_qc.partials.grid')
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

    (function () {
        var form = document.getElementById('qc-bulk-form');
        if (!form) {
            return;
        }

        var selected = function () {
            return Array.prototype.map.call(document.querySelectorAll('#collection .qc-select:checked'), function (box) {
                return box.value;
            });
        };

        document.addEventListener('change', function (event) {
            if (event.target.id === 'qc-select-all') {
                document.querySelectorAll('#collection .qc-select').forEach(function (box) {
                    box.checked = event.target.checked;
                });
            }
            if (event.target.id === 'qc-select-all' || event.target.classList.contains('qc-select')) {
                document.getElementById('qc-selected-count').textContent = selected().length;
            }
        });

        form.addEventListener('submit', function (event) {
            var ids = selected();
            form.querySelectorAll('input[name="ids[]"]').forEach(function (input) {
                input.remove();
            });
            if (ids.length === 0) {
                event.preventDefault();
                alert(@js(trans('core::core.messages.no_records')));
                return;
            }
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });
        });
    })();
</script>
@endpush
