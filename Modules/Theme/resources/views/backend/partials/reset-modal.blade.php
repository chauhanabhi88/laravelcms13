<div class="modal fade in" id="resetModal" tabindex="-1" role="dialog" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-danger">
            <div class="modal-header">
                <h4 class="modal-title" id="delete-confirmation-title">{{ trans('core::core.modal.title') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="default-message">
                    @isset($message)
                        {!! $message !!}
                    @else
                        {{ trans('theme::theme.messages.confirm') }}
                    @endisset
                </div>
                <div class="custom-message"></div>
            </div>
            <div class="modal-footer justify-content-between">
                    <button  id="reset-btn" type="button" class="btn btn-outline-light"  data-dismiss="modal">
                        {{ trans('theme::theme.buttons.reset') }}
                    </button>
                    <button type="button" class="btn btn-outline-light pull-left" data-dismiss="modal">
                        {{ trans('theme::theme.buttons.cancel') }}
                    </button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
</div>
<!-- END MODAL -->

@push('js-stack')
    <script type="text/javascript">
        jQuery('#reset-btn').click(function() 
        {
            jQuery.ajax({
                url: "{{ route('admin.theme.reset', updateUrlParams()) }}",
                method: 'get',
                success: function(result){
                    jQuery(location).attr('href', "{{route('admin.theme.index', updateUrlParams())}}");
                }});
        });
    </script>
@endpush