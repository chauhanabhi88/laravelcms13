@extends('theme::layouts.backend.master')

@section('title')
{{ trans("menu::menu.titles.create_menu") }}
@endsection

@section('content-header')
<!-- Content Header (Page header) -->
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("menu::menu.titles.create_menu") }}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.menu.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
<!-- /.content-header -->
@stop

@section('content')
<!-- Main content -->
<!-- form start -->

{{ formStart(null,"POST" ,'admin.menu.store' ,updateUrlParams(), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" menu="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-menu-info-tab" data-toggle="pill" href="#custom-tabs-three-menu-info" menu="tab" aria-controls="custom-tabs-three-menu-info" aria-selected="true">{{ trans("menu::menu.titles.menu_info") }}</a>
                </li>

            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-menu-info" menu="tabpanel" aria-labelledby="custom-tabs-three-menu-info-tab">
                        {{ normalText("menu[label]","menu::menu.labels.label", $errors,null,["class" => "form-control required"])}}
                        <div>
                            <label for="menu.custom_link">{{trans("menu::menu.labels.custom_link")}}</label>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="menu[custom_link]" class="custom-link">
                            <span class="slider round"></span>
                        </label>
                        <div class="text-link hide">
                            {{ normalText("menu[text_link]","menu::menu.labels.link", $errors,null,["class" => "form-control"])}}
                        </div>
                        @if(isset($permissions) && !empty($permissions))
                        <div class="form-group dropdown-link">
                            <label for="menu.link">{{trans("menu::menu.labels.link")}}</label>
                            <select name="menu[dropdown_link]" class="select-2 form-control">
                                <option value=" ">{{' -- ' . trans('core::core.labels.select') . ' -- '}}</option>
                                @foreach ($permissions as $modules)
                                @foreach($modules as $module => $modulePermission)
                                <optgroup label="{{trans($module)}}">{{trans($module)}}</optgroup>
                                @foreach($modulePermission as $value => $label)
                                @if(in_array('index', explode('.', $value)) || in_array('create', explode('.', $value)))
                                <option value="{{$value}}">{{trans($label)}}</option>
                                @endif
                                @endforeach
                                @endforeach
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div>
                            <label for="menu.link_target">{{trans("menu::menu.labels.link_target")}}</label>
                        </div>
                        <label class="switch">
                            <input type="checkbox" value="{{config('core.enabled')}}" name="menu[link_target]">
                            <span class="slider round"></span>
                        </label>
                        {{ normalText("menu[css_class]","menu::menu.labels.css_class", $errors,null,["class" => "form-control"])}}
                        {{ normalText("menu[icon]","menu::menu.labels.icon", $errors,null,["class" => "form-control"])}}
                        {{ normalInputOfType("number","menu[sort_order]", 'banner::banner.labels.sort_order',$errors,0,["class" => "form-control ", "min"=>"0" ])}}
                        <div>
                            <label for="menu.is_system">{{trans("menu::menu.labels.is_system")}}</label>
                        </div>
                        <label class="switch">
                            <input type="checkbox" value="{{config('core.enabled')}}" name="menu[is_system]">
                            <span class="slider round"></span>
                        </label>
                        <div>
                            <label for="menu.status">{{trans("menu::menu.labels.status")}}</label>
                        </div>
                        <label class="switch">
                            <input type="checkbox" value="{{config('core.enabled')}}" name="menu[status]">
                            <span class="slider round"></span>
                        </label>

                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>

{{ formEnd() }}
<!-- /.container-fluid -->
@stop
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        customLinkShowHide();
        jQuery('.custom-link').click(function() {
            customLinkShowHide();
        });

        function customLinkShowHide() {
            if ($('input.custom-link').is(':checked')) {
                jQuery('.text-link').show();
                jQuery('.dropdown-link').hide();
            } else {
                jQuery('.text-link').hide();
                jQuery('.dropdown-link').show();
            }
        }
        jQuery(".select-2").select2({
            width: '100%'
        });
        jQuery("#main_form").validate({
            ignore: [],
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                var classes = element.attr('class');
                classes = classes.split(' ');
                if (classes.includes('custom-file-label')) {
                    error.insertAfter('.input-group.mb-3');
                }


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

    });
</script>
@endpush
