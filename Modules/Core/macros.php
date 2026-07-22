<?php

use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;

/*
* start form tag
*/
if (!function_exists("formStart")) {
    function formStart($model=null,$method=null, $route=null,$params=[],  array $options = []) {
        $string ="";
        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
            $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        if($model){
            $string .= html()->form($method)->route($route,$params)->id($id)->class($class)->acceptsFiles()->open() ;
        }elseif($route == null && $params == null){
            $string .= html()->form($method)->id($id)->class($class)->acceptsFiles()->open() ;
        }else{
            $string .= html()->form($method)->route($route,$params)->id($id)->class($class)->acceptsFiles()->open() ;
        }
        
        return new HtmlString($string);
    }
}

if (!function_exists("formEnd")) {
    function formEnd() {
        $string ="";
        $string .= html()->form()->close() ;
        return new HtmlString($string);
    }
}

if (!function_exists("normalText")) {
    function normalText($name, $title, ViewErrorBag $errors, $value = null, array $options = []){
        $errorField = cleanFieldName($name);
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'form-control', 'hide_label' => false, "form_div"=>true], $options);
        $maxLength = config("core.varchar_maxlength");
        $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        $errorClass = "";
        
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }
        
        $string = '';
       
        if($options['form_div']){
            
            $string = '<div class="form-group req">';
        }
        
        if (!$options['hide_label']) {
            $element = html()->label($labelTitle, $name);
            if($errorClass){
                $element->class($errorClass);
            }
            
            $string .= $element;
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }
        
        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? trans($options['placeholder']) : "";
        $readonly = isset($options['readonly']) && $options['readonly'] ? $options['readonly'] : null;
        $dataSlug = isset($options['data-slug']) && $options['data-slug'] ? $options['data-slug'] : "";
        
        if(isset($readonly)){
            $string .=  html()->text($name,old($errorField, $value))->class($class)->id($id)->placeholder($placeholder)->isReadonly(true)->attribute("data-slug" , $dataSlug);
        }else{
            $string .=  html()->text($name,old($errorField, $value))->class($class)->id($id)->placeholder($placeholder)->attribute("data-slug" , $dataSlug);
        }

        if (isset($options['append']) && $options['append']) {
            $string .= $options['append'];
        }

        $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');

        if($options['form_div']){
            $string .= '</div>';
        }
        
        return new HtmlString($string);
    }
}

if (!function_exists("normalHidden")) {
    function normalHidden($name, $value = null,$id, array $options = []){
        //$errorField = cleanFieldName($name);
        //$labelTitle = trans($title);
        //$options = array_merge(['class' => 'form-control', 'placeholder' => $labelTitle, 'hide_label' => false], $options);
        //$maxLength = config("core.varchar_maxlength");
        //$options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        /*$errorClass = "";
        
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }*/   
        //$string = '<div class="form-group req">';
        /*if (!$options['hide_label']) {
            $string .= html()->label($errorField, $labelTitle)->class([$errorClass]);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }*/

        //$class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        //$id = isset($options['id']) && $options['id'] ? $options['id'] : "";

        //$string =  html()->hidden($name, $value)->id($id);
        
        /*if (isset($options['append']) && $options['append']) {
            $string .= $options['append'];
        }*/

        //$string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');

        //$string .= '</div>';
        $options = array_merge(['class' => ''], $options);
        if($id){
            $string =  html()->hidden($name, $value)->id($id)->class($options['class']);
        }else{
            $string =  html()->hidden($name, $value)->class($options['class']);
        }
        
        return new HtmlString($string);
    }
}

if (!function_exists("normalSelect")) {
    function normalSelect($name, $title, ViewErrorBag $errors, array $choice, $value = null, array $options = []) {
        $errorField = cleanFieldName($name);
        if (array_key_exists('multiple', $options)) {
            $nameForm = $name . '[]';
        } else {
            $nameForm = $name;
        }
    
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'custom-select', 'hide_label' => false,"form_div"=>true], $options);
    
        $errorClass = "";
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }
    
        $string = '';
       
        if($options['form_div']){
            
            $string = '<div class="form-group req">';
        }

        if (!$options['hide_label']) {
            $string .= html()->label($labelTitle,$errorField);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }
        unset($options['hide_label']);
        $multiple = isset($options['multiple']) && $options['multiple'] ? $options['multiple'] : null;        
        $id = (isset($options['id']) && $options['id']) ? $options['id'] : null;
        if(isset($multiple)){
            $element = html()->select($name, $choice, old($errorField, $value))->class($options['class'])->multiple(true);
        }else if($id) {
            $element = html()->select($name, $choice, old($errorField, $value))->class($options['class'])->attribute('id', $id);
        } else {
            $element = html()->select($name, $choice, old($errorField, $value))->class($options['class']);
        }
        
        $string .= $element.$errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
        if($options['form_div']){
            $string .= '</div>';
        }
        return new HtmlString($string);
    }
}


if (!function_exists("normalNumber")) {
    function normalNumber($name, $title, ViewErrorBag $errors, $value = null,$min = null,$max = null, $step = null,array $options = []){
        $errorField = cleanFieldName($name);
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'form-control', 'placeholder' => $labelTitle, 'hide_label' => false,"form_div"=>true], $options);
        $maxLength = config("core.varchar_maxlength");
        $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        $errorClass = "";
        
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }

        $string = '';
        if($options['form_div']){
            
            $string = '<div class="form-group req">';
        }

        if (!$options['hide_label']) {
            $string .= html()->label( $labelTitle,$errorField)->class([$errorClass]);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? $options['placeholder'] : "";

        $string .=  html()->number($name,old($errorField, $value),$min , $max , $step)->class($class)->id($id)->placeholder($placeholder);
        if (isset($options['append']) && $options['append']) {
            $string .= $options['append'];
        }

        $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');

        if($options['form_div']){
            $string .= '</div>';
        }
        return new HtmlString($string);
    }
}

if (!function_exists("normalCheckbox")) {
    function normalCheckbox($name, $title, ViewErrorBag $errors, $value = null, $options = []){
        
        
        if(!isset($options['grid'])){
            $errorField = cleanFieldName($name);
            $labelTitle = trans($title);
            
            $class = " custom-control-input " . ($errors->has($errorField) ? ' is-invalid' : '') . "";
            $options = array_merge(['class' => 'custom-control-input', 'placeholder' => $labelTitle,'hide_label'=>false], $options);
            $options['class'] .= $class;

            $options['id'] = $errorField;
            $string = "<div class='custom-control custom-checkbox'>";

            
            $checked = isset($options['checked']) && $options['checked'] ? $options['checked'] : false;
            //$string .= Form::checkbox($name, $value, $currentData, $options);
            $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
            $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
            
            $string .=  html()->checkbox($name, $checked, $value)->attribute('data-bootstrap-switch', isset($options["data-bootstrap-switch"]) && $options['data-bootstrap-switch'] ? "" : false)->class($class)->id($id)->attribute("data-on-text",isset($options["data-on-text"]) && $options['data-on-text'] ? $options["data-on-text"] : "" )->attribute("data-off-text",isset($options["data-off-text"]) && $options['data-off-text'] ? $options["data-off-text"] : "" )->attribute('data-on-color', isset($options["data-on-color"]) && $options['data-on-color'] ? $options["data-on-color"] : "")->attribute('data-module', isset($options["data-module"]) && $options['data-module'] ? $options["data-module"] : "")->attribute('data-id', isset($options["data-id"]) && $options['data-id'] ? $options["data-id"] : "");
            //dd($title);
            

            if (!$options['hide_label']) {
                $string .= "<label class='custom-control-label' for='$errorField'>";
            }
            
            $string .= $labelTitle;
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk error">*</span>';
            }
            $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
            //$string .= '</label>';
            if (!$options['hide_label']) {
                $string .= '</label>';
            }
            $string .= '</div>';
        
            return new HtmlString($string);
        }else{
            $errorField = cleanFieldName($name);
            $labelTitle = trans($title);

            $options['id'] = $errorField;
            
            $checked = isset($options['checked']) && $options['checked'] ? $options['checked'] : false;
            //$string .= Form::checkbox($name, $value, $currentData, $options);
            $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
            $id = isset($options['id']) && $options['id'] ? $options['id'] : "";

            $string =  html()->checkbox($name, $checked, $value)->class($class)->id($id)->attribute('data-id', isset($options["data-id"]) && $options['data-id'] ? $options["data-id"] : "");

            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk error">*</span>';
            }
            $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
        
            return new HtmlString($string);
        }
    }
}

if (!function_exists("normalRadioForAttribute")) {
    function normalRadioForAttribute($name, $title, ViewErrorBag $errors, $value = null, $options = []){

            $errorField = cleanFieldName($name);
            $labelTitle = trans($title);
            
            $class = ($errors->has($errorField) ? ' is-invalid' : '') . "";
            $options = array_merge([ 'placeholder' => $labelTitle], $options);
            $options['class'] .= $class;

            $options['id'] = $errorField;
           // $string = '<div class="custom-control custom-checkbox">';
             $string = '';
            
            $checked = isset($options['checked']) && $options['checked'] ? $options['checked'] : false;
            //$string .= Form::checkbox($name, $value, $currentData, $options);
            $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
            $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
            $onChange = isset($options['onchange']) && $options['onchange'] ? $options['onchange'] : "";

            $string .=  html()->radio($name, $checked, $value)->class($class)->id($id)->attribute('onchange' , $onChange);
            
            //$string .= '<label class="custom-control-label" for="$errorField">';
            //$string .= $labelTitle;
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk error">*</span>';
            }
            $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
            $string .= '</label>';
           // $string .= '</div>';
        
            return new HtmlString($string);
    }
}


if (!function_exists("normalRadio")) {
    function normalRadio($name, $title, ViewErrorBag $errors, $value = null, $options = []){

            $errorField = cleanFieldName($name);
            $labelTitle = trans($title);
            
            $class = " custom-control-input " . ($errors->has($errorField) ? ' is-invalid' : '') . "";
            $options = array_merge(['class' => 'custom-control-input', 'placeholder' => $labelTitle], $options);
            $options['class'] .= $class;

            $options['id'] = $errorField;
            $string = '<div class="custom-control custom-checkbox">';

            
            $checked = isset($options['checked']) && $options['checked'] ? $options['checked'] : false;
            //$string .= Form::checkbox($name, $value, $currentData, $options);
            $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
            $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
            $onChange = isset($options['onchange']) && $options['onchange'] ? $options['onchange'] : "";

            $string .=  html()->radio($name, $checked, $value)->class($class)->id($id)->attribute('onchange' , $onChange);
            
            $string .= '<label class="custom-control-label" for="$errorField">';
            $string .= $labelTitle;
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk error">*</span>';
            }
            $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
            $string .= '</label>';
            $string .= '</div>';
        
            return new HtmlString($string);
    }
}

if (!function_exists("normalFile")) {
    function normalFile($name, $title, ViewErrorBag $errors, array $options = []){
        /*if (array_key_exists('multiple', $options)) {
            $nameForm = "{$lang}[$name][]";
        } else {
            $nameForm = "{$lang}[$name]";
        }
    
        $options = array_merge(['class' => 'form-control'], $options);*/
    
        //$string = "<div class='form-group req " . ($errors->has($name) ? ' has-error' : '') . "'>";
        //$string .= "<label for='$name'>$title</label>";
    
        /*if (is_object($object)) {
            $currentData = $object->hasTranslation($lang) ? $object->translate($lang)->{$name} : '';
        } else {
            $currentData = false;
        }*/
    
        //$string .= Form::file("{$lang}[{$name}]", $options);
        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $accept = isset($options['accept']) && $options['accept'] ? $options['accept'] : "";

        $string = html()->file($name)->class($class)->id($id)->accept($accept);
        //html()->file('image')->class('custom-file-label form-control  is-invalid required')->id('bannerInputFile')
    
        $string .= $errors->first($name, '<label class="error">:message</label>');
        //$string .= '</div>';
        return new HtmlString($string);
    }
}

if (!function_exists("normalInputOfType")) {
    function normalInputOfType($type, $name, $title, ViewErrorBag $errors, $value = null, array $options = []){
        $string = '';
        $errorField = cleanFieldName($name);
        $labelTitle = trans($title);
       // dd($options);
        $options = array_merge(['class' => 'form-control', 'placeholder' => $labelTitle, 'hide_label' => false,"form_div"=>true], $options);
        $maxLength = config("core.smallint_maxlength");
        if ($type == "email" || $type == "password" || $type == "hidden") {
            $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : config("core.varchar_maxlength");
        } else {
            $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        }
        $errorClass = "";
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }

        $string = '';
       
        if($options['form_div']){
            
            $string = '<div class="form-group req">';
        }
        
        if (!$options['hide_label']) {
            //$string .= Form::label($errorField, $labelTitle, ["class" => $errorClass]);
            $string .= html()->label($labelTitle,$errorField)->class([$errorClass]);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }   

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder']
        ? trans($options['placeholder'])
        : $labelTitle;

        //$string .= Form::input($type, $name, old($errorField, $value), $options);
        $string .=  html()->input($type, $name, old($errorField, $value))->class($class)->id($id)->placeholder($placeholder);
        
        if (isset($options['append']) && $options['append']) {
            $string .= $options['append'];
        }

        $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');

        if($options['form_div']){
            $string .= '</div>';
        }
        return new HtmlString($string);
    }
}
if (!function_exists("i18nInput")) {
    function i18nInput($name, $title, ViewErrorBag $errors, $lang, $object = null, array $options = []){

        $options = array_merge(['class' => 'form-control', 'placeholder' => $title, 'hide_label' => false,"form_div"=>true], $options);
        $maxLength = config("core.varchar_maxlength");
        $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        $errorClass = ' has-error';
        //$string = "<div class='form-group req " . ($errors->has($lang . '.' . $name) ? ' has-error' : '') . "'>";
        $string = '';
       
        if($options['form_div']){
            
            $string = "<div class='form-group req " . ($errors->has($lang . '.' . $name) ? ' has-error' : '') . "'>";
        }

        $string .= html()->label($title,"{$lang}[{$name}]");

        if (!$options['hide_label']) {
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }

        if (is_object($object)) {
            $currentData = $object->hasTranslation($lang) ? $object->translate($lang)->{$name} : '';
        } else {
            $currentData = '';
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? $options['placeholder'] : "";
        $dataSlug = isset($options['data-slug']) && $options['data-slug'] ? $options['data-slug'] : "";

        //$string .= Form::text("{$lang}[{$name}]", old("{$lang}[{$name}]", $currentData), $options);
        $string .=  html()->text("{$lang}[{$name}]",old("{$lang}[{$name}]", $currentData))->class($class)->id($id)->placeholder($placeholder)->attribute("data-slug" , $dataSlug);;
        $string .= $errors->first("{$lang}.{$name}", '<label class="error">:message</label>');
        if($options['form_div']){
            $string .= '</div>';
        }

        return new HtmlString($string); 
    }
}

if(!function_exists("i18nInputOfType")){
    function i18nInputOfType($type, $name, $title, ViewErrorBag $errors, $lang, $object = null, array $options = []) {
        $options = array_merge(['class' => 'form-control', 'placeholder' => $title, 'hide_label' => false], $options);
        $maxLength = config("core.smallint_maxlength");
        if ($type == "email" || $type == "password" || $type == "hidden") {
            $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : config("core.varchar_maxlength");
        } else {
            $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;
        }
        $errorClass = ' has-error';
        $string = "<div class='form-group req " . ($errors->has($lang . '.' . $name) ? ' has-error' : '') . "'>";
        //$string .= Form::label("{$lang}[{$name}]", $title);
        $string .= html()->label("{$lang}[{$name}]", $title);
    
        if (!$options['hide_label']) {
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }
    
        if (is_object($object)) {
            $currentData = $object->hasTranslation($lang) ? $object->translate($lang)->{$name} : '';
        } else {
            $currentData = '';
        }
    
        //$string .= Form::input($type, "{$lang}[{$name}]", old("{$lang}[{$name}]", $currentData), $options);
        $string .=  html()->input($type,"{$lang}[{$name}]",old("{$lang}[{$name}]", $currentData))->class($options['class']);
        $string .= $errors->first("{$lang}.{$name}", '<label class="error">:message</label>');
        $string .= '</div>';
    
        return new HtmlString($string);
    }
}

if (!function_exists("i18nTextarea")) {
    function i18nTextarea($name, $title, ViewErrorBag $errors, $lang, $object = null, array $options = []){

        $options = array_merge(['class' => 'ckeditor', 'rows' => 10, 'cols' => 10, 'hide_label' => false,"form_div"=>true], $options);

        $errorClass = ' has-error';

        $string = '';
       
        if($options['form_div']){
            
            $string = "<div class='form-group req " . ($errors->has($lang . '.' . $name) ? ' has-error' : '') . "'>";
        }

        
        $string .= html()->label($title,"{$lang}[{$name}]");
    
        if (!$options['hide_label']) {
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }
    
        if (is_object($object)) {
            $currentData = $object->hasTranslation($lang) ? $object->translate($lang)->{$name} : '';
        } else {
            $currentData = '';
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? $options['placeholder'] : "";
    
        //$string .= Form::textarea("{$lang}[$name]", old("{$lang}[{$name}]", $currentData), $options);
        $string .=  html()->textarea("{$lang}[{$name}]",old("{$lang}[{$name}]", $currentData))->class($class)->id($id)->placeholder($placeholder);
        $string .= $errors->first("{$lang}.{$name}", '<label class="error">:message</label>');
        if($options['form_div']){
            $string .= '</div>';
        }
    
        return new HtmlString($string);
    }
}

if (!function_exists("normalTextarea")) {
    function normalTextarea($name, $title, ViewErrorBag $errors, $value = null, array $options = []){
        $errorField = cleanFieldName($name);
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'form-control', 'placeholder' => $labelTitle, 'hide_label' => false,"form_div"=>true], $options);

        $errorClass = "";
        if ($errors->has($errorField)) {
            $options['class'] = $options['class'] . " is-invalid";
            $errorClass = "error";
        }

        $string = '';
       
        if($options['form_div']){
            
            $string = '<div class="form-group req">';
        }

        if (!$options['hide_label']) {
            //$string .= Form::label($errorField, $labelTitle);
            $string .= html()->label($labelTitle, $name);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk ' . $errorClass . '"> *</span>';
            }
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $rows = isset($options['rows']) && $options['rows'] ? $options['rows'] : 3;
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? trans($options['placeholder']) : "";
        

        //$string .= Form::textarea($name, old($errorField, $value), $options);
        $string .=  html()->textarea($name, old($errorField, $value))->class($class)->id($id)->rows($rows)->placeholder($placeholder);
        $string .= $errors->first($errorField, '<label id="' . $errorField . '-error" class="error" for="' . $errorField . '">:message</label>');
        if($options['form_div']){
            $string .= '</div>';
        }
        return new HtmlString($string);
    }
}

if (!function_exists("i18nInputAttribute")) {
    function i18nInputAttribute($name, $variable, $title, ViewErrorBag $errors, $lang, $object = null, array $options = []) {

        $options = array_merge(['class' => 'form-control', 'placeholder' => $title, 'hide_label' => false,"form_div"=>true], $options);

        $errorClass = ' has-error';
        
        
        $string = '';
       
        if($options['form_div']){
            
            $string = '<div class="form-group req ' . ($errors->has($lang . '.' . $name) ? ' has-error' : '') . '">';
        }

        if (!$options['hide_label']) {
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="' . $errorClass . '"> *</span>';
            }
        }
        if (is_object($object)) {
            $currentData = $object->hasTranslation($lang) ? $object->translate($lang)->{$name} : '';
        } else {
            $currentData = '';
        }

        //$string .= Form::text("{$variable}[{$lang}][{$name}]", old("{$variable}[{$lang}][{$name}]", $currentData), $options);
        $string .= html()->text("{$variable}[{$lang}][{$name}]", old("{$variable}[{$lang}][{$name}]", $currentData))->class($options['class']);
        $string .= $errors->first("{$lang}.{$name}", '<label class="error">:message</label>');
        if($options['form_div']){
            $string .= '</div>';
        }

        return new HtmlString($string);
    }
}

if (!function_exists("normalLabel")) {
    function normalLabel($label , $for ,array $options = []){
        

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        

        //$string .= Form::textarea($name, old($errorField, $value), $options);
        $string = '';
        $string .=  html()->label($label , $for)->class($class)->id($id);
        
        return new HtmlString($string);
    }
}

if (!function_exists("livewireFormStart")) {
    function livewireFormStart($method = null, array $options = [])
    {
        $string = "";
        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $id = isset($options['id']) && $options['id'] ? $options['id'] : "";
        $string .= html()->form($method)->id($id)->class($class)->acceptsFiles()->attribute("wire:submit.prevent", "save")->open();
        
        return new HtmlString($string);
    }
}

if (!function_exists("livewireText")) {
    function livewireText($name, $title, $value = null, array $options = [])
    {
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'form-control', 'hide_label' => false, "form_div" => true], $options);
        $maxLength = config("core.varchar_maxlength");
        $options['maxlength'] = (isset($options['maxlength'])) ? $options['maxlength'] : $maxLength;

        $string = '';

        if (!$options['hide_label']) {
            $element = html()->label($labelTitle, $name);

            $string .= $element;
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk"> *</span>';
            }
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? trans($options['placeholder']) : "";
        $readonly = isset($options['readonly']) && $options['readonly'] ? $options['readonly'] : null;

        if (isset($readonly)) {
            $string .=  html()->text($name)->attribute("wire:model",$name)->class($class)->placeholder($placeholder)->isReadonly(true);
        } else {
            $string .=  html()->text($name)->attribute("wire:model", $name)->class($class)->placeholder($placeholder);
        }

        if (isset($options['append']) && $options['append']) {
            $string .= $options['append'];
        }

        return new HtmlString($string);
    }
}

if (!function_exists("livewireTextarea")) {
    function livewireTextarea($name, $title, $value = null, array $options = [])
    {
        $labelTitle = trans($title);
        $options = array_merge(['class' => 'form-control', 'hide_label' => false], $options);

        $string = '';
        if (!$options['hide_label']) {
            $string .= html()->label($labelTitle, $name);
            if (str_contains($options['class'], 'required') || isset($options['required'])) {
                $string .= '<span class="asterisk"> *</span>';
            }
        }

        $class = isset($options['class']) && $options['class'] ? $options['class'] : "";
        $rows = isset($options['rows']) && $options['rows'] ? $options['rows'] : 3;
        $placeholder = isset($options['placeholder']) && $options['placeholder'] ? trans($options['placeholder']) : "";

        $string .=  html()->textarea($name)->attribute("wire:model", $name)->class($class)->rows($rows)->placeholder($placeholder);
        
        return new HtmlString($string);
    }
}

if (!function_exists("livewireFormEnd")) {
    function livewireFormEnd()
    {
        $string = "";
        $string .= html()->form()->close();
        return new HtmlString($string);
    }
}

//End with custom macros
if (!function_exists("cleanFieldName")) {

    function cleanFieldName($name)
    {
        return str_replace(array('[', ']'), array(".", ""), $name);
    }
}
