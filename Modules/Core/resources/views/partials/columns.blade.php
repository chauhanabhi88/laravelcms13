@if ($columns)
    <div class="accordion" id="accordionColumn" role="tablist" aria-multiselectable="true">
        <input type="hidden" name="active_menu_id" value = "{{ $activeMenuId ?? '' }}" id = "active_menu_id">
        <div class="gp-panel" id="gp-panel-columns" style="display:none;">
            <button type="button" class="gp-panel-trigger collapsed" role="tab" id="columnsHeader"
                data-toggle="collapse" data-target="#columnCollapse" aria-expanded="false"
                aria-controls="columnCollapse">
                <span class="gp-panel-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>
                </span>
                <span class="gp-panel-heading">
                    <span class="gp-panel-title">{{ trans('core::core.labels.columns') }}</span>
                    <span class="gp-panel-sub">{{ trans('core::core.labels.columns_hint') }}</span>
                </span>
                <span class="gp-panel-spacer"></span>
                <span class="gp-count" id="columns-count"></span>
                <svg class="gp-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div id="columnCollapse" class="collapse" role="tabpanel" aria-labelledby="columnsHeader"
                data-parent="#accordionColumn">
                <div class="gp-panel-body">
                    <div class="gp-col-grid">
                        @foreach ($columns as $column)
                            <label class="gp-col-toggle">
                                <input class="columns-checkbox"
                                    type="checkbox"
                                    id="checkbox_{{ $column['code'] }}"
                                    value="{{ $column['code'] }}"
                                    name="columns[]" {{ $column['checkbox_checked'] ? 'checked' : ''}}
                                    column-id={{ $column['id'] }}
                                    onclick="selectCheckbox(this)"
                                >
                                <span>{{ trans($langPath . '.' . $column['code']) }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="gp-panel-foot">
                        <button type="button" class="gp-linkbtn"
                            title="{{trans('core::core.labels.select_all')}}"
                            onclick="selectAllColumns(); return false;">{{trans('core::core.labels.select_all')}}</button>
                        <span class="gp-dot">&middot;</span>
                        <button type="button" class="gp-linkbtn"
                            title="{{trans('core::core.labels.deselect_all')}}"
                            onclick="unSelectAllColumns(); return false;">{{trans('core::core.labels.deselect_all')}}</button>
                        <span class="gp-foot-spacer"></span>
                        <span class="gp-saved-note" id="columns-saved-note">{{trans('core::core.labels.saved')}}</span>
                        <button type="button" class="btn btn-primary"
                            title="{{trans('core::core.buttons.save')}}"
                            onclick="saveDefaultColumns(); return false;">{{trans('core::core.buttons.save')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
    function saveDefaultColumns() {
        let columns = {};
         jQuery(".columns-checkbox").each(function () {
            let columnId = jQuery(this).attr('column-id');
            columns[columnId] = jQuery(this).is(':checked')
        })
        activeMenuId = jQuery('#active_menu_id').val();
        customObj.setMethod('post')
            .setUrl('{{ route('admin.column.save') }}')
            .setParams({ 'columns': columns ,"_token": csrfToken,'active_menu_id': activeMenuId })
            .save();

        let note = jQuery("#columns-saved-note");
        note.addClass('is-visible');
        setTimeout(function () { note.removeClass('is-visible'); }, 1600);
    }

    function selectAllColumns(){
        jQuery(".columns-checkbox").not(':checked').trigger('click');
    }

    function unSelectAllColumns(){
        jQuery(".columns-checkbox:checked").trigger('click');
    }

    function selectCheckbox(ele){

        if(jQuery(ele).is(':checked')){
            jQuery(ele).attr('checked',true)
        }else{
            jQuery(ele).attr('checked',false)
        }
        updateColumnsCount();
    }

    function updateColumnsCount(){
        let total = jQuery(".columns-checkbox").length;
        let selected = jQuery(".columns-checkbox:checked").length;
        jQuery("#columns-count").text(selected + ' / ' + total);
    }

    jQuery(function () {
        updateColumnsCount();
    });
</script>
@endif
