@extends('theme::layouts.backend.master')

@section('title')
{{ trans("banner::banner.titles.create_banner") }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
  <div class="page-header col-sm-12 d-flex pb-4 pt-2">
    <div class="col-sm-6">
      <h4 class="page-title">{{ trans("banner::banner.titles.create_banner") }}</h4>
    </div>
    <div class="col-sm-6 btn-right">
      <div class="float-right">
        <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.banner.index', updateUrlParams()) }}')">{{ trans("core::core.buttons.cancel") }}</button>
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

{{ formStart(null,"POST" ,'admin.banner.store' ,updateUrlParams(), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}


{{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
<div class="row">
  <div class="col-12 col-sm-6 col-lg-12">
    
    <div class="p-0 border-bottom-0">
      <ul class="nav nav-tabs" id="custom-tabs-three-tab">
        
        @if(isset($languageOptions) && (count($languageOptions)) > 1)
        <li class="nav-item">
          <a href="#lang-trans-form" class="nav-link active" data-toggle="tab">{{ trans("banner::banner.labels.language_translation") }}</a>
        </li>
        @endif
        <li class="nav-item {{(array_key_exists('role',$errors->getMessages()))? 'error': ''}}">
          <a href="#general-form" class="nav-link {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" data-toggle="tab">{{ trans("banner::banner.labels.banner_info") }}</a>
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
                  @include('banner::backend.banner.partials.create-translatable-fields', ['lang' => $locale])
                </div>
                @endforeach
              </div>
            </div>
          </div>
          @endif
          <div class="tab-pane ctab-pane {{isset($languageOptions) && (count($languageOptions)) > 1 ? '' : 'active'}}" id="general-form">
            @if(isset($languageOptions) && (count($languageOptions)) == 1)
            @foreach ($languageOptions as $locale => $language)
            @include('banner::backend.banner.partials.create-translatable-fields', ['lang' => $locale])
            @endforeach
            @endif
            @include('banner::backend.banner.partials.create-fields')
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{ formEnd()}}

@stop
@push('js-stack')
<script type="text/javascript">
  jQuery(document).ready(function() {
    jQuery(".countrylist").hide();
    $.validator.setDefaults({
      ignore: []
    });
    $('sort_order').keyup(function() {
      alert(this.value);

    });
    jQuery("#main_form").validate({
      rules: {
        @foreach($languageOptions as $locale => $language)
        "{{$locale}}[title]": {
          required: true,
          maxlength: 255,
          lettersOnly: true
        },
        @endforeach
        content: {
          required: true,
          lettersOnly: true
        },
        code: {
          required: true,
          maxlength: 255,
        },
        url: {
          url: true,
          maxlength: 255,
        },
      },
      errorPlacement: function(error, element) {
        error.insertAfter(element);
        if (element.attr("id") == "bannerInputFile") {
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
      }
    });

        jQuery.validator.addMethod("lettersOnly", function(value, element) {
            const hasLetter = /[A-Za-z]/.test(value);

            return this.optional(element) || (hasLetter);
        }, "Please enter a valid name with at least one letter");
  });

  $.validator.addMethod("url", function(value, element) {
    var decimal = /(http(s)?:\/\/.)[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g;
    if (value) {
      if (value.match(decimal)) {
        return true;
      } else {
        return false;
      }
    } else {
      return true;
    }
  }, "{{ trans('banner::banner.messages.invalid_url') }}");

  jQuery('input[type="file"]').change(function(e) {
    var fileName = e.target.files[0].name;
    jQuery(".custom-file-label").html(fileName);
    $('input[type="file"').addClass('valid_image');
  });
  var countryCodes = <?php echo json_encode($countryCodes); ?>;
  jQuery("#banner_group").change(function() {
    var selectedGroup = $(this).children("option:selected").text();
    if ($.inArray(selectedGroup, countryCodes) >= 0) {
      jQuery("#country").addClass('required');
      jQuery(".countrylist").show();
    } else {
      jQuery("#country").val('');
      jQuery(".countrylist").hide();
      jQuery("#country").removeClass('required');
    }

  });
  var msg;
  var dynamicmsg = function() {
    return msg;
  };
  $.validator.addMethod("valid_image", function(value, element) {
    if (typeof($(element)[0].files[0]) != 'undefined') {
      var file_size = ($(element)[0].files[0].size / 1024);
      var maxImageSize = "{{settings('banner', 'max_upload_size')}}";
      if (maxImageSize != '' && typeof(maxImageSize) != 'undefined') {
        var maxFileSize = (1024 * maxImageSize);
        if (file_size > maxFileSize) {
          msg = "{{trans('core::core.validation-message.image.max-size',['size'=>settings('banner', 'max_upload_size')])}}";
          return false;
        }
      }
    }
    return true;
  }, dynamicmsg);
</script>
@endpush
