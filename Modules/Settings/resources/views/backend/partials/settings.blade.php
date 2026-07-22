@php
$i = 0;
@endphp
@forelse ($elements as $group => $items)
@php
$group = trans($group);
$moduleName = str_replace(" ", "_", $group);
@endphp
<div class="accordion md-accordion" id="accordionEx" role="tablist" aria-multiselectable="true">
    <div class="card">
        <div class="card-header change-pointer collapsed" role="tab" id="headingOne1" data-toggle="collapse" data-target="#collapse_{{ $moduleName }}_{{ $i }}" aria-expanded="true">
            <a class="btn-tool">
                <h3 class="mb-0">{{ trans($group) }}</h3>
            </a>
            <div class="card-tools">
                <button type="button" class="btn btn-tool filterAccordian {{ ($i == 0) ? '' : 'collapsed' }}" aria-expanded="true" aria-controls="collapse_{{ $moduleName }}_{{ $i }}" data-toggle="collapse" data-target="#collapse_{{ $moduleName }}_{{ $i }}"></button>
            </div>
        </div>
        <!-- <a data-toggle="collapse" href="#collapse_{{ $moduleName }}_{{ $i }}">
            <div class="card-header" role="tab" id="headingOne1">
                <h3 class="ttl-filter">{{ trans($group) }}</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool filterAccordian {{ ($i == 0) ? '' : 'collapsed' }}" data-toggle="collapse" href="#collapse_{{ $moduleName }}_{{ $i }}" aria-expanded="true" aria-controls="collapse_{{ $moduleName }}_{{ $i }}"></button>
                </div>
            </div>
        </a> -->
        <div id="collapse_{{ $moduleName }}_{{ $i }}" class="collapse {{ ($i == 0) ? 'show' : '' }}" role="tabpanel" aria-labelledby="headingOne1" data-parent="#accordionEx">
            <div class="card-body table-responsive">
                <div class="col-lg-12">
                    @foreach($items as $fieldName => $item)
                    <div class="row">
                        <div class="col-md-4">
                        {{isset($item['label']) ? trans($item['label']) : $fieldName}}
                        </div>
                        <div class="col-md-8">
                            @php
                            //dd($item['label']);                            
                            //dd($fieldName);                            

                            if($item['storage'] == 'db') {
                            $value = (isset($settingData[$fieldName]) ? $settingData[$fieldName] : (isset($item['default']) ? $item['default'] : null));
                            } else {
                            $value = config($fieldName, (isset($item['default']) ? $item['default'] : null));
                            }
                            $storage = (isset($item['storage']) && $item['storage']) ? $item['storage'] : "";
                            $fieldName = ($item['storage'] == 'db') ? $fieldName : $item['env_key'];
                            @endphp
                            @if ($item['type'] == "text")
                            
                            
                            {{ normalText(sprintf("%s[%s][%s]", $storage, $module->getLowerName(), $fieldName),"contact::contact.labels.name", $errors,$value,["class" => "form-control","placeholder" => isset($item['placeholder']) ? trans($item['placeholder']) : null,
                            "hide_label" => true,
                            "append" => (isset($item['comment']) && $item['comment']) ? '<span class="setting_comment">'.trans($item['comment']).'</span>' : ""
                            ])}}
                            
                            @elseif($item['type'] == "number")
                            
                            
                            {{ normalNumber(sprintf("%s[%s][%s]", $storage, $module->getLowerName(), $fieldName),$item['label'],$errors, $value, (isset($item['min']) && $item['min']) ? $item['min'] : 0,(isset($item['max']) && $item['max']) ? $item['max'] : null,null,["class" => "form-control",
                            "placeholder" => isset($item['placeholder']) ? trans($item['placeholder']) : null,
                            "hide_label" => true,"append" => (isset($item['comment']) && $item['comment']) ? '<span class="setting_comment">'.trans($item['comment']).'</span>' : ""],
                            )}}

                            

                            @elseif($item['type'] == "select")
                            {{ normalSelect(sprintf("%s[%s][%s]", $storage, $module->getLowerName(), $fieldName),$item['label'],$errors, $item['options'],$value, ["class" => "custom-select","hide_label" => true])}} 

                            @elseif($item['type'] == "password")
                            @php
                                // Masked placeholder if value exists
                                $displayValue = $value ? '************' : '';
                            @endphp

                            {{ normalInputOfType(
                                'password',
                                sprintf("%s[%s][%s]", $storage, $module->getLowerName(), $fieldName),
                                $item['label'],
                                $errors,
                                $displayValue,
                                [
                                    "class" => "form-control",
                                    "placeholder" => isset($item['placeholder']) ? trans($item['placeholder']) : null,
                                    "hide_label" => true,
                                    "autocomplete" => "new-password",
                                    "append" => (isset($item['comment']) && $item['comment'])
                                        ? '<span class="setting_comment">'.trans($item['comment']).'</span>'
                                        : ""
                                ]
                            )}}
                        @endif



                        </div>
                    </div>                    
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@php
$i++;
@endphp
@empty
<p> {{ trans("core::core.messages.no_records") }} </p>
@endforelse

@push('js-stack')
<script type="text/javascript">
    var error = '<?php echo trans('travel::package.messages.prefix_error'); ?>';
    jQuery(document).ready(function() {
        jQuery.validator.addMethod("unique", function(value, element) {
            return $("input[name='db[travel][package-prefix]']").val() != $("input[name='db[travel][travel-prefix]']").val();
        }, error);
        /*$(document).on('blur', "input[name='db[travel][package-prefix]']","input[name='db[travel][travel-prefix]']" , function(){
            $('.prefix-error').remove();
            if( $("input[name='db[travel][package-prefix]']").val() !='' && $("input[name='db[travel][travel-prefix]']").val() !='' ) {
                if ($("input[name='db[travel][package-prefix]']").val() == $("input[name='db[travel][travel-prefix]']").val()) {
                    $('<p class="prefix-error">'+error+'</p>').insertBefore("input[name='db[travel][travel-prefix]']");
                } else { return true; }
            }
        });*/
    });
</script>
@endpush