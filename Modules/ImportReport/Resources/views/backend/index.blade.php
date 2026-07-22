@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("importreport::importreport.titles.list") }}
@endsection

@section('content-header')
<div class="card">
    <div class="card-body">
        <div class="page-title-header row d-none d-sm-flex">
            <div class="page-header col-sm-12 d-flex pb-4 pt-2">
                <div class="col-sm-6">
                    <h4 class="page-title">{{ trans("importreport::importreport.titles.importreport") }}</h4>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                    @can('admin.importreport.export')
                    <button type="button" class="btn btn-info btn-fw" id="export">{{ trans("importreport::importreport.buttons.export") }} </button>
                    @endcan
                    </div>
                </div>
            </div>
        </div>
        @stop

        @section('content')
        <div class="row">
            <div class="col-12">
                <div id="collection">
                
                    @include('importreport::backend.partials.grid')
                </div>
            </div>
            
        </div>
        
    </div>
</div>
@include('core::partials.delete-modal')
@php $maxFileSize = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize')) @endphp

@stop
@push('js-stack')
<script type="text/javascript">
    $(document).ready(function() {

        var errMsgFileType = `{!! trans("importreport::importreport.messages.file_type_error_message") !!}`;
        var errMsgFileSize = `{!! trans("importreport::importreport.messages.file_size_error_message") !!}`;
        var maxFileSize = "{{ $maxFileSize }}";
        var maxFileSize = (1024 * maxFileSize);

        $("#competitorMappingErr").html("");
        $("#competitorMappingErr").attr("display","none");
        $("#importcompetitorMapping").hide();

        var supportedFiles = ["csv","xlsx"]

        function extension(file){
            return file.substr((file.lastIndexOf('.') +1));
        } 

        //Validation For Competitor Mapping

        $(document).on('change','#competitorMapping',function(){
            $("#importprisyncverticalreport").hide();
            $("#prisyncverticalreport").val(null);
            $("#prisyncverticalreport_lbl").html('Choose File');
            var FileType = extension($(this)[0].files[0].name);
            var FileSize = Math.round(($(this)[0].files[0].size / 1024));
            var file = $(this)[0].files[0]['name'];
            
            if(supportedFiles.includes(FileType))
            {
                if(FileSize > maxFileSize){
                    $("#competitorMapping_lbl").html(file);
                    $("#competitorMappingErr").attr("display","block");
                    $("#competitorMappingErr").html(errMsgFileSize);
                    $("#importcompetitorMapping").hide();
                    $("#competitorMapping").val(null);
                }else{
                    $("#competitorMapping_lbl").html(file);
                    $("#competitorMappingErr").html("");
                    $("#competitorMappingErr").attr("display","none");
                    $("#importcompetitorMapping").show();
                }
            }
            else
            {   
                $("#competitorMapping_lbl").html(file);
                $("#competitorMappingErr").attr("display","block");
                $("#competitorMappingErr").html(errMsgFileType);
                $("#importcompetitorMapping").hide();
                $("#competitorMapping").val(null);
            }
        });

        //Validation For Prisync Vertical Report

        $(document).on('change','#prisyncverticalreport',function(){
            $("#importcompetitorMapping").hide();
            $("#competitorMapping").val(null);
            $("#competitorMapping_lbl").html('Choose File');
            var FileType = extension($(this)[0].files[0].name);
            var FileSize = Math.round(($(this)[0].files[0].size / 1024));
            var file = $(this)[0].files[0]['name'];

            if(supportedFiles.includes(FileType))
            {
                if(FileSize > maxFileSize){
                    $("#prisyncverticalreport_lbl").html(file);
                    $("#prisyncverticalreportErr").attr("display","block");
                    $("#prisyncverticalreportErr").html(errMsgFileSize);
                    $("#importprisyncverticalreport").hide();
                    $("#prisyncverticalreport").val(null);
                }else{
                    $("#prisyncverticalreport_lbl").html(file);
                    $("#prisyncverticalreportErr").html("");
                    $("#prisyncverticalreportErr").attr("display","none");
                    $("#importprisyncverticalreport").show();
                }
            }
            else
            {   
                $("#prisyncverticalreport_lbl").html(file);
                $("#prisyncverticalreportErr").attr("display","block");
                $("#prisyncverticalreportErr").html(errMsgFileType);
                $("#importprisyncverticalreport").hide();
                $("#prisyncverticalreport").val(null);
            }
        });


        
        $("#importcompetitorMappingBtn").on("click",function(){
            customObj.setFormId("competitor_mapping_form").setUrl('{{ route("admin.importreport.import_competitor_mapping",updateUrlParams()) }}').setMethod("post").saveFormWithFile();
            $("#importcompetitorMapping").hide();
            $("#competitorMapping").val(null);
            $("#competitorMapping_lbl").html('Choose File');
        });


        $("#importprisyncverticalreportBtn").on("click",function(){
            customObj.setFormId("prisync_vertical_report_form").setUrl('{{ route("admin.importreport.prisync_vertical_report",updateUrlParams()) }}').setMethod("post").saveFormWithFile();
            loaderShow();
            $("#importprisyncverticalreport").hide();
            $("#prisyncverticalreport").val(null);
            $("#prisyncverticalreport_lbl").html('Choose File');
        });

        $("#export").on("click",function(){
            $("#search_frm").attr("action", "{{ route('admin.importreport.export',updateUrlParams()) }}");
            $("#search_frm").submit();
        });

       

    });
        
</script>
@endpush
