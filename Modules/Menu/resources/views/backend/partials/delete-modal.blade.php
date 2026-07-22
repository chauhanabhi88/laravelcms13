<div class="modal fade" id="modal-delete-confirmation" tabindex="-1" role="dialog" aria-labelledby="delete-confirmation-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header delete-header">
                <div class="close-delete-popup"></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(null,"DELETE" ,null ,null, ['class' => 'pull-left', 'id' => 'delete-form'])}}
            <div class="modal-body delete-body">
                <div class="default-message">
                    @isset($message)
                    {!! $message !!}
                    @else
                    {{ trans('core::core.modal.confirmation-message') }}
                    @endisset
                </div>
                <div class="custom-message"></div>
            
                {{ normalCheckbox('deleteAllChild','Delete all child items?',$errors,null  , [])}}
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                
                <input type="hidden" name="idsToDelete" id="idToDelete" />
                <input type="hidden" name="idsNotToDelete" id="idsNotToDelete" />
                <input type="hidden" name="selectAll" id="select-all" />
                
                <input type="hidden" class="delete-collection" name="delete-collection" value="" />
                <div id="fieldContainer">
                </div>
                <input type="hidden" id="isFiltered" name="isFiltered" value="0" />

                <button data-form-id="delete-form" class="btn del-btn save">{{ trans('core::core.buttons.delete') }}</button>
            </div>
            {!! formEnd() !!}
        </div>
    </div>
</div>

@push('js-stack')
<script>
    jQuery(document).ready(function() {

        $("#delete-form").validate({
            submitHandler: function(form) {
                if ($('.delete-collection').val()) {
                    var collectionField = "";
                    var collectionArr = JSON.parse($('.delete-collection').val());
                    if (collectionArr !== undefined) {
                        $.each(collectionArr, function(field, value) {
                            if (value.name !== '_token' && value.name !== 'order_by' && value.name !== 'dir' && value.name !== 'last_page' && value.name !== 'page' && value.name != 'per_page' && value.name !== 'selectedCategory') {
                                if (value.value !== "") {
                                    collectionField = collectionField + "<input type='hidden' name='" + value.name + "' value='" + value.value + "' /></n>";
                                }
                            }
                        });
                        if (collectionField.length > 0) {
                            $('#fieldContainer').html(collectionField);
                            $('#isFiltered').val('1');
                            $('#delete-collection').val('');
                        }
                    }
                }

                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        jQuery('#mass-delete').click(function() {
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
            jQuery('#modal-delete-confirmation').modal('show');
        });

        jQuery('#modal-delete-confirmation').on('show.bs.modal', function(event) {
            var button = jQuery(event.relatedTarget);
            if (button.data('action-target') == undefined) {
                button = jQuery('#mass-delete');
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