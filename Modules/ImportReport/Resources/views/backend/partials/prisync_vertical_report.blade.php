
{{ formStart(null,"" ,'admin.importreport.prisync_vertical_report' ,updateUrlParams(), ['id' => 'prisync_vertical_report_form','enctype'=>'multipart/form-data'])}}
    <div class="card text-left">
        <div class="card-body">
            <h3>{!! trans("importreport::importreport.titles.prisync_vertical_report") !!}</h3>
            
            <p class="card-text">
                <div class="col-md-12">
                <div class="form-group">
                    <div class="input-group mb-3">
                        <div class="custom-file">
                            <input class="custom-file-label form-control" id="prisyncverticalreport" accept=".xlsx,.csv" name="prisyncverticalreport" type="file" required>
                            <label class="custom-file-label prisyncverticalreport" for="prisyncverticalreport" id="prisyncverticalreport_lbl">Choose file</label>
                        </div>
                        
                        <div class="input-group-append">
                        </div>
                    </div>
                    <p id="prisyncverticalreportErr" class="form-text text-danger"></p>
                    </div>
                </div>

            </p>
            
            <div id="importprisyncverticalreport" style="display:none">
                <button type="button" id="importprisyncverticalreportBtn" class="btn btn-primary btn-fw">{!! trans("importreport::importreport.labels.prisync_vertical_report") !!}</button>
            </div>
        </div>
    </div>
{{ formEnd() }}
