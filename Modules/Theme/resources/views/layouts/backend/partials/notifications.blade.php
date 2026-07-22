@if (Session::has('success'))
<div class="sufee-alert alert with-close alert-success alert-dismissible fade show">
    <strong>{{ Session::get('success') }}</strong>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif

@if (Session::has('error'))
<div class="sufee-alert alert with-close alert-danger alert-dismissible fade show">
    <strong>{{ Session::get('error') }}</strong>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

@endif

@if (Session::has('warning'))
<div class="sufee-alert alert with-close alert-warning alert-dismissible fade show">
    <strong>{{ Session::get('warning') }}</strong>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

@endif

@if (Session::has('info'))
<div class="sufee-alert alert with-close alert-info alert-dismissible fade show">
    <strong>{{ Session::get('info') }}</strong>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif

@if (file_exists(storage_path('framework/down')))
<div class="sufee-alert alert with-close alert-warning alert-dismissible fade show">
    <strong>{{ trans("core::core.messages.notify_maintenance_mode") }}</strong>
</div>
@endif

