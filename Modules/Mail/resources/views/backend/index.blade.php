@extends('theme::layouts.backend.master')

@section('title')
{{ trans("mail::mail.titles.mail_list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("mail::mail.titles.mail") }}</h4>
                </div>
                <div class="col-sm-6 btn-right">
                    <div class="float-right">
                        @can('admin.mail.mass_delete')
                        <button type="button" id="mass-delete" class="btn btn-danger btn-fw" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.mail.mass_delete', updateUrlParams()) }}">{{ trans('core::core.buttons.delete') }}</i></button>
                        @endcan
                        @can('admin.mail.create')
                        <button type="button" class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.mail.create', updateUrlParams()) }}')">{{ trans("core::core.buttons.create") }}</button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <!-- /.content-header -->
        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                    @include('mail::backend.partials.grid')
                </div>
            </div>
        </div>
    </div>
</div>

@include('core::partials.delete-modal')
@stop
@push('js-stack')
<script type="text/javascript">
    var status;
    jQuery(document).on('change', '.status', function() {
        var id = $(this).attr('data-id');
        // status = ($(this).is(':checked')) ? 1 : 2;
        status = ($(this).is(':checked')) ? "{{ config('core.enabled') }}" : "{{ config('core.disabled') }}";
        $(".tooltip").remove();
        customObj.setUrl('{{route("admin.mail.update_status",updateUrlParams())}}').setMethod("POST").setParams({
            'status': status,
            'id': id,
            'active_menu_id': jQuery('#active_menu_id').val(),
            '_token': '{{csrf_token()}}'
        }).getContent();
    });
</script>
@endpush