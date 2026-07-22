@extends('theme::layouts.backend.master')

@section('title')
{{ trans("customer::customer.titles.customer_login_log") }}
@endsection

@section('content-header')
  <div class="card">
    <div class="card-body">
      <div class="page-title-header row d-none d-sm-flex">
        <div class="page-header col-sm-12 d-flex pb-4 pt-2">
          <div class="col-sm-6">
            <h4 class="page-title">{{ trans("customer::customer.titles.customer_login_log") }}</h4>
          </div>
          <div class="col-sm-6 btn-right">
            <div class="float-right">
              @can('admin.customerloginlog.export')
              <button type="button" class="btn btn-primary btn-sm au-btn-icon" id="export"><i class="fas fa-file-export"></i>{{ trans("core::core.buttons.export") }}</button>
              @endcan
            </div>
          </div>
        </div>
      </div>
      <!-- /.content-header -->
      @stop
      @section('content')

      <div class="row">
        <div class="col-12">
          <div id="collection">
            @include('customer::backend.customerloginlog.partials.grid')
          </div>
        </div>
      </div>
    </div>
  </div>
@stop


@push('js-stack')
<script type="text/javascript">
  jQuery("#export").click(function() {
    $("#search_frm").attr("action", "{{ route('admin.customerloginlog.export', updateUrlParams()) }}");
    $("#search_frm").submit();
    $("#search_frm").attr("action", "{{ route('admin.customerloginlog.filters', updateUrlParams()) }}");
  });
</script>

@endpush