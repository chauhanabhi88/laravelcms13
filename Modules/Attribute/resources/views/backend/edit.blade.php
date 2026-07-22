@extends('theme::layouts.backend.master')

@section('title')
{{ trans("attribute::attribute.titles.edit_attribute") }} {{wordWrapper($attribute->name, true)}}
@endsection

@section('content-header')
@php
$attributeSettings = settings("attribute");
$imgTypes = (!empty($attributeSettings['image_type']))?$attributeSettings['image_type']:'-';
$maxUploadSize = (!empty($attributeSettings['max_upload_size'])) ? $attributeSettings['max_upload_size'] : 0;
$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
$image_max_size = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;
@endphp
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("attribute::attribute.titles.edit_attribute") }} {{wordWrapper($attribute->name, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.attribute.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.attribute.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.attribute.delete', updateUrlParams([$attribute->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
<!-- /.content-header -->
@stop

@section('content')
<!-- Main content -->
<!-- form start -->

{{ formStart(null,"put" ,'admin.attribute.update',updateUrlParams([$attribute->id]), ['id' => 'main_form', "class" => "commentForm", "enctype"=>"multipart/form-data"])}}
{{ normalHidden("snc", 0, 'snc', ['class' => 'snc']) }}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-attribute-info" attribute="tabpanel" aria-labelledby="custom-tabs-three-attribute-info-tab">
                        @php $i = 0; @endphp
                        @foreach ($languageOptions as $locale => $language)
                        @php $i++; @endphp

                        {{ i18nInput("name",trans('attribute::attribute.labels.name').'['.$locale.']',$errors,$locale,$attribute,['class' => 'form-control required'])}}

                        @endforeach

                        <div class="row">
                            <div class="col-md-6">
                                {{ normalText("code","attribute::attribute.labels.code", $errors, $attribute->code,["class" => "form-control required"])}}
                            </div>
                            <div class="col-md-6">
                                {{ normalSelect("input_type","attribute::attribute.labels.input_type",$errors, $inputTypeOptions,$attribute->input_type, ["class" => "form-control required",'disabled'=>'true','id'=>'input_type'])}} 
                            </div>
                        </div>

                        <div id="switch_custom_option" class="{{(!in_array($attribute->input_type, $optionableInput))?'d-none':''}}">
                        <label>{{trans("attribute::attribute.labels.custom")}}</label>
                            <div class="custom-control custom-switch">
                                {{ normalCheckbox('custom_value','',$errors,(isset($attribute->custom_value) && $attribute->custom_value ==1)? true:false, ['class'=>'custom-control-input','id'=>'custom_value']) }}
                                <label class="custom-control-label" for="custom_value" id='switch_custom_option'></label>
                            </div>
                        </div>
                       
                        {{ normalSelect("is_required","attribute::attribute.labels.is_required",$errors,$yesNoOptions,$attribute->is_required,  ["class" => "form-control required"])}} 
                    </div>
                </div>
            </div>
        </div>
        <div class="card card-info card-outline card-outline-tabs {{(!in_array($attribute->input_type,$optionableInput))?'d-none':''}}" id='attribute_option'>
            <div class="card-header p-0 border-bottom-0">
                <table class="table table-bordered table-striped table-hover text-nowrap filter-option-tbl">
                    <thead id="customTableHead">
                        @if(in_array($attribute->input_type, $optionableInput))
                        <tr>
                            @php $i = 0; @endphp
                            @foreach ($languageOptions as $locale => $language)
                            @php $i++; @endphp
                            <th>{{trans("attribute::attribute.titles.name")}} [{{$locale}}]</th>
                            @endforeach
                            <th class="custome_option_fild {{($attribute->custom_value != config('core.yes'))?'d-none':''}}">{{trans("attribute::attribute.titles.custom_option")}}</th>
                            <th>{{ trans("attribute::attribute.titles.sort_order") }}</th>
                            <th>{{trans("attribute::attribute.titles.image")}}</th>
                            <th>{{trans("attribute::attribute.titles.default")}}</th>
                            <th><button type="button" class="btn btn-primary" id="addOptionRow"><i class="fa fa-plus" aria-hidden="true"></i></button></th>
                        </tr>
                        @endif
                    </thead>
                    <tbody id="customTableBody">
                        {{ normalHidden("delete_ids",null, "delete_ids",['id' => 'delete_ids'])}}
                        @if(isset($attributeOption) && $attributeOption)
                        @foreach($attributeOption as $key=>$option)
                        <tr>
                            @php $i = 0; @endphp
                            @foreach ($languageOptions as $locale => $language)
                            @php $i++; @endphp

                            <td>
                            {{ normalHidden("option[old][$key][id]",$option->id,"", [])}}

                                {{ i18nInputAttribute("name","option[old][$key]",null, $errors,$locale, $option, ["required"=>"true","hide_label"=>"true","class" => 'form-control'])}} 
                            </td>
                            @endforeach
                            <td class="custome_option_fild {{($attribute->custom_value != config('core.yes'))?'d-none':''}}">
                            {{ normalText("option[old][$key][custom_option]",trans("attribute::attribute.titles.custom_option"), $errors, $option->custom_option,["hide_label"=>"true","class" => "form-control customeOptionClass"])}}

                            </td>
                            <td>
                            {{ normalInputOfType("number",'option[old]['.$key.'][sort_order]','', $errors, $option->sort_order,["hide_label"=>"true",'min'=>'0',"required"=>"true", "maxlength" => config("core.config.config.smallint_maxlength")])}}
                            </td>
                            <td> 
                                
                                {{ normalFile('option[old]['.$key.'][image]','option[old]['.$key.'][image]',$errors,['id'=>'option[old]['.$key.'][image]', 'accept' =>implode(",", getFormatedImageType($imgTypes))])}}
                                @php
                                    $og_image_param = [
                                    'module' => config("attribute.name"),
                                    'image' => $option->image,
                                    ];
                                    $thumbnail_image_param = [
                                    'image-type' => 'thumbnail',
                                    'module' => config("attribute.name"),
                                    'image' => $option->image,
                                    ];
                                @endphp
                                @if(getImageUrl($og_image_param))
                                    <a href="{{getImageUrl($og_image_param)}}" target="_BLANK" class="mt-2">
                                        <img src="{{getImageUrl($thumbnail_image_param)}}" class="img-responsive center-block" height=200 width=200>
                                    </a>
                                @endif
                                <div class="image-note mt-2">
                                    <lh><b>{{trans("core::core.image-note.label")}}</b></lh>
                                    <li>{{trans("core::core.image-note.max-size",['size'=>$image_max_size])}}</li>
                                    <li>{{trans("core::core.image-note.file-type",['file_type'=>$imgTypes])}}</li>
                                </div>
                            </td>
                            <td>

                            {{ normalRadioForAttribute('option[default_radio][]','', $errors, ($option->default == 1)?true:false,["checked"=>($option->default == 1)?"true":false,"class"=>'test',"onchange"=>"defaultSelect(this)"])}}
                            
                            {{ normalHidden('option[old]['.$key.'][default]',$option->default, "",["class"=>"defaultClass"])}}  
                                
                            {{ normalHidden("option[old][$key][id]",$option->id, "", [])}} 
                        </td>
                            <td><button type="button" class="btn" data-id="{{$option->id}}" onclick="rowRemove(this)"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}

<!-- /.container-fluid -->
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
                 is_required:
                {
                    required:true
                },
                input_type:
                {
                    required:true
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

         uniqueCustom();//checks custom option is unique

         var i = 0;
         var allowImgTypes = '{{implode(",", getFormatedImageType($imgTypes))}}';
         var image_max_size = "{{trans('core::core.image-note.max-size',['size'=>$image_max_size])}}";
         var imgTypes = '{{trans("core::core.image-note.file-type",["file_type"=>$imgTypes])}}';
         jQuery('#addOptionRow').click(function() {
             $name = '';
             @php $i = 0;
             @endphp
             @foreach($languageOptions as $locale => $language)
             @php $i++;
             @endphp
             $name += '<td>{{ i18nInputAttribute("name","option[new][index]" ,null, $errors, $locale, null, ["required"=>"true","hide_label"=>"true","class" => "form-control"]) }}<span class="$errorClass"></span></td>';
             @endforeach

             var element = '<tr>' +
                 $name +
                 '<td class="custome_option_fild ' + ((!jQuery('#custom_value').is(":checked")) ? 'd-none' : '') + '">{{ normalText("option[new][index][custom_option]",'', $errors,null ,["class" => "form-control customeOptionClass"])}}<span class="$errorClass"></span></td>' +
                 '<td>{{ normalNumber("option[new][index][sort_order]",'',$errors, null,  0,config("core.config.config.smallint_maxlength"),null,["class" => "form-control","required" =>"true"] ) }}<span class="$errorClass"></span></td>' +
                 '<td><input type="file" name="option[new][index][image]" class="required" id="attributeInputFile[index]" accept='+ allowImgTypes + '><div class="image-note mt-2"><lh><b>{{trans("core::core.image-note.label")}}</b></lh><li>'+ image_max_size + '</li><li>' + imgTypes + '</li></div></td>'+
                 '<td> {{ normalHidden("option[new][index][default]",2,"",["class"=>"defaultClass"])}} <span class="$errorClass"></span>' +
                 '{{ normalRadioForAttribute("option[default_radio][]","", $errors, 2,["checked"=>false,"class"=>"test","onchange"=>"defaultSelect(this)"])}}' +
                 '</td>' +
                 '<td><button type="button" class="btn removeRow" onclick="rowRemove(this)"><i class="fas fa-trash" ></i></button></td>' +
                 '</tr>';
             element = element.replace(/index/g, i++);
             jQuery("#customTableBody").append(element);

            uniqueCustom();//checks custom option is unique

            customValue(); //checks custom option is selected or not
         });

         jQuery('#custom_value').click(function() {
            customValue(); //checks custom option is selected or not
         });
     });

     function customValue()
     {
        if (jQuery('#custom_value').is(":checked")) {
            jQuery('.custome_option_fild').removeClass('d-none');
            jQuery('.customeOptionClass').addClass('required',true);
            jQuery('.customeOptionClass').addClass('hide_label',true);
        } else if (jQuery('#custom_value').is(":not(:checked)")) {
            jQuery('.custome_option_fild').addClass('d-none');
            jQuery('.customeOptionClass').removeClass('required',true);
            jQuery('.customeOptionClass').removeClass('hide_label',true);
        }
     }

     function uniqueCustom()
     {
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
     }

     function rowRemove(element) {
        var deleteId = jQuery(element).attr("data-id");
        var currentValDelete = jQuery('#delete_ids').val();
        var deleteIds;
        deleteIds = (currentValDelete == "") ? deleteId : currentValDelete + "," + deleteId;
        jQuery('#delete_ids').val(deleteIds);
        jQuery(element).parent().parent().remove();
    }

    function defaultSelect(element) {
        for (let [key, value] of Object.entries(jQuery('.defaultClass'))) {
            jQuery(value).val(2);
        }
        jQuery(element).parent().parent().find('.defaultClass').val(1);
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
                if (!prop == '') {
                    result.push(prop);
                }
             }
         }
         return result;
     }
</script>
@endpush
