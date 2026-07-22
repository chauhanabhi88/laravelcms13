@extends('theme::layouts.backend.master')

@section('title')
{{ trans("blog::blog_category.titles.create_blog_category") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("blog::blog_category.titles.create_blog_category") }}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.blog_category.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
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

{{ formStart(null,"POST" ,'admin.blog_category.store' ,updateUrlParams(), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab">
                @if(isset($languageOptions) && (count($languageOptions)) > 1)
                <li class="nav-item">
                    <a href="#lang-trans-form" class="nav-link active" data-toggle="tab">{{ trans("core::core.labels.language_translation") }}</a>
                </li>
                @endif
                <li class="nav-item {{(array_key_exists('role',$errors->getMessages()))? 'error': ''}}">
                    <a href="#general-form" class="nav-link {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" data-toggle="tab">{{ trans("blog::blog_category.titles.blog_category_info") }}</a>
                </li>
            </ul>
        </div>
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-body">
                <div class="tab-content">
                    @if(isset($languageOptions) && (count($languageOptions)) > 1)
                    <div class="tab-pane ctab-pane  active" id="lang-trans-form">
                        @include('core::partials.form-tab-headers')
                        <div class="card-body">
                            <div class="tab-content">
                                @php $i = 0; @endphp
                                @foreach ($languageOptions as $locale => $language)
                                @php $i++; @endphp
                                <div class="tab-pane {{ App::getLocale() == $locale ? 'active' : '' }}" id="tab_{{ $i }}">
                                    @include('blog::backend.blog_category.partials.create-translatable-fields', ['lang' => $locale])
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="tab-pane ctab-pane {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" id="general-form">

                        @if(isset($languageOptions) && (count($languageOptions)) == 1)
                        @foreach ($languageOptions as $locale => $language)
                        @include('blog::backend.blog_category.partials.create-translatable-fields', ['lang' => $locale])
                        @endforeach
                        @endif
                        {{ normalText("slug","blog::blog_category.labels.slug", $errors,null,["class" => "form-control required", "data-slug" => "target" ])}}
                        {{ normalInputOfType("number","sort_order", 'blog::blog_category.labels.sort_order',$errors,null,["class" => "form-control required", "min"=>"0"])}}

                        <div>
                            <label for="blog_category.status">{{trans("blog::blog_category.labels.status")}}</label>
                        </div>
                        <span data-placement="right" data-toggle="tooltip" title="{!! trans('core::core.options.status.disable') !!}">
                            <label class="switch">
                                <input type="checkbox" value="{{config('core.enabled')}}" name="status" class="status">
                                <span class="slider round"></span>
                            </label>
                        </span>
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
        jQuery("#main_form").validate({
             rules: {
                "en[title]": {
                    required: true,
                    lettersOnly: true
                },
            },
            messages: {
                "en[title]": {
                    lettersOnly: "Please enter a valid title with at least one letter"
                },
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                var classes = element.attr('class');
                classes = classes.split(' ');
                if (classes.includes('custom-file-label')) {
                    error.insertAfter('.input-group.mb-3');
                } else if (element.attr("id") == "blog-category-discription") {
                    error.insertAfter('.note-editor');
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

        jQuery.validator.addMethod("lettersOnly", function(value, element) {
            const hasLetter = /[A-Za-z]/.test(value);

            return this.optional(element) || (hasLetter);
        }, "Please enter a valid title with at least one letter");
        
        jQuery(".formated-textarea").summernote({
            height: 200
        });

        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
    });
</script>
@endpush