@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("directory::directory.titles.currency_setup") }}
@endsection

@section('content-header')
    <!-- Content Header (Page header) -->
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            </div>
        </div>
    </div>
    <!-- /.content-header -->
@stop

@section('content')
@foreach ($errors->all() as $error)
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        {{ $error }}
    </div>
@endforeach
    <!-- Main content -->
    <!-- /.row -->
    <div class="card">
        <div class="card-body">
        <div class="row">
            <div class="col-12">
                @include('directory::backend.currency_setup.partials.grid')
            </div>
        </div>
        </div>
    </div>
    
    <!-- /.container-fluid -->
@stop
@push('js-stack')
    <script type="text/javascript">
      $("#allowed_currencies").change(function(){
        var selectedCurrency = $(this).children("option:selected").val();
        	var option = '';
        	$(this).children("option:selected").each(function(){ 
        		option = option + "<option value='"+$(this).val()+"'>"+$(this).val()+"</option>";
        	});
         $("#base_currency").html(option);
         $("#display_currency").html(option);
      });
      var options = $("#allowed_currencies").val();
      var option = '';
      var baseCurrencyOption = '<?php echo $baseCurrencyLabel ?>';
      var displayCurrencyLabel = '<?php echo $displayCurrencyLabel ?>';
      for (var i = 0; i < options.length; i++) {
          option += "<option value='"+options[i]+"'>"+options[i]+"</option>";
          $("#base_currency").html(option);
          $('#base_currency option[value="'+baseCurrencyOption+'"]').attr("selected","selected");
          $("#display_currency").html(option);
          $('#display_currency option[value="'+displayCurrencyLabel+'"]').attr("selected","selected");
      }
      jQuery("#currency_setup_form").validate({
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
      });
      jQuery("#currency_symbol_form").validate({
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
      });
      jQuery("#currency_rate_form").validate({
        submitHandler: function(form) {
            if (!beenSubmitted) {
                beenSubmitted = true;
                loaderShow();
                form.submit();
            }
        }
      });
      $('.select2').select2();
    </script>
@endpush