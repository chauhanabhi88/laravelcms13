
{{ formStart(null,"" ,'admin.importreport.import_competitor_mapping' ,updateUrlParams(), ['id' => 'competitor_mapping_form','enctype'=>'multipart/form-data'])}}
    <div class="card text-left">
        <div class="card-body">
            <h3>{!! trans("importreport::importreport.titles.competitor_mapping") !!}</h3>
            
            <p class="card-text">
                <div class="col-md-12">
                <div class="form-group">
                    <div class="input-group mb-3">
                        <div class="custom-file">
                            <input class="custom-file-label form-control" id="competitorMapping" accept=".xlsx,.csv" name="competitorMapping" type="file" required>
                            <label class="custom-file-label competitorMapping" for="competitorMapping" id="competitorMapping_lbl">Choose file</label>
                        </div>
                        
                        <div class="input-group-append">
                        </div>
                    </div>
                    <p id="competitorMappingErr" class="form-text text-danger"></p>
                    </div>
                </div>

            </p>
            
            <div id="importcompetitorMapping" style="display:none">
                <button type="button" id="importcompetitorMappingBtn" class="btn btn-primary btn-fw">{!! trans("importreport::importreport.labels.import_competitor_mapping") !!}</button>
            </div>
        </div>
    </div>
{{ formEnd() }}
