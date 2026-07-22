 @extends('theme::layouts.backend.master')

 @section('title')
 {{ trans("attribute::attribute.titles.create_attribute") }}
 @endsection

 @section('content-header')

 <div class="page-title-header row d-none d-sm-flex">
     <div class="page-header col-sm-12 d-flex pb-4 pt-2">
         <div class="col-sm-6">
             <h4 class="page-title">{{ trans("attribute::attribute.titles.create_attribute") }}</h4>
         </div>
         <div class="col-sm-6 btn-right">
             <div class="float-right">
                 <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.attribute.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                 <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                 <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
             </div>
         </div>
     </div>
 </div>

 <!-- /.content-header -->
 @stop
 @php
 $attributeSettings = settings("attribute");
 $imgTypes = (!empty($attributeSettings['image_type']))?$attributeSettings['image_type']:'-';
 $maxUploadSize = (!empty($attributeSettings['max_upload_size'])) ? $attributeSettings['max_upload_size'] : 0;
 $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
 $image_max_size = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;
 @endphp
 @section('content')

 {{ formStart(null,"post" ,'admin.attribute.store',updateUrlParams(), ['id' => 'main_form', "enctype"=>"multipart/form-data"])}}
 {{ normalHidden("snc", 0, 'snc', ['class' => 'snc'])}}
 <div class="row">
     <div class="col-12 col-sm-6 col-lg-12">
         <div class="card card-info card-outline card-outline-tabs">
             <div class="card-body">
                 <div class="tab-content" id="custom-tabs-three-tabContent">
                     <div class="tab-pane fade show active" id="custom-tabs-three-attribute-info" attribute="tabpanel" aria-labelledby="custom-tabs-three-attribute-info-tab">
                         @php $i = 0; @endphp
                         @foreach ($languageOptions as $locale => $language)
                         @php $i++; @endphp

                         {{ i18nInput("name",trans('attribute::attribute.labels.name').'['.$locale.']',$errors,$locale,['class' => 'form-control required'])}}
                         @endforeach

                         <div class="row">
                             <div class="col-md-6">
                                 {{ normalText("code","attribute::attribute.labels.code", $errors, null,['placeholder' => "attribute::attribute.labels.code","class" => "form-control required"])}}
                             </div>
                             <div class="col-md-6">
                                 {{ normalSelect("input_type","attribute::attribute.labels.input_type",$errors, $inputTypeOptions,null, ["class" => "form-control required",'disabled'=>'true','id'=>'input_type'])}}
                             </div>
                         </div>



                         <div class="switch_custom_option d-none">
                             <label>{{trans("attribute::attribute.labels.custom")}}</label>
                             <div class="custom-control custom-switch">

                                 {{ normalCheckbox('custom_value',"custom_value",$errors,false, ['class'=>'custom-control-input','id'=>'custom_value']) }}
                                 <label class="custom-control-label" for="custom_value" class='switch_custom_option'></label>
                             </div>
                         </div>

                         {{ normalSelect("is_required","attribute::attribute.labels.is_required",$errors,$yesNoOptions,null,  ["class" => "form-control required"])}}
                     </div>
                 </div>
             </div>
         </div>
         <div class="card card-info card-outline card-outline-tabs d-none" id='attribute_option'>
             <div class="card-header p-0 border-bottom-0">
                 <table class="table table-bordered table-striped table-hover text-nowrap filter-option-tbl">
                     <thead id="customTableHead">
                         <tr>
                             @php $i = 0; @endphp
                             @foreach ($languageOptions as $locale => $language)
                             @php $i++; @endphp
                             <th>{{trans("attribute::attribute.titles.name")}} [{{$locale}}]</th>
                             @endforeach
                             <th class="custome_option_fild">{{trans("attribute::attribute.titles.custom_option")}}</th>
                             <th>{{trans("attribute::attribute.titles.sort_order")}}</th>
                             <th>{{trans("attribute::attribute.titles.image")}}</th>
                             <th>{{trans("attribute::attribute.titles.default")}}</th>
                             <th><button type="button" class="btn btn-info" id="addRow"><i class="fa fa-plus" aria-hidden="true"></i></button></th>
                         </tr>
                     </thead>
                     <tbody id="customTableBody">

                     </tbody>
                 </table>
             </div>
         </div>
     </div>
 </div>
 {{ formEnd() }}

 @stop
 @push('js-stack')
 <script type="text/javascript">
     jQuery(document).ready(function() {
         customValue(); //checks custom option is selected or not
         jQuery("#main_form").validate({
             rules: {
                 @foreach($languageOptions as $locale => $language)
                 "{{$locale}}[name]": {
                     required: true,
                     maxlength: 255,
                 },
                 @endforeach
                 code: {
                     required: true,
                     maxlength: 255,
                 },
                 is_required: {
                     required: true
                 },
                 input_type: {
                     required: true
                 },
             },
             submitHandler: function(form) {
                 // Prevent double submission
                 if (!beenSubmitted) {
                     beenSubmitted = true;
                     loaderShow();
                     form.submit();
                 }
             },
         });
         //page load time check is that input type
         var inputTypeElement = jQuery('#input_type').val();
         if (inputTypeElement == 'boolean' || inputTypeElement == 'textbox' || inputTypeElement == 'textarea') {
             jQuery('#attribute_option').addClass('d-none');
             jQuery('.switch_custom_option').addClass('d-none');
         }
         if (inputTypeElement == 'checkbox' || inputTypeElement == 'multiselect' || inputTypeElement == 'radio' || inputTypeElement == 'select') {
             jQuery('#attribute_option').removeClass('d-none');
             jQuery('.switch_custom_option').removeClass('d-none');
         }

         //
         jQuery('#input_type').change(function() {
             var inputTypeElement = jQuery('#input_type').val();
             if (inputTypeElement == 'boolean' || inputTypeElement == 'textbox' || inputTypeElement == 'textarea') {
                 jQuery('#attribute_option').addClass('d-none');
                 jQuery('.switch_custom_option').addClass('d-none');
             }
             if (inputTypeElement == 'checkbox' || inputTypeElement == 'multiselect' || inputTypeElement == 'radio' || inputTypeElement == 'select') {
                 jQuery('#attribute_option').removeClass('d-none');
                 jQuery('.switch_custom_option').removeClass('d-none');
             }
         });
         var i = 0;
         var allowImgTypes = '{{implode(",", getFormatedImageType($imgTypes))}}';
         var image_max_size = "{{trans('core::core.image-note.max-size',['size'=>$image_max_size])}}";
         var imgTypes = '{{trans("core::core.image-note.file-type",["file_type"=>$imgTypes])}}';
         jQuery('#addRow').click(function() {
             $name = '';
             @php $i = 0;
             @endphp
             @foreach($languageOptions as $locale => $language)
             @php $i++;
             @endphp
             $name += '<td>{{ i18nInputAttribute("name","option[index]" ,null, $errors, $locale, null, ["required"=>"true","hide_label"=>"true","class" => "form-control"]) }}<span class="$errorClass"></span></td>';
             @endforeach
             var element = '<tr>' +
                 $name +
                 '<td class="custome_option_fild ' + ((!jQuery('#custom_value').is(":checked")) ? 'd-none' : '') + '">{{ normalText("option[index][custom_option]","", $errors,null ,["class" => "form-control customeOptionClass"])}}<span class="$errorClass"></span></td>' +
             '<td>{{ normalNumber("option[index][sort_order]","",$errors, null,  0,config("core.config.config.smallint_maxlength"),null,["class" => "form-control","required" =>"true"] ) }}<span class="$errorClass"></span></td>' +
             '<td><input type="file" name="option[index][image]" class="required" id="attributeInputFile[index]" accept=' + allowImgTypes + '><div class="image-note mt-2"><lh><b>{{trans("core::core.image-note.label")}}</b></lh><li>' + image_max_size + '</li><li>' + imgTypes + '</li></div></td>' +
            '{{ normalHidden("option[index][default]",2,"",["class"=>"defaultVal"])}} <span class="$errorClass"></span></td>' +
            '<td> {{ normalInputOfType("radio","option[default_radio][]","", $errors, null,["checked"=>false,"class"=>'',"onchange"=>"defaultSelect(this)"])}}' +
            '</td>' +
             '<td><button type="button" class="btn removeRow" onclick="rowRemove(this)"><i class="fas fa-trash" ></i></button></td>' +
             '</tr>';
             element = element.replace(/index/g, i++);
             jQuery("#customTableBody").append(element);
             jQuery('.customeOptionClass').each(function() {
                 jQuery(this).rules("add", {
                     unique: true,
                     messages: {
                         unique: "Please Enter Unique Custome Option",
                     }
                 });
             });
             jQuery.validator.addMethod("unique", function(value, element, params) {
                 var customOptionvals = [];
                 jQuery('.customeOptionClass').each(function(key, element) {
                     customOptionvals.push(jQuery(element).val());
                 });
                 var duplicateCustomeOption = find_duplicate_in_array(customOptionvals);
                 if (duplicateCustomeOption.length > 0) {
                     return false;
                 }
                 return true;
             }, "Value is not unique.");
             customValue(); //checks custom option is selected or not
         });

         jQuery('#custom_value').click(function() {
             customValue(); //checks custom option is selected or not
         });
     });

     function rowRemove(element) {
         jQuery(element).parent().parent().remove();
     }

     function customValue() {
         if (jQuery('#custom_value').is(":checked")) {
             jQuery('.custome_option_fild').removeClass('d-none');
             jQuery('.customeOptionClass').addClass('required', true);
             jQuery('.customeOptionClass').addClass('hide_label', true);
         } else if (jQuery('#custom_value').is(":not(:checked)")) {
             jQuery('.custome_option_fild').addClass('d-none');
             jQuery('.customeOptionClass').removeClass('required', true);
             jQuery('.customeOptionClass').removeClass('hide_label', true);
         }
     }

     function defaultSelect(element) {
         for (let [key, value] of Object.entries(jQuery('.defaultVal'))) {
             jQuery(value).val(2);
         }
         jQuery(element).parent().parent().find('.defaultVal').val(1);
     }

     function find_duplicate_in_array(dataArray) {
         const object = {};
         const result = [];
         dataArray.forEach(item => {

             if (!object[item])
                 object[item] = 0;
             object[item] += 1;
         })
         for (const prop in object) {
             if (object[prop] >= 2) {
                 (!prop == '') ? result.push(prop): null;
             }
         }
         return result;
     }
 </script>
 @endpush