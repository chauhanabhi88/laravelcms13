@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp


{{ formStart(null,"PUT" ,'admin.customer.update' ,updateUrlParams([$customer->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-customer-info-tab" data-toggle="pill" href="#custom-tabs-three-customer-info" role="tab" aria-controls="custom-tabs-three-customer-info" aria-selected="true">{{ trans("customer::customer.titles.customer_info") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-address-tab" data-toggle="pill" href="#custom-tabs-three-address" role="tab" aria-controls="custom-tabs-three-address" aria-selected="false">{{ trans("customer::customer.titles.address") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-customer-info" role="tabpanel" aria-labelledby="custom-tabs-three-customer-info-tab">

                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("customer[first_name]","customer::customer.labels.first_name", $errors,$customer->first_name,["class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalText("customer[last_name]","customer::customer.labels.last_name", $errors,$customer->last_name,["class" => "form-control"])}}
                            </div>
                        </div>


                        {{ trans("customer::customer.labels.profile_image") }}
                        <div class="input-group mb-3">
                            <div class="custom-file">
                                {{ normalFile("profile_picture",'profile_picture',$errors,['class'=>'custom-file-label form-control is_invalid is-invalid','id'=>'customerInputFile', 'accept' => $image_extension])}}
                                <label class="custom-file-label" for="customerInputFile">Choose file</label>
                            </div>
                            <div class="input-group-append">
                            </div>
                        </div>

                        @php
                        $og_image_param = [
                        'module' => Config::get('customer.name'),
                        'image' => $customer->profile_picture,
                        ];
                        $thumbnail_image_param = [
                        'image-type' => 'thumbnail',
                        'module' => Config::get('customer.name'),
                        'image' => $customer->profile_picture,
                        ];
                        @endphp
                        @if(getImageUrl($og_image_param))
                        <a href="{{getImageUrl($og_image_param)}}" target="_BLANK">
                            <img src="{{getImageUrl($thumbnail_image_param)}}" height=100 width=150 alt="{{ trans('customer::customer.labels.profile_image') }}">
                        </a>
                        {{ normalCheckbox("remove_profile_picture","customer::customer.labels.remove_profile_picture", $errors,null,["class" => "form-control"])}}
                        @endif
                        <div class="image-note">
                            <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                            <li>{{trans("core::core.image-note.max-size",['size'=>$image_max_size])}}</li>
                            <li>{{trans("core::core.image-note.file-type",['file_type'=>$image_extension])}}</li>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="email">{{trans('customer::customer.labels.email')}}{{ $customer->phone ? '' : ' *' }}</label>
                                <div class="form-group">
                                    <div class="input-group email-div">
                                        <input type="email" class="form-control" value="{{ old('customer.email', $customer->email) }}" name="customer[email]" placeholder="{{trans('customer::customer.labels.email')}}">
                                        @if((settings('core', 'email_verification') == config('core.yes')))
                                        @if((!empty($customer->email_verified_at)))
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <span class='title' data-placement='bottom' data-toggle='tooltip' title="{{ trans('customer::customer.labels.email_verified') }}">
                                                    <span class="fa fa-check-circle email-verified-icon"></span>
                                                </span>
                                            </div>
                                        </div>
                                        @else
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <span class='title' data-placement='bottom' data-toggle='tooltip' title="{{ trans('customer::customer.labels.email_unverified') }}">
                                                    <span class="fa fa-check-circle email-unverified-icon"></span>
                                                </span>
                                            </div>
                                        </div>
                                        @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number">{{trans('customer::customer.labels.contact_number')}}</label>
                                {{ normalText("customer[contact_number]","customer::customer.labels.contact_number", $errors,$customer->contact_number,["class" => "form-control number", "hide_label" => "true"])}}
                            </div>
                        </div>

                        {{-- M08: mobile sign-in identity, reseller mode and COD risk --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_phone">{{ trans('customer::customer.labels.phone') }}</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">+91</span></div>
                                        <input type="tel" id="customer_phone" class="form-control @error('customer.phone') is-invalid @enderror" name="customer[phone]" maxlength="14"
                                            value="{{ old('customer.phone', \Modules\Customer\Support\PhoneNumber::national($customer->phone)) }}">
                                    </div>
                                    <small class="form-text text-muted">{{ trans('customer::customer.labels.phone_help') }}</small>
                                    @error('customer.phone')
                                        <span class="error invalid-feedback d-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="customer_is_reseller">{{ trans('customer::customer.labels.is_reseller') }}</label>
                                    <select id="customer_is_reseller" name="customer[is_reseller]" class="custom-select">
                                        <option value="{{ config('core.no') }}" @selected((int) old('customer.is_reseller', $customer->is_reseller) !== (int) config('core.yes'))>{{ trans('core::core.labels.no') }}</option>
                                        <option value="{{ config('core.yes') }}" @selected((int) old('customer.is_reseller', $customer->is_reseller) === (int) config('core.yes'))>{{ trans('core::core.labels.yes') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                {{ normalText("customer[reseller_business_name]","customer::customer.labels.reseller_business_name", $errors,$customer->reseller_business_name,["class" => "form-control", "maxlength" => 120])}}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="d-block">{{ trans('customer::customer.labels.cod_blocked') }}</label>
                                <span class="badge {{ $customer->isCodBlocked() ? 'badge-danger' : 'badge-success' }}">{{ $customer->isCodBlocked() ? trans('core::core.labels.yes') : trans('core::core.labels.no') }}</span>
                                @can('admin.customer.toggle_cod_block')
                                    <button type="button" class="btn btn-sm btn-outline-secondary ml-2" onclick="customerToggleCodBlock('{{ route('admin.customer.toggle_cod_block', updateUrlParams([$customer->id])) }}')">
                                        {{ $customer->isCodBlocked() ? trans('customer::customer.labels.unblock_cod') : trans('customer::customer.labels.block_cod') }}
                                    </button>
                                @endcan
                            </div>
                            <div class="col-md-6">
                                <label class="d-block">{{ trans('customer::customer.labels.rto_count') }}</label>
                                <span>{{ (int) $customer->rto_count }}</span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="password">{{trans('customer::customer.labels.password')}}</label>
                                <div class="form-group">
                                    <div class="input-group password-div">
                                        <input type="password" class="form-control newPassword" name="password" placeholder="{{trans('customer::customer.labels.password')}}">
                                        @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <span class="fa fa-eye-slash"></span>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="cpassword">{{trans('customer::customer.labels.cpassword')}}</label>


                                <div class="form-group">
                                    <div class="input-group confirmpassword-div">
                                        <input type="password" class="form-control" name="password_confirmation" placeholder="{{trans('customer::customer.labels.password')}}" equalTo=".newPassword">
                                        @if(isset($viewPassword) && $viewPassword == config('core.on'))
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <span class="fa fa-eye-slash"></span>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>{{ trans('customer::customer.labels.status') }}</label>
                            <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$customer->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" name="customer[status]" class="status" {{ ($customer->status == 1) ? "checked" : ""}}>
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        </div>
                    </div>
                    {{ formEnd() }}
                    <div class="tab-pane fade" id="custom-tabs-three-address" role="tabpanel" aria-labelledby="custom-tabs-three-address-tab">
                        @include('customer::backend.new-address-form')
                        <button class="btn btn-primary btn-fw mb-2" type="button" id="add_new_address_btn">{{trans("customer::customer.buttons.add_new_address")}}</button>
                        @include('customer::backend.partials.customer_address')
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        jQuery("#main_form").validate({
            ignore: [],
            rules: {
                'customer[first_name]': {
                    required: true,
                    maxlength: 255,
                    lettersOnly: true
                },
                'customer[last_name]': {
                    required: false,
                    maxlength: 255,
                    lettersOnly: true
                },
                'customer[email]': {
                    required: @js(! $customer->phone),
                    basicEmail: true
                },
                'customer[contact_number]': {
                    required: false,
                    minlength: 8,
                    maxlength: 10
                },
            },
            messages: {
                'customer[first_name]': {
                    required: "Please enter First Name.",
                    lettersOnly: "Please enter a valid first name"
                },
                'customer[last_name]': {
                    required: "Please enter Last Name.",
                    lettersOnly: "Please enter a valid last name"
                },
                'customer[email]': {
                    required: "Please enter Email.",
                    basicEmail: "Please enter a valid Email"
                },
                'customer[contact_number]': {
                    required: "Please enter Contact Number.",
                    minDigits: "Contact Number should contain atleast 8 numbers"
                },
            },
                errorPlacement: function(error, element) {
                error.insertAfter(element);
                var classes = element.attr('class');
                classes = classes.split(' ');
                if (classes.includes('custom-file-label')) {
                    error.insertAfter('.input-group.mb-3');
                } else if(classes.includes("formated-textarea")){
                    error.insertAfter('.note-editor');
                }
            },
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            },
        });

        window.customerToggleCodBlock = function(url) {
            var form = jQuery('<form method="POST"></form>').attr('action', url);
            form.append(jQuery('<input type="hidden" name="_token">').val(@js(csrf_token())));
            jQuery('body').append(form);
            form.trigger('submit');
        };

        jQuery.validator.addMethod("lettersOnly", function(value, element) {
            return this.optional(element) || /^[A-Za-z\s]+$/.test(value);
        }, "Please enter letters only.");

        jQuery.validator.addMethod("basicEmail", function(value, element) {
            return this.optional(element) || /^[^@]+@[^@]+\.[^@]+$/.test(value);
        }, "Please enter a valid email address.");
        jQuery.validator.addMethod("minDigits", function(value, element, param) {
            return this.optional(element) || value.replace(/\D/g, '').length >= param;
        }, "Please enter at least {0} digits.");
    });

</script>
@endpush