<div class="modal fade" id="modal-restore" tabindex="-1" role="dialog" aria-labelledby="delete-confirmation-title" aria-hidden="true">
    <div class="modal-dialog custom-popup confirmation-popup">
        <div class="modal-content">
            <div class="modal-header restore-header">
                <div class="restore-trash-icon restore-customer fas fa-trash-restore fa-4x"></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body restore-body">
                {{ trans('customer::customer.messages.restore_modal') }}
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                
                {{ formStart(null,"" ,'' ,updateUrlParams(), ['class' => 'pull-left', 'id' => 'restore-form'])}}
                <input type="hidden" name="selectAll" id="restoreSelectAll">
                <input type="hidden" name="restoreIdsNotDelete" id="restoreIdsNotDelete">
                <input type="hidden" name="restoreIdToDelete" id="restoreIdToDelete">
                <button type="submit" class="btn cancel-btn">{{ trans('user::deleted_user.buttons.restore') }}</button>
                {!! formEnd() !!}
            </div>
        </div>
    </div>
</div>


@push('js-stack')
<script>
    jQuery(document).ready(function() {
        $("#restore-form").validate({

            submitHandler: function(form) {
                updateStatusHiddenValues();
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        function updateStatusHiddenValues() {
            $("#restoreSelectAll").val($("#select-all").val());
            $("#restoreIdToDelete").val($("#idToDelete").val());
            $("#restoreIdsNotDelete").val($("#idsNotToDelete").val());
        }


        jQuery('#restore').click(function() {
            if (jQuery('.select-item:checked').length < 1 && jQuery('#selectAll').val() == '') {
                jQuery(document).Toasts('create', {
                    class: 'bg-danger',
                    title: '{{ trans("core::core.titles.alert") }}',
                    autohide: true,
                    autoremove: true,
                    delay: 1000,
                    icon: 'fas fa-exclamation-triangle fa-lg',
                    body: '{{ trans("core::core.messages.select_record") }}'
                })
                return false;
            }
            jQuery('#modal-restore').modal('show');
        });

        jQuery('#modal-restore').on('show.bs.modal', function(event) {
            var button = jQuery(event.relatedTarget);
            if (button.data('action-target') == undefined) {
                button = jQuery('#restore');
            }
            var modal = jQuery(this);
            var actionTarget = button.data('action-target');
            modal.find('form').attr('action', actionTarget);
            if (button.data('message') === undefined) {} else if (button.data('message') != '') {
                modal.find('.custom-message').show().empty().append(button.data('message'));
                modal.find('.default-message').hide();
            } else {
                modal.find('.default-message').show();
                modal.find('.custom-message').hide();
            }

            if (button.data('remove-submit-button') === true) {
                modal.find('button[type=submit]').hide();
            } else {
                modal.find('button[type=submit]').show();
            }
        });
    });
</script>
@endpush