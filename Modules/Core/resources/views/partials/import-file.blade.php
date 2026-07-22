
{{ formStart(null,"POST" ,'' ,updateUrlParams(), ['enctype'=>'multipart/form-data', 'id' => 'importForm'])}}
<div class="modal fade" id="importFileModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{trans('core::core.import_csv_modal.choose_file')}}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
            @php
              $headers = (isset($headers) && !empty($headers) ? $headers : []);
              $otherNotes = (isset($otherNotes) && !empty($otherNotes) ? $otherNotes : []);
            @endphp
            {{getHeaderNote($headers, $otherNotes)}}
        <a href="#" class="mb-2" id="csvFormatLink">click here to download xlsx format</a>
        <input type="file" name="importFileInput" class="form-control required" accept=".xlsx">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('core::core.import_csv_modal.close')}}</button>
        <button  class="btn btn-primary" type="submit">{{trans('core::core.import_csv_modal.import')}}</button>
      </div>
    </div>
  </div>
</div>
<style>
  .import-note li {
    display: inline-block;
  }
</style>
{!! formEnd() !!}
@push('js-stack')
<script type="text/javascript">

  jQuery("#import").on('click', function() {

      var importModal = $("#importFileModal");
      var importAction = $(this).data('action');
      var getCsvUrl = $(this).data('getcsv');
      $("#importForm").attr('action', importAction);
      $("#csvFormatLink").attr('href', getCsvUrl);
      importModal.modal('show');

  });

  jQuery("#importForm").validate({
    submitHandler: function(form) {
      // Prevent double submission
      if (!beenSubmitted) {
          beenSubmitted = true;
          loaderShow();
          form.submit();
      }
    }
  });

</script>
@endpush
