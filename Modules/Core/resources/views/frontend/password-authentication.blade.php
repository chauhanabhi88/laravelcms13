<div class="modal fade" id="modal-password-authentication" tabindex="-1" role="dialog" aria-labelledby="delete-confirmation-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="edit-confirmation-title">Password Authentication</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="content">
                </div>
                
                {{ formStart(null,"POST" ,'' ,updateUrlParams(), ['id' => 'password-authentication', 'class' => 'pull-left'])}}
                    <input type="hidden" name="id" id="id"/>

                    <div class="input-row">
                        <div class="input-field">
                            <div class="input-field ">
                                <label for="oldpassword">{{trans('customer::customer.labels.old_password')}}</label>
                                    {!! normalInputOfType("password", "old_password", '', $errors, null, ["class" => "form-control required newPassword", 'hide_label' => true ]) !!}
                            </div>
                        </div>
                    </div>
                <div class="custom-message"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-danger" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                   <button type="submit" class="btn yellow-btn">Edit</button>
            </div>
             {!! formEnd() !!}
        </div>
    </div>
</div>

