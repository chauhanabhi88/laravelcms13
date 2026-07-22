@extends('theme::layouts.backend.master')

@section('title')
{{ trans("blog::blog_category.titles.edit_blog_category") }} {{wordWrapper($blog_category->title, true)}}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("blog::blog_category.titles.edit_blog_category") }} {{wordWrapper($blog_category->title, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.blog_category.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.blog_category.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_category.delete', updateUrlParams([$blog_category->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
                @endcan
                <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
            </div>
        </div>
    </div>
</div>
@include('core::partials.delete-modal')
@stop

@section('content')
{{ formStart(null,"PUT" ,'admin.blog_category.update' ,updateUrlParams([$blog_category->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
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
                                    @include('blog::backend.blog_category.partials.edit-translatable-fields', ['lang' => $locale])
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="tab-pane ctab-pane {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" id="general-form">
                        @if(isset($languageOptions) && (count($languageOptions)) == 1)
                        @foreach ($languageOptions as $locale => $language)
                        @include('blog::backend.blog_category.partials.edit-translatable-fields', ['lang' => $locale])
                        @endforeach
                        @endif

                        {{ normalText("slug","blog::blog_category.labels.slug", $errors,$blog_category->slug,["class" => "form-control required", "data-slug" => "target" ])}}
                        {{ normalInputOfType("number","sort_order", 'blog::blog_category.labels.sort_order',$errors,$blog_category->sort_order,["class" => "form-control required", "min"=>"0"])}}
                        <div>
                            <label for="blog_category.status">{{trans("blog::blog_category.labels.status")}}</label>
                        </div>
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$blog_category->status] !!}">
                            <label class="switch">
                                <input type="checkbox" value="{{config('core.enabled')}}" name="status" class="status" {{ ($blog_category->status == config("core.enabled")) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{ formEnd() }}

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
                } else if (element.attr("id") == "edit-blog-category-discription") {
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
