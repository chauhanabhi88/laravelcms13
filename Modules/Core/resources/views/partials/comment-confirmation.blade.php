<div class="modal fade" id="modal-comment-confirmation" tabindex="-1" role="dialog" aria-labelledby="confirm-comment-title" style="display: none;" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-primary">
            <div class="modal-header">
                <h4 class="modal-title" id="confirm-comment-title">Confirmation</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="default-message">
                    Are you sure you want to change comment status pending to approved ?
                </div>
                <div class="custom-message"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-light reject-comment" data-action-target = "{{ route('admin.blogpostcomment.comment-reject', updateUrlParams([$blogPostComment->id])) }}" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-light approved">Approved</button>
            </div>
        </div>
    </div>
</div>
@push('js-stack')
<script>
    jQuery( document ).ready(function() {
        jQuery('#modal-comment-confirmation').on('show.bs.modal', function (event) {
            $('.approved').on('click', function(){
                window.location.href = $('.comment-reply-button').data('action-target');
            });
            $('.reject-comment').on('click', function(){
                alert('test')
                window.location.href = $(this).data('action-target');
            });
        });
    });
</script>
@endpush
