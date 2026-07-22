@extends('theme::layouts.backend.master')


@section('title')
{{ trans("settings::settings.titles.setting") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("settings::settings.titles.setting") }}</h4>
        </div>
        <div class="col-sm-6">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.settings.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.reset") }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('content')

<!-- Main content -->
<!-- /.row -->

{{ formStart(null,"post" ,"admin.settings.save",updateUrlParams(), ['id' => 'main_form'])}}
@csrf
{{ normalInputOfType("hidden","last_module",  "",$errors, null, ['id'=>'last_module_tab','hide_label' => true])}}
<div class="row">
    <div class="card card-info card-outline col-md-12">
        <div class="card-body setting-ac-main">
            <div class="row">
                <div class="col-3 col-sm-3">
                    <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist" aria-orientation="vertical">
                        @php
                        $i = 0;
                        @endphp
                        @foreach($moduleList as $module)
                        @php
                        $path = $module->getPath();
                        @endphp
                        @if(file_exists($path.'/config/settings.php'))
                        @php
                        $i++;
                        @endphp
                        <a data-module="{{ $module->getLowerName() }}" data-url="{{ route('admin.settings.getModuleSetting', updateUrlParams()) }}" class="nav-link module {{ ($i == 1) ?  'active':$i }}" id="{{ $module->getLowerName() }}-tab" data-toggle="pill" href="#{{ $module->getLowerName() }}-content" role="tab" aria-controls="{{ $module->getLowerName() }}" aria-selected="true">{{ $module->getName() }}</a>
                        @endif
                        @endforeach
                    </div>
                </div>
                <div class="col-9 col-sm-9">
                    <div class="tab-content" id="vert-tabs-tabContent">
                        @php
                        $i = 0;
                        $first_module = '';
                        @endphp
                        @foreach($moduleList as $module)
                        <div class="tab-pane text-left fade  {{ ($i == 0) ?  'active show':'' }}" id="{{ $module->getLowerName() }}-content" role="tabpanel" aria-labelledby="{{ $module->getLowerName() }}-tab">
                            <div class="col-md-12">
                                @if ($i <= 0) @php $elements=[]; $path=$module->getPath();
                                    if(file_exists($path.'/config/settings.php')){
                                        if($first_module == ''){
                                        $first_module = $module->getLowerName();
                                        }
                                        $i++;
                                        $elements = require_once($path.'/config/settings.php');
                                        
                                        $setting = $settings->getModuleSettings($module->getLowerName());
                                        $settingData = [];
                                        if($setting){
                                        $settingData = json_decode($setting->value, true);
                                        }
                                    }
                                    @endphp
                                    @if($i == 1)
                                    @include('settings::backend.partials.settings',compact( 'elements', 'module', 'settingData'))
                                    @endif
                                    @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card -->
    </div>
    <!-- /.card -->
</div>
{{ formEnd()}}
@stop
@push('js-stack')
<script type="text/javascript">
    $(document).ready(function() {
        jQuery("#{{$first_module}}-tab").addClass('disabled');
        jQuery("#last_module_tab").val('<?php echo $first_module; ?>');
        var firstModuleSettingOn = "{{old('firstModuleSettingOn')}}";
        if(firstModuleSettingOn == 1)
        {
            jQuery("#{{$first_module}}-tab").removeClass('disabled');
        }
        var tab = "{{old('tab')}}";
        if (tab != '') {
            var tab_module = "{{old('tabpanel')}}";
            jQuery("#last_module_tab").val(tab_module);
            var first_module = '<?php echo $first_module; ?>';
            jQuery(tab).trigger('click');
            if (tab_module != first_module) {
                $('.tab-pane').each(function(key, value) {
                    $(value).removeClass('show');
                    $(value).removeClass('active');
                });
                data = customObj.setUrl('{{route("admin.settings.getModuleSetting",updateUrlParams())}}').setParams({
                    module: tab_module
                }).getContent();
            }
        }

        $(".module").click(function(event) {
            $(this).off(event);
            jQuery("#{{$first_module}}-tab").removeClass('disabled');
            $('.tab-pane').each(function(key, value) {
                $(value).removeClass('show');
                $(value).removeClass('active');
            });
            jQuery("#last_module_tab").val($(this).data("module"));
            var url = $(this).data("url");
            var data = customObj.setUrl(url).setParams({
                module: $(this).data("module")
            }).getContent();
        });
        $('#main_form').validate({
            rules: {
                'db[travel][package-prefix]': {
                    unique: true,
                },
                'db[travel][travel-prefix]': {
                    unique: true
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

        })

    });
</script>
@endpush
