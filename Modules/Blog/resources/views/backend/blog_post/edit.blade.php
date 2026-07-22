@extends('theme::layouts.backend.master')

@section('title')
{{ trans("blog::blog_post.titles.edit_blog_post") }} {{wordWrapper($blog_post->title, true)}}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
	<div class="page-header col-sm-12 d-flex pb-4 pt-2">
		<div class="col-sm-6">
			<h4 class="page-title">{{ trans("blog::blog_post.titles.edit_blog_post") }} {{wordWrapper($blog_post->title, true)}}</h4>
		</div>
		<div class="col-sm-6 btn-right">
			<div class="float-right">
				<button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.blog_post.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                @can('admin.blog_post.delete')
                <button class="btn btn-danger btn-fw" data-form-id="main_form" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_post.delete', updateUrlParams([$blog_post->id])) }}">{{ trans('core::core.buttons.delete') }}</button>
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

{{ formStart(null,"PUT" ,'admin.blog_post.update' ,updateUrlParams([$blog_post->id]), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}
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
					<a href="#general-form" class="nav-link {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" data-toggle="tab">{{ trans("blog::blog_post.titles.blog_post_info") }}</a>
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
									@include('blog::backend.blog_post.partials.edit-translatable-fields', ['lang' => $locale])
								</div>
								@endforeach
							</div>
						</div>
					</div>
					@endif
					<div class="tab-pane ctab-pane {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" id="general-form">
						@if(isset($languageOptions) && (count($languageOptions)) == 1)
						@foreach ($languageOptions as $locale => $language)
						@include('blog::backend.blog_post.partials.edit-translatable-fields', ['lang' => $locale])
						@endforeach
						@endif

						{{ normalSelect("blog_categories[]",trans("blog::blog_post.labels.blog_categories"),$errors, $blogCategories,$selectedPostCategory,  ["class" => "form-control select2 required","multiple" => "multiple"]) }}
                        {{ normalText("slug","blog::blog_post.labels.slug", $errors,$blog_post->slug,["class" => "form-control required", "data-slug" => "target" ])}}

						{{ trans("blog::blog_post.labels.image") }}
						<div class="input-group mb-3">
							<div class="custom-file">
								{{ normalFile("image",'image',$errors,["class"=>"custom-file-label image form-control  is-invalid","id"=> "blog_post_image", "accept" => $imageTypes])}}

								<label class="custom-file-label image hideoverflow" for="blog_post_image"> Choose file </label>
							</div>
							<div class="input-group-append"></div>
						</div>
						{!! $errors->first("image", "<label class='error'>:message</label>") !!}
						@php
						$image_extension = (!empty(settings("blog", "image_type")))?settings("blog", "image_type"):"jpeg, jpg, png";
						$width = (!empty(settings("blog", "min_upload_width")))?settings("blog", "min_upload_width"):"100";
						$height = (!empty(settings("blog", "min_upload_height")))?settings("blog", "min_upload_height"):"100";
						$ratio = (!empty(settings("blog", "image_ratio")))?settings("blog", "image_ratio"):"1";
						$image_max_size = (!empty(settings("blog", "max_upload_size")))?settings("blog", "max_upload_size"):"5";

						$og_image_param = [
						"module" => Config::get("blog.name")."/".Config::get("blog.blog_post_name"),
						"image" => $blog_post->image,
						];

						$resize_image_param = [
						"image-type" => "resize",
						"image-size" => 100,
						"module" => Config::get("blog.name")."/".Config::get("blog.blog_post_name"),
						"image" => $blog_post->image,
						];
						@endphp

						@if(getImageUrl($og_image_param))
						<a href="{{getImageUrl($og_image_param)}}" target="_BLANK">
							<img src="{{getImageUrl($resize_image_param)}}" alt="introduction">
						</a>
						{{ normalCheckbox("remove_image","Remove Image", $errors,null,["class" => "form-control"])}}
						@endif
						<div class="image-note">
							<lh><b>{{trans("core::core.image-note.label")}}</b></lh>
							<li>{{trans("core::core.image-note.min-dimension",["width"=>$width,"height" => $height])}}</li>
							<li>{{trans("core::core.image-note.ratio",["ratio"=>$ratio])}}</li>
							<li>{{trans("core::core.image-note.max-size",["size"=>$image_max_size])}}</li>
							<li>{{trans("core::core.image-note.file-type",["file_type"=>$image_extension])}}</li>
						</div>

						{{ normalText("author","blog::blog_post.labels.author", $errors,$blog_post->author,["class" => "form-control required"])}}

                        {{ normalText("post_date","blog::blog_post.labels.post_date", $errors,date(config("core.encrypt.php_datepicker_format"), strtotime( $blog_post->post_date)),["id"=>"blog_post_post_date","class" => "form-control required" ])}}

						<div>
							<label for="blog_post.is_featured">{{trans("blog::blog_post.labels.is_featured")}}</label>
						</div>
						<span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$blog_post->is_featured] !!}">
							<label class="switch">
								<input type="checkbox" value="{{config('core.enabled')}}" class="status" name="is_featured" {{ ($blog_post->is_featured == config("core.enabled")) ? "checked" : ""}}>
								<span class="slider round"></span>
							</label>
						</span>
						<div>
							<label for="blog_post.status">{{trans("blog::blog_post.labels.status")}}</label>
						</div>
						<span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$blog_post->status] !!}">
							<label class="switch">
								<input type="checkbox" value="{{config('core.enabled')}}" class="status" name="status" {{ ($blog_post->status == config("core.enabled")) ? "checked" : ""}}>
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

@stop
@push('js-stack')
<script type="text/javascript">
	jQuery(document).ready(function() {

		$('.select2').select2();

		jQuery("#main_form").validate({
			rules: {
                "en[title]": {
                    required: true,
                    lettersOnly: true
                },
                "author": {
                    required: true,
                    noNumber: true
                },
            },
            messages: {
                "en[title]": {
                    lettersOnly: "Please enter a valid title with at least one letter"
                },
                "author": {
                    noNumber: "Please enter a valid Author Name with at least one letter and no numbers"
                },
            },
			errorPlacement: function(error, element) {
				error.insertAfter(element);
				var classes = element.attr('class');
				classes = classes.split(' ');
				if (classes.includes('custom-file-label')) {
					error.insertAfter('.input-group.mb-3');
				} else if (element.attr("id") == "blog-post-content") {
					error.insertAfter(jQuery("#blog-post-content").parent().find('.note-editor'));
				} else if (element.attr("name") == "blog_categories[]") {
					error.insertAfter(".select2-container");
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

        jQuery.validator.addMethod("noNumber", function(value, element) {
            const hasLetter = /[A-Za-z]/.test(value);
            const noNumbers = /^[^0-9]+$/.test(value);

            return this.optional(element) || (hasLetter && noNumbers);
        }, "Please enter a valid name with at least one letter and no numbers");


		jQuery("#blog_post_image").change(function(e) {
			var fileName = e.target.files[0].name;
			jQuery(".image").html(fileName);
			$("input[type='file']").addClass("valid_image");
			$("input[type='file']").addClass("validImage");
			$("input[type='file']").addClass("validDimension");
		});

		jQuery.validator.addMethod("validImage", function(value, element) {
			var ext = value.split(".").pop().toLowerCase();
			var Image_extention_db = '{{ (!empty(settings("blog", "image_type")))?settings("blog", "image_type"):"jpeg,jpg,png" }}'.toLowerCase().split(",");
			return ($.inArray(ext, Image_extention_db) == -1 && ext != "") ? false : true;
		}, '{{ trans("core::core.messages.invalid_image") }}');

		jQuery.validator.addMethod("validDimension", function(value, element) {
			var img = new Image();
			if ($(element)[0].files[0]) {
				img.src = window.URL.createObjectURL($(element)[0].files[0]);
				img.onload = function() {
					width = parseInt(img.naturalWidth);
					height = parseInt(img.naturalHeight);
					minWidth = parseInt('{{ (!empty(settings("blog", "min_upload_width")))?settings("blog", "min_upload_width"):"100" }}');
					minHeight = parseInt('{{ (!empty(settings("blog", "min_upload_height")))?settings("blog", "min_upload_height"):"100" }}');
					window.URL.revokeObjectURL(img.src);
					if ((width >= minWidth) && (height >= minHeight)) {
						if ((width / height).toFixed(2) != parseInt('{{ (!empty(settings("blog", "image_ratio")))?settings("blog", "image_ratio"):"1" }}')) {
							alert('{{ trans("core::core.messages.invalid_image_ratio") }}');
							return false;
						}
						return true;
					} else {
						alert('{{ trans("core::core.messages.invalid_dimension") }}');
						return false;
					}
				};
			}
			return true;
		}, '{{ trans("core::core.messages.invalid_dimension") }}');

		var msg;
		var dynamicmsg = function() {
			return msg;
		};

		jQuery.validator.addMethod("valid_image", function(value, element) {
			if (typeof($(element)[0].files[0]) != "undefined") {
				var file_size = ($(element)[0].files[0].size / 1024);
				var maxImageSize = '{{ (!empty(settings("blog", "max_upload_size")))?settings("blog", "max_upload_size"):"5" }}';
				if (maxImageSize != "" && typeof(maxImageSize) != "undefined") {
					var maxFileSize = (1024 * maxImageSize);
					if (file_size > maxFileSize) {
						msg = '{{ trans("core::core.validation-message.image.max-size",["size"=>( (!empty(settings("blog", "max_upload_size")))?settings("blog", "max_upload_size"):"5" )] ) }}';
						return false;
					}
				}
			}
			return true;
		}, dynamicmsg);
		jQuery("#blog_post_post_date").datepicker({
			dateFormat: "{{config('core.encrypt.datepicker_format')}}"
		});

		jQuery(".formated-textarea").summernote({
			height: 200
		});

		jQuery('[data-slug="source"]').each(function() {
			jQuery(this).slug();
		});
	});
</script>
@endpush
