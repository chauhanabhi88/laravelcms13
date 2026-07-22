@if (Session::has('success'))
    <div class="callout callout-success">
        <button type="button" class="close close-msg-div" data-dismiss="alert" aria-hidden="true">&times; </i></button>
        {{ Session::get('success') }}
    </div>
@endif

@if (Session::has('error'))
    <div class="callout callout-danger">
        <button type="button" class="close close-msg-div" data-dismiss="alert" aria-hidden="true">&times; </i></button>
        {{ Session::get('error') }}
    </div>
@endif

@if (Session::has('warning'))
    <div class="alert alert-warning alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        {{ Session::get('warning') }}
    </div>
@endif

@if (Session::has('info'))
    <div class="alert alert-info alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        {{ Session::get('info') }}
    </div>
@endif
@push('js-stack')
<script type="text/javascript">
    jQuery(document).ready(function() {
        $('.close-msg-div').click(function(){
            $('.callout').hide();
        });
    });
</script>
@endpush