<div class="modal fade" id="select-atleast-one" tabindex="-1" role="dialog" aria-labelledby="delete-confirmation-title" aria-hidden="true">
    <div class="modal-dialog custom-popup confirmation-popup">
        <div class="modal-content">
            <div class="modal-header restore-header">
                <div class="restore-trash-icon restore-customer fas fa-trash-restore fa-4x"></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body restore-body">
                {{ trans('customer::customer.messages.select_atleast_one_modal') }}
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
            </div>
        </div>
    </div>
</div>


@push('js-stack')
<script>
    jQuery( document ).ready(function() {

        jQuery('#select-atleast-one').on('show.bs.modal', function (event) {
            var button = jQuery(event.relatedTarget);
            if(button.data('action-target') == undefined){
                button = jQuery('#mass-delete');
            }
            var modal = jQuery(this);
            var actionTarget = button.data('action-target');
            modal.find('form').attr('action', actionTarget);
            if (button.data('message') === undefined) {
            } else if (button.data('message') != '') {
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
