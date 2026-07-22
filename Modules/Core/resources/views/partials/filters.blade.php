@if ($filters)

    @php
        $row = 0;
        $flag = true;
    @endphp

    <div class="accordion" id="accordionEx" role="tablist" aria-multiselectable="true">
        <div class="gp-panel" id="gp-panel-filters" style="display:none;">
            <button type="button" class="gp-panel-trigger collapsed" role="tab" id="headingOne1"
                data-toggle="collapse" data-target="#collapseOne1" aria-expanded="false"
                aria-controls="collapseOne1">
                <span class="gp-panel-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
                </span>
                <span class="gp-panel-heading">
                    <span class="gp-panel-title">{{trans('core::core.labels.filter')}}</span>
                    <span class="gp-panel-sub">{{trans('core::core.labels.filter_hint')}}</span>
                </span>
                <span class="gp-panel-spacer"></span>
                <span class="gp-count" id="filters-count" style="display:none;"></span>
                <svg class="gp-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div class="gp-chips" id="filter-chips" style="display:none;"></div>

            <div id="collapseOne1" class="collapse collapseFilter" role="tabpanel" aria-labelledby="headingOne1"
                data-parent="#accordionEx">
                <div class="gp-panel-body table-responsive">
                    <div class="filter-main">
                        @foreach ($filters as $item)

                            @if($row != $item['row'] && $flag == true)
                                <div class="row">
                            @elseif($row != $item['row'])
                                    </div>
                                    <div class="row">
                                @endif

                                @php
                                    $row = $item['row'];
                                    $flag = false;
                                @endphp

                                @if ($item['type'] == 'number_range')
                                    @if (isset($item['name']) && $item['name'])
                                        <div class="form-group col-md-3 created-main">
                                            @foreach ($item['name'] as $fieldName)
                                                @if(array_key_exists('label', $item['options'][$fieldName]))
                                                    <label>{{$item['options'][$fieldName]['label']}}</label>
                                                    <div class="row">
                                                @endif
                                                    <div class="col">
                                                        {{ normalNumber($fieldName, "From", $errors, $item['value'][$fieldName], null, null, null, ['class' => $item['options'][$fieldName]['class'], 'placeholder' => $item['options'][$fieldName]['placeholder'], 'hide_label' => true])}}
                                                    </div>
                                            @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                @if ($item['type'] == 'checkbox')
                                    <div class="form-group col-md-3">
                                        <label>{{$item['options']['label']}}</label>

                                        {{ normalCheckbox($item["name"], $item["name"], $errors, $item['value'], $item['options'])}}

                                    </div>
                                @endif

                                @if ($item['type'] == 'switch')
                                    <div class="form-group col-md-3">
                                        <label>{{$item['options']['label']}}</label>
                                        <label class="switch">
                                            <input name="{{$item['name']}}" type="checkbox" value="{{$item['value']}}">
                                            <span class="{{$item['options']['class']}}"></span>
                                        </label>
                                    </div>
                                @endif

                                @if ($item['type'] == 'text')
                                    <div class="form-group col-md-3">
                                        <label>{{$item['options']['placeholder']}}</label>

                                        {{ normalText($item["name"], $item['options']['placeholder'], $errors, $item['value'], ['class' => $item['options']['class'], 'placeholder' => $item['options']['placeholder'], 'hide_label' => true])}}

                                    </div>
                                @endif

                                @if ($item['type'] == 'select')
                                    <div class="form-group col-md-3">
                                        <label>{{$item['options']['label']}}</label>
                                        {{ normalSelect($item["name"], null, $errors, $item['select_options'], $item['value'], ['class' => 'custom-select', 'hide_label' => true]) }}
                                    </div>
                                @endif

                                @if ($item['type'] == 'date_range')
                                    @if (isset($item['name']) && $item['name'])
                                        <div class="form-group col-md-3 created-main">
                                            @foreach ($item['name'] as $fieldName)
                                                @php
                                                    $item['options'][$fieldName]['class'] = isset($item['options'][$fieldName]['class']) ? $item['options'][$fieldName]['class'] . ' jquery-datepicker' : 'jquery-datepicker';
                                                    $item['options'][$fieldName]['id'] = $fieldName;
                                                @endphp
                                                @if(array_key_exists('label', $item['options'][$fieldName]))
                                                    <label>{{$item['options'][$fieldName]['label']}}</label>
                                                    <div class="row">
                                                @endif
                                                    <div class="col">
                                                        {{ normalText($fieldName, (array_key_exists('label', $item['options'][$fieldName])) ? $item['options'][$fieldName]['label'] : '', $errors, $item['value'][$fieldName], ['class' => $item['options'][$fieldName]['class'], 'placeholder' => $item['options'][$fieldName]['placeholder'], 'hide_label' => true,'id'=>$item['options'][$fieldName]['id'],'readonly'=>'readonly'])}}
                                                    </div>
                                            @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                @if ($item['type'] == 'time_range')
                                    @if (isset($item['name']) && $item['name'])
                                        @foreach ($item['name'] as $fieldName)
                                            <div class="form-group col-md-2">
                                                @if(array_key_exists('label', $item['options'][$fieldName]))
                                                    <label>{{$item['options'][$fieldName]['label']}}</label>
                                                @else
                                                    <label> &nbsp </label>
                                                @endif
                                                {{ normalText($fieldName, (array_key_exists('label', $item['options'][$fieldName])) ? $item['options'][$fieldName]['label'] : '', $errors, $item['value'][$fieldName], ['hide_label' => true])}}

                                            </div>
                                        @endforeach
                                    @endif
                                @endif
                                @if ($item['type'] == 'action')
                                    @if (isset($item['buttons']) && $item['buttons'])
                                        <div class="col-12 gp-panel-foot">
                                            <span class="gp-foot-hint">{{ trans('core::core.labels.filter_hint') }}</span>
                                            <span class="gp-foot-spacer"></span>
                                            @foreach ($item['buttons'] as $item)
                                                <button type="{{ $item['type'] }}" class="{{ $item['class'] }}" title="{{ $item['title'] }}"
                                                    onclick="{{ $item['onclick'] }}">{{ $item['title'] }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                        @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif


@push('js-stack')
    <script type="text/javascript">
        var date_format = "{{ config('core.encrypt.datepicker_format') }}";
        jQuery(document).ready(function () {
            jQuery(".jquery-datepicker").datepicker();
            initDatePicker(date_format);
            initMassDelete();
            timeSlot();
            buttonDisableAccToSlctRecords();
            openAccordian();
        });

        function openAccordian() {
            // Handle Filters accordion only
            let filterHasValue = false;
            $("#collapseOne1").find("input, select").each(function () {
                if ($(this).val() !== "" && $(this).val() !== null) {
                    filterHasValue = true;
                }
            });
            if (filterHasValue && typeof toggleGridPanel === 'function') {
                toggleGridPanel('filters', true);
            }

            renderFilterChips();

            // // Handle Columns accordion only
            // let columnHasValue = false;
            // $("#columnCollapse").find("input:checked").each(function () {
            //     columnHasValue = true;
            // });
            // if (columnHasValue) {
            //     $("#columnCollapse").addClass("show");
            //     $("#columnsHeader .filterCard").removeClass("collapsed");
            // }
        }

        function escapeChipHtml(value) {
            return jQuery('<div/>').text(value).html();
        }

        /**
         * Human readable name for a filter field: the form-group label, plus the
         * field placeholder for the "From / To" halves of a range filter.
         */
        function filterFieldLabel(field) {
            var groupLabel = jQuery.trim(field.closest('.form-group').find('label').first().text());
            var placeholder = jQuery.trim(field.attr('placeholder') || '');

            if (groupLabel && placeholder && groupLabel.toLowerCase() !== placeholder.toLowerCase()) {
                return groupLabel + ' ' + placeholder;
            }
            return groupLabel || placeholder || field.attr('name');
        }

        /**
         * Renders every applied filter as a removable chip on the collapsed panel header
         * and updates the "N active" badge.
         */
        function renderFilterChips() {
            var chipContainer = jQuery('#filter-chips');
            var badge = jQuery('#filters-count');

            if (!chipContainer.length) {
                return;
            }

            chipContainer.empty();
            var chipCount = 0;

            jQuery('#collapseOne1 .filter-main').find('input, select').each(function (index) {
                var field = jQuery(this);
                var type = (field.attr('type') || '').toLowerCase();
                var name = field.attr('name');

                if (!name || name === '_token' || type === 'hidden' || type === 'submit' || type === 'button') {
                    return;
                }

                var value = field.val();
                var display;

                if (jQuery.isArray(value)) {
                    value = value.join(',');
                }

                if (this.tagName === 'SELECT') {
                    display = jQuery.map(field.find('option:selected'), function (option) {
                        return jQuery.trim(jQuery(option).text());
                    }).join(', ');
                } else if (type === 'checkbox' || type === 'radio') {
                    if (!this.checked) {
                        return;
                    }
                    display = filterFieldLabel(field);
                } else {
                    display = value;
                }

                if (value === null || typeof value === 'undefined' || jQuery.trim(value) === '' || display === '') {
                    return;
                }

                field.attr('data-gp-chip', index);
                chipCount++;

                chipContainer.append(
                    '<span class="gp-chip">' + escapeChipHtml(filterFieldLabel(field)) +
                    ' <b>' + escapeChipHtml(display) + '</b>' +
                    '<button type="button" class="gp-chip-remove" data-gp-chip="' + index + '" ' +
                    'title="{{ trans('core::core.buttons.reset') }}" aria-label="{{ trans('core::core.buttons.reset') }}">&times;</button></span>'
                );
            });

            chipContainer.toggle(chipCount > 0);
            badge.text("{{ trans('core::core.labels.filters_active', ['count' => ':n']) }}".replace(':n', chipCount))
                .toggle(chipCount > 0);

            // mirror the count onto the header button so applied filters stay visible while hidden
            jQuery('.gp-toggle[data-gp-panel="filters"] .gp-toggle-badge')
                .text(chipCount)
                .toggle(chipCount > 0);
        }

        jQuery('body').on('click', '.gp-chip-remove', function () {
            var field = jQuery('#collapseOne1 .filter-main').find('[data-gp-chip="' + jQuery(this).data('gp-chip') + '"]').first();

            if (!field.length) {
                return;
            }

            var type = (field.attr('type') || '').toLowerCase();

            if (type === 'checkbox' || type === 'radio') {
                field.prop('checked', false);
            } else if (field.is('select')) {
                field.val(field.find('option').first().val());
            } else {
                field.val('');
            }

            searchFilter();
        });


        //datepicker changes
        $("#created_at_from").datepicker({
            maxDate: 0,
            dateFormat: date_format,
        });
        $('#created_at_to').datepicker({
            maxDate: 0,
            dateFormat: date_format,
            beforeShow: function () {
                $(this).datepicker('option', 'minDate', $('#created_at_from').val());
            }
        });

        function filterArray(arr) {
            return arr.filter(function (el) {
                if (el != null && el.length > 0) {
                    return true
                }
                return false;
            });
        }

        var unique = (value, index, self) => {
            return self.indexOf(value) === index
        }
        var temp = '';

        function dropdownChange() {
            var countCheckedItems = $(".select-item:checked").length;
            if (countCheckedItems == $(".select-item").length) {
                jQuery("#massSelectDropdown").html(checkedSelectionDropdownHtml);
            } else {
                jQuery('#massSelectDropdown').html(unCheckedSelectionDropdownHtml);
            }
        }

        function buttonDisableAccToSlctRecords() {

            if ($('.select-item:checked').length > 0) {
                $('#mass-delete, #update-status, #restore').attr('disabled', false);
            } else {
                $('#mass-delete, #update-status, #restore').attr('disabled', true);
            }

        }

        function initMassDelete() {

            var totalRecords = parseInt(jQuery("#total-records").text());
            // select manually
            jQuery('.select-item').click(function () {
                if (jQuery('#select-all').val() == 1) {
                    if (jQuery(this).is(':checked')) {
                        jQuery(this).parents("tr").addClass('selected-row');
                        var idNotToDelete = jQuery('#idsNotToDelete').val();
                        idNotToDeleteArray = idNotToDelete.split(',');
                        idNotToDeleteArray = filterArray(idNotToDeleteArray)
                        idNotToDeleteArray = idNotToDeleteArray.filter(unique)
                        var found = jQuery.inArray("" + jQuery(this).attr('value') + "", idNotToDeleteArray);
                        idNotToDeleteArray.splice(found, 1);
                        jQuery('#idsNotToDelete').val(idNotToDeleteArray.toString());
                    } else if (!jQuery(this).is(':checked')) {
                        jQuery(this).parents("tr").removeClass('selected-row');
                        var id = jQuery(this).attr('value');
                        var idNotToDelete = jQuery('#idsNotToDelete').val();
                        idNotToDelete = (idNotToDelete) ? idNotToDelete + "," + id : id;
                        jQuery('#idsNotToDelete').val(idNotToDelete);
                        jQuery('#massDeleteCheckbox').prop('checked', false);
                    }
                    var idNotToDelete = jQuery('#idsNotToDelete').val();
                    var idNotToDeleteArray = idNotToDelete.split(',');
                    idNotToDeleteArray = filterArray(idNotToDeleteArray);
                    jQuery('#selected-records').text(totalRecords - idNotToDeleteArray.length);
                    temp = totalRecords - idNotToDeleteArray.length;
                    if (temp == 0) {
                        jQuery('#select-all').val('');
                        jQuery('#idToDelete').val('');
                        jQuery('#idsNotToDelete').val('');
                        jQuery('#massSelectDropdown').html(unCheckedSelectionDropdownHtml);
                    }
                } else {
                    if (jQuery(this).is(':checked')) {
                        jQuery(this).parents("tr").addClass('selected-row');
                        var id = jQuery(this).attr('value');
                        var idToDelete = jQuery('#idToDelete').val();

                        idToDelete = (idToDelete) ? idToDelete + "," + id : id;

                        jQuery('#idToDelete').val(idToDelete);
                        dropdownChange();
                    } else if (!jQuery(this).is(':checked')) {
                        jQuery(this).parents("tr").removeClass('selected-row');
                        var idToDelete = jQuery('#idToDelete').val();
                        var idToDeleteArray = idToDelete.split(',');
                        idToDeleteArray = filterArray(idToDeleteArray)
                        idToDeleteArray = idToDeleteArray.filter(unique)
                        var found = jQuery.inArray("" + jQuery(this).attr('value') + "", idToDeleteArray);
                        idToDeleteArray.splice(found, 1);
                        jQuery('#idToDelete').val(idToDeleteArray.toString());
                        dropdownChange();
                    }
                    var idToDelete = jQuery('#idToDelete').val();
                    var idToDeleteArray = idToDelete.split(',');
                    idToDeleteArray = filterArray(idToDeleteArray);
                    jQuery('#selected-records').text(idToDeleteArray.length);
                    temp = idToDeleteArray.length;
                }
                if (temp == totalRecords) {
                    jQuery('#massDeleteCheckbox').prop("checked", true);
                    jQuery('#select-all').val(1);
                    jQuery('#idToDelete').val('');
                    jQuery('#idsNotToDelete').val('');
                    jQuery('#massSelectDropdown').html('<li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>');
                } else {
                    jQuery('#massDeleteCheckbox').prop("checked", false);
                }
                buttonDisableAccToSlctRecords();
            });
            // select manually over

            jQuery('#massDeleteCheckbox').click(function () {
                if (jQuery('#massDeleteCheckbox').is(":not(:checked)")) {
                    deselectAll(false);
                } else {
                    selectAll(false);
                }
            });
        }

        function selectAll(flag) {
            var totalRecords = parseInt(jQuery("#total-records").text());
            if ($('.checked-checkbox-ids').length > 0) {
                var totalRecords = parseInt($('.checked-checkbox-ids').val());
            }
            if (flag) {
                jQuery('#massDeleteCheckbox').prop("checked", true);
            }
            jQuery('.select-item').prop('checked', true);
            jQuery('#select-all').val(1);

            if ($('.update-status-all').length > 0) {
                $('.update-status-all').val(1);
            }
            jQuery('.select-item').parents("tr").addClass('selected-row');
            jQuery('#idsNotToDelete').val('');
            jQuery('#idToDelete').val('');

            // jQuery('#selected-records').text(totalRecords);
            jQuery('#selected-records').text("All");
            jQuery('#massSelectDropdown').html('<li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>');
            buttonDisableAccToSlctRecords();
            // dropdownChange();
        }

        function deselectAll(flag) {
            if (flag) {
                jQuery('#massDeleteCheckbox').prop("checked", false);
            }
            jQuery('.select-item').prop('checked', false);
            jQuery('#select-all').val('');
            if ($('.update-status-all').length > 0) {
                $('.update-status-all').val('');
            }
            jQuery('#idToDelete').val('');
            jQuery('#idToDelete').val('');
            jQuery('#idsNotToDelete').val('');
            jQuery('#selected-records').text(0);
            jQuery('.select-item').parents("tr").removeClass('selected-row');
            jQuery('#massSelectDropdown').html(unCheckedSelectionDropdownHtml);
            // dropdownChange();
            buttonDisableAccToSlctRecords();
            // dropdownChange();
        }

        function selectVisible() {
            jQuery('#idsNotToDelete').val('');
            var ids = '';
            jQuery('.select-item').prop('checked', true).change();
            jQuery('#select-all').val('');
            if ($('.update-status-all').length > 0) {
                $('.update-status-all').val('');
            }
            jQuery(".select-item").each(function () {
                ids += jQuery(this).data('id') + ',';
                jQuery(this).parents("tr").addClass('selected-row');
            })
            ids = (jQuery('#idToDelete').val()) ? jQuery('#idToDelete').val() + ',' + ids : ids;
            ids = ids.replace(/^,|,$/g, '');
            var idToDeleteArray = ids.split(',');
            idToDeleteArray = filterArray(idToDeleteArray);
            idToDeleteArray = idToDeleteArray.filter(unique);
            jQuery('#idToDelete').val(idToDeleteArray.toString());
            jQuery('#selected-records').text(idToDeleteArray.length);
            jQuery('#massSelectDropdown').html(checkedSelectionDropdownHtml);
            if (parseInt(jQuery("#total-records").text()) == parseInt(jQuery('#selected-records').text())) {
                jQuery('#massDeleteCheckbox').prop("checked", true);
                jQuery('#select-all').val(1);
                jQuery('#massSelectDropdown').html('<li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>');
                jQuery('#idToDelete').val('');
                jQuery('#idsNotToDelete').val('');
            }
            buttonDisableAccToSlctRecords();
            // dropdownChange();
        }

        function deselectVisible() {
            var ids = '';
            var deleteIds = '';
            jQuery('#massDeleteCheckbox').prop('checked', false);
            jQuery('#select-all').val(0);
            jQuery(".select-item").each(function (key, element) {
                if (jQuery(this).is(':checked')) {
                    var idToDelete = jQuery('#idToDelete').val();
                    var idToDeleteArray = idToDelete.split(',');
                    idToDeleteArray = filterArray(idToDeleteArray)
                    idToDeleteArray.filter(unique)
                    var found = jQuery.inArray("" + jQuery(this).data('id') + "", idToDeleteArray);
                    idToDeleteArray.splice(found, 1);
                    jQuery('#idToDelete').val(idToDeleteArray.toString());
                    ids += jQuery(this).data('id') + ',';
                    deleteIds += jQuery(this).data('id') + ',';
                    jQuery(this).parents("tr").removeClass('selected-row');
                }
            })
            jQuery('.select-item').prop('checked', false).change();
            ids = (jQuery('#idsNotToDelete').val()) ? jQuery('#idsNotToDelete').val() + ',' + ids : ids;
            deleteIds = (jQuery('#idToDelete').val()) ? jQuery('#idToDelete').val() + ',' + deleteIds : deleteIds;
            var idNotToDeleteArray = ids.split(',');
            idNotToDeleteArray = filterArray(idNotToDeleteArray);
            idNotToDeleteArray = idNotToDeleteArray.filter(unique);

            jQuery('#selected-records').text(parseInt(jQuery(".selected_records").text()) - idNotToDeleteArray.length);
            jQuery('#massSelectDropdown').html(unCheckedSelectionDropdownHtml);
            buttonDisableAccToSlctRecords();
            // dropdownChange();
        }
    </script>
@endpush


<script type="application/javascript">
    var checkedSelectionDropdownHtml = '<li><a href="javascript:void(0)" class="dropdown-item" onClick="deselectVisible()"> {{ trans("core::core.labels.deselect_visible") }} </a></li>\
            <li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>';
    var unCheckedSelectionDropdownHtml = '<li><a href="javascript:void(0)" onClick="selectVisible()" class="dropdown-item"> {{ trans("core::core.labels.select_visible") }} </a></li>\
            <li><a href="javascript:void(0)" class="dropdown-item" onClick="selectAll(true)"> {{ trans("core::core.labels.select_all") }} </a></li>';

    if (document.readyState !== 'loading') {
        var totalRecords = parseInt(jQuery("#total-records").text());
        initDatePicker(date_format);
        initMassDelete();
        timeSlot();
        openAccordian();


        if (jQuery('#select-all').val() == 1) {
            jQuery('.select-item').attr('checked', true);
            jQuery('#massDeleteCheckbox').prop('checked', true);
            jQuery('.select-item').parents("tr").addClass('selected-row');
            var idsNotToDelete = jQuery('#idsNotToDelete').val();
            if (idsNotToDelete) {
                jQuery('#massDeleteCheckbox').prop('checked', false);
            }
            var idsNotToDeleteArray = [];
            if (idsNotToDelete) {
                idsNotToDeleteArray = idsNotToDelete.split(',');
                idsNotToDeleteArray.forEach((id) => {
                    var item = jQuery(`input[value="${id}"]`);
                    item.prop('checked', false);
                    item.parents("tr").removeClass('selected-row');
                });
            }
            if (idsNotToDeleteArray.length == 0) {
                jQuery('#selected-records').text("All");
            } else {
                jQuery('#selected-records').text(totalRecords - idsNotToDeleteArray.length);
            }
            buttonDisableAccToSlctRecords();
        } else {
            //make selected checkbox if checked once

            // ids not delete value
            var idsNotToDelete = jQuery('#idsNotToDelete').val();
            var idsNotToDeleteArray = [];
            if (idsNotToDelete) {
                jQuery('.select-item').attr('checked', true);
                jQuery('.select-item').parents("tr").addClass('selected-row');
                idsNotToDeleteArray = idsNotToDelete.split(',');
                idsNotToDeleteArray.forEach((id) => {
                    var item = jQuery(`input[value="${id}"]`);
                    item.prop('checked', false);
                    item.parents("tr").removeClass('selected-row');
                });
            }
            idsNotToDeleteArray = filterArray(idsNotToDeleteArray);
            idsNotToDeleteArray = idsNotToDeleteArray.filter(unique);
            if (idsNotToDeleteArray.length) {
                jQuery('#selected-records').text(totalRecords - idsNotToDeleteArray.length);
            }
            if (idsNotToDeleteArray.length == totalRecords && idsNotToDeleteArray.length !== 0) {
                jQuery('#massDeleteCheckbox').prop('checked', true);
            }

            // ids delete value
            var idsToDelete = jQuery('#idToDelete').val();
            if (idsToDelete !== undefined) {
                var idsToDeleteArray = idsToDelete.split(',');
                jQuery('.select-item').prop('checked', false).change();
                jQuery('.select-item').parents("tr").removeClass('selected-row');
                idsToDeleteArray.forEach((id) => {
                    if (id != null) {
                        var item = jQuery(`input[value="${id}"]`);
                        item.prop('checked', true);
                        item.parents("tr").addClass('selected-row');
                    }
                });

                idsToDeleteArray = filterArray(idsToDeleteArray);
                idsToDeleteArray = idsToDeleteArray.filter(unique);
                if (idsToDeleteArray.length) {
                    jQuery('#selected-records').text(idsToDeleteArray.length);
                }
                if (idsToDeleteArray.length == totalRecords && idsToDeleteArray.length !== 0) {
                    jQuery('#massDeleteCheckbox').prop('checked', true);
                }
            }

            //equal idsNotToDeleteArray to  idsToDeleteArray
            if (idsNotToDeleteArray.length > 0 && idsToDeleteArray.length > 0 && idsToDeleteArray.length == idsNotToDeleteArray.length) {
                jQuery('.select-item').attr('checked', false);
                jQuery('.select-item').parents("tr").removeClass('selected-row');
                jQuery('#selected-records').text(idsToDeleteArray.length - idsNotToDeleteArray.length);

            }

            if (idsNotToDeleteArray.length > 0 && idsToDeleteArray.length > 0 && idsToDeleteArray.length > idsNotToDeleteArray.length) {
                var differenceArray = difference(idsToDeleteArray, idsNotToDeleteArray);
                jQuery('.select-item').attr('checked', false);
                jQuery('.select-item').parents("tr").removeClass('selected-row');
                differenceArray.forEach((id) => {
                    if (id != null) {
                        var item = jQuery(`input[value="${id}"]`);
                        item.prop('checked', true);
                        item.parents("tr").addClass('selected-row');
                    }
                });
                jQuery('#selected-records').text(differenceArray.length);
                buttonDisableAccToSlctRecords();
            }

        }

        if (jQuery('#select-all').val() == 1) {
            jQuery('#massSelectDropdown').html('<li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>');
        } else {
            jQuery('#massSelectDropdown').html(unCheckedSelectionDropdownHtml);
            dropdownChange();
        }

        if (parseInt(jQuery('#selected-records').text()) != 0 && parseInt(jQuery("#total-records").text()) == parseInt(jQuery('#selected-records').text())) {
            jQuery('#massDeleteCheckbox').prop("checked", true);
            jQuery('#select-all').val(1);
            jQuery('#massSelectDropdown').html('<li><a href="javascript:void(0)" onClick="deselectAll(true)" class="dropdown-item"> {{ trans("core::core.labels.deselect_all") }} </a></li>');
            jQuery('#idToDelete').val('');
            jQuery('#idsNotToDelete').val('');
        }

        if (parseInt(jQuery('#selected-records').text()) != 0) {
            $('#mass-delete').attr('disabled', false);
        } else {
            $('#mass-delete').attr('disabled', true);
        }

    }
</script>