
@php
$viewPassword = !empty(settings('core', 'view_password')) ? settings('core', 'view_password') : config('core.on');
@endphp


{{ formStart(null,"POST" ,'admin.customer.store' ,updateUrlParams(), ['id' => 'create-customer-form','enctype'=>'multipart/form-data'])}}

{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<!-- form start -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-customer-info-tab" data-toggle="pill" href="#custom-tabs-three-customer-info" role="tab" aria-controls="custom-tabs-three-customer-info" aria-selected="true">{{ trans("customer::customer.titles.customer_info") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-customer-address-tab" data-toggle="pill" href="#custom-tabs-three-customer-address" role="tab" aria-controls="custom-tabs-three-customer-address" aria-selected="false">{{ trans("customer::customer.titles.address") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-customer-info" role="tabpanel" aria-labelledby="custom-tabs-three-customer-info-tab">

                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("customer[first_name]","customer::customer.labels.first_name", $errors,null,["class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalText("customer[last_name]","customer::customer.labels.last_name", $errors,null,["class" => "form-control required"])}}
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
                        $image_extension = (!empty(settings('customer', 'image_type')))?settings('customer', 'image_type'):config('asgard.customer.config.defualt_image_type');
                        $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
                        @endphp
                        <div class="image-note">
                            <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                            <li>{{trans("core::core.image-note.max-size",['size'=>$image_max_size])}}</li>
                            <li>{{trans("core::core.image-note.file-type",['file_type'=>($image_extension ? $image_extension : 'jpeg,jpg,png')])}}</li>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <label for="email">{{trans('customer::customer.labels.email')}} *</label>
                                {{ normalInputOfType("email","customer[email]", 'customer::customer.labels.email',$errors,null,['class' => 'form-control required' ,"hide_label" => "true"])}}
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number">{{trans('customer::customer.labels.contact_number')}} *</label>
                                {{ normalText("customer[contact_number]","customer::customer.labels.contact_number", $errors,null,["class" => "form-control number required", "hide_label" => "true"])}}
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <label for="cpassword">{{trans('customer::customer.labels.password')}} *</label>
                                <div class="form-group">
                                    <div class="input-group password-div">
                                        <input type="password" class="form-control required newPassword" name="password" placeholder="{{trans('customer::customer.labels.password')}}">
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
                                <label for="cpassword">{{trans('customer::customer.labels.cpassword')}} *</label>
                                <div class="form-group">
                                    <div class="input-group confirmpassword-div">
                                        <input type="password" class="form-control required" name="password_confirmation" placeholder="{{trans('customer::customer.labels.cpassword')}}" equalTo=".newPassword">
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
                            <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                                <label class="switch">
                                    <input type="checkbox" name="customer[status]" class="status">
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="custom-tabs-three-customer-address" role="tabpanel" aria-labelledby="custom-tabs-three-customer-address-tab">
                        @include('customer::backend.partials.create-address-fields')
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>

{{ formEnd() }}