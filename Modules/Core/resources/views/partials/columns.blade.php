@if ($columns)
    <div class="accordion md-accordion" id="accordionColumn" role="tablist" aria-multiselectable="true">
        <input type="hidden" name="active_menu_id" value = "{{ $activeMenuId ?? '' }}" id = "active_menu_id">
        <div class="card">
            <div class="card-header change-pointer collapsed d-flex justify-content-between align-items-center" role="tab"
                id="columnsHeader" data-toggle="collapse" data-target="#columnCollapse" aria-expanded="false">

                <a class="btn-tool mb-0">
                    <h5 class="mb-0">{{ trans('core::core.labels.columns') }}</h5>
                </a>

                <div class="card-tools">
                    <button type="button" class="btn btn-tool filterAccordian collapsed filterCard" aria-expanded="false"
                        aria-controls="columnCollapse" data-toggle="collapse" data-target="#columnCollapse">
                    </button>
                </div>
            </div>

            <div id="columnCollapse" class="collapse collapseFilter" role="tabpanel" aria-labelledby="columnsHeader"
                data-parent="#accordionColumn">
                <div class="card-body">
                    <div class="row">
                        @foreach ($columns as $column)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input columns-checkbox" 
                                        type="checkbox"
                                        id="checkbox_{{ $column['code'] }}" 
                                        value="{{ $column['code'] }}"
                                        name="columns[]" {{ $column['checkbox_checked'] ? 'checked' : ''}}
                                        column-id={{ $column['id'] }}
                                        onclick="selectCheckbox(this)" 
                                    >
                                    <label class="{{$column['checkbox_checked'] ? 'font-weight-bold' : ''}}" for="checkbox_{{ $column['code'] }}" style="vertical-align: -webkit-baseline-middle;">
                                        <!-- {{ ($langPath . '.' . $column['code'] == trans($langPath . '.' . $column['code'])) ? $column['name'] : trans($langPath . '.' . $column['code']) }} -->
                                          {{ trans($langPath . '.' . $column['code']) }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="row">
                        <div class="form-group col-md-5">
                            <button type="button" class="btn btn-primary btn-fw"
                                title="{{trans('core::core.buttons.save')}}" onclick="saveDefaultColumns(); return false;"
                                fdprocessedid="b7xbe">{{trans('core::core.buttons.save')}}</button>
                            <a href="#" class="ml-2"
                                title="{{trans('core::core.labels.select_all')}}" onclick="selectAllColumns();"
                                fdprocessedid="b7xbe">{{trans('core::core.labels.select_all')}}</a>
                            <span class="ml-2">|</span>
                            <a href="#"class="ml-2"
                                title="{{trans('core::core.labels.deselect_all')}}" onclick="unSelectAllColumns();"
                                fdprocessedid="b7xbe">{{trans('core::core.labels.deselect_all')}}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
    function saveDefaultColumns() {
        let columns = {};
         jQuery(".columns-checkbox").each(function () {
            console.log(jQuery(this))
            let columnId = jQuery(this).attr('column-id');
            columns[columnId] = jQuery(this).is(':checked')
        })
        activeMenuId = jQuery('#active_menu_id').val();
        customObj.setMethod('post')
            .setUrl('{{ route('admin.column.save') }}')
            .setParams({ 'columns': columns ,"_token": csrfToken,'active_menu_id': activeMenuId })
            .save();
    }

    function selectAllColumns(){
        jQuery(".columns-checkbox").not(':checked').trigger('click');
    }

    function unSelectAllColumns(){
        jQuery(".columns-checkbox:checked").trigger('click');
    }

    function selectCheckbox(ele){

        if(jQuery(ele).is(':checked')){
            jQuery(ele).siblings('label').addClass('font-weight-bold')
            jQuery(ele).attr('checked',true)
        }else{
            jQuery(ele).siblings('label').removeClass('font-weight-bold')
            jQuery(ele).attr('checked',false)
        }
    }
</script>
@endif

