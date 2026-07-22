@extends('theme::layouts.backend.master')

@section('title')
{{ trans("block::block.titles.edit_block")}} {{wordWrapper($block->title, true)}}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
            <h4 class="page-title">{{ trans("block::block.titles.edit_block")}} {{wordWrapper($block->title, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.block.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.block.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.block.delete', updateUrlParams([$block->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
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
{{ formStart(null,"PUT" ,'admin.block.update' ,updateUrlParams([$block->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab">
                @if(isset($languageOptions) && (count($languageOptions)) > 1)
                <li class="nav-item">
                    <a href="#lang-trans-form" class="nav-link active" data-toggle="tab">{{ trans("block::block.labels.language_translation") }}</a>
                </li>
                @endif
                <li class="nav-item {{(array_key_exists('role',$errors->getMessages()))? 'error': ''}}">
                    <a href="#general-form" class="nav-link {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" data-toggle="tab">{{ trans("block::block.labels.block_info") }}</a>
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
                                    @include('block::backend.partials.edit-translatable-fields', ['lang' => $locale])
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="tab-pane ctab-pane {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" id="general-form">
                        @if(isset($languageOptions) && (count($languageOptions)) == 1)
                        @foreach ($languageOptions as $locale => $language)
                        @include('block::backend.partials.edit-translatable-fields', ['lang' => $locale])
                        @endforeach
                        @endif
                        @include('block::backend.partials.edit-fields')
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
        $.validator.setDefaults({
            ignore: []
        });
        jQuery("#main_form").validate({
            rules: {
                @foreach($languageOptions as $locale => $language)
                "{{$locale}}[title]": {
                    required: true,
                    maxlength: 255,
                },
                @endforeach
                slug: {
                    required: true,
                    maxlength: 255,
                }
            },
            errorPlacement: function(error, element) {
                error.insertAfter(element);
                if (element.attr("name") == "slug") {
                    error.insertAfter('.mb-0');
                } else if (element.attr("id") == "content") {
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
        // Ignore Summernote Elements
        $('form').each(function() {
            if ($(this).data('validator'))
                $(this).data('validator').settings.ignore = ".note-editor *";
        });
        // End Ignore Summernote
        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
    });
</script>
<script>
    $('.formated-textarea').summernote({
        onCreateLink: function(originalLink) {
            return originalLink; // return original link
        },
        'height': 400
    })
</script>
@endpush
