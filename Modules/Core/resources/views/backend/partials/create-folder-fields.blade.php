{{ formStart(null,"POST" ,'admin.module.savefolder' ,updateUrlParams(), ['id' => 'clear_cache'])}}
<div class="row">
    <div class="col-12 col-sm-6 col-lg-12">
        <div class="card card-info card-outline card-outline-tabs">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="custom-tabs-three-tab" catalog="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="custom-tabs-three-catalog-info-tab" data-toggle="pill" href="#custom-tabs-three-catalog-info" catalog="tab" aria-controls="custom-tabs-three-catalog-info" aria-selected="true">{{ trans("core::core.titles.folder_info") }}</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                    <div class="tab-pane fade show active" id="custom-tabs-three-catalog-info" catalog="tabpanel" aria-labelledby="custom-tabs-three-catalog-info-tab">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! normalText("folder_name", "core::core.labels.folder_name", $errors, null, ["class" => "form-control required" ]) !!}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    {!! normalSelect("module_name", "core::core.labels.module_name", $errors,$modules, null, ["class" => "form-control required" ]) !!}
                                </div>
                            </div>
                            <div class="col-md-4">
                                {!! normalCheckbox("include_view", "core::core.labels.include_view", $errors, null, ["class" => "form-control"]) !!}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                {!! normalSelect("database[softDelete]", "core::core.labels.soft_delete", $errors, $yesNoOptions, null, ["class" => "form-control required"]) !!}
                            </div>
                        </div>
                        <div id="ModuleContainer">
                            <!--Textboxes will be added here -->
                        </div>
                        {!! normalInputOfType('hidden','sort_order_no', "sort_order_no", $errors,null,['id'=>'outputvalues','hide_label' => true]) !!}
                        @if($folderType == 2)
                        {!! normalInputOfType('hidden','translatable_folder', "core::core.labels.translatable_folder", $errors,1,['hide_label' => true]) !!}
                        @endif
                    </div>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
{{ formEnd() }}




@push('js-stack')
<script type="text/javascript">
    var i = 0;

    $(function() {

        $("#ModuleContainer").sortable({
            update: function(event, ui) {
                getIdsOfImages();
            } //end update
        });

        jQuery.validator.addMethod("alphanumeric", function(value, element) {
            return this.optional(element) || /^[\w.]+$/i.test(value);
        }, "Letters, numbers, and underscores only please");

        jQuery("#clear_cache").validate({
            rules: {
                "module_name": {
                    required: true,
                    alphanumeric: true,
                }
            },
            messages: {
                "module_name": {
                    required: "This field is required",
                    alphanumeric: "Only alpha numeric are allowed",
                }
            }
        });


        $("#buttonModuleAdd").bind("click", function() {

            var div = $("<div />");
            div.html(GenerateModulebox());
            $("#ModuleContainer").append(div);


            jQuery(".filterArray").change(function() {
                jQuery('.filterArray').each(function(filterKey, filterElement) {
                    if (jQuery(filterElement).val() != '') {
                        jQuery('.gridArray').each(function(gridKey, gridElement) {
                            if (filterKey == gridKey) {
                                jQuery(gridElement).prop('checked', true);
                                jQuery(gridElement).prop('disabled', true);
                            }
                        });
                    } else {
                        jQuery('.gridArray').each(function(gridKey, gridElement) {
                            if (filterKey == gridKey) {
                                //  jQuery(gridElement).prop('checked', false);
                                jQuery(gridElement).prop('disabled', false);
                            }
                        });
                    }

                });
            });


            jQuery(".column_name").change(function() {
                jQuery('.column_name').each(function(columnKey, columnElement) {
                    var value = jQuery(columnElement).val().toLowerCase();
                    value = value.replace(/ /g, "_");
                    jQuery('.add_fields').each(function(fieldsKey, fieldsElement) {
                        if (columnKey == fieldsKey) {
                            jQuery(fieldsElement).val(value);
                        }
                    });
                });
            });


            //change null to NULL and check nullable
            jQuery(".defaultCheck").change(function() {
                jQuery('.defaultCheck').each(function(defaultKey, defaultElement) {
                    var value = jQuery(defaultElement).val().toLowerCase();
                    if (value == 'null') {
                        jQuery(defaultElement).val('NULL');
                        jQuery('.nullableCheck').each(function(nullableKey, nullableElement) {
                            if (defaultKey == nullableKey) {
                                jQuery(nullableElement).prop('checked', true);
                                jQuery(nullableElement).prop("disabled", true);

                            }
                        });

                    } else {
                        jQuery('.nullableCheck').each(function(nullableKey, nullableElement) {
                            if (defaultKey == nullableKey) {
                                jQuery(nullableElement).prop('checked', false);
                                jQuery(nullableElement).prop("disabled", false);

                            }
                        });
                    }
                });
            });


            jQuery(".foreignKeyCheck").change(function() {
                jQuery('.foreignKeyCheck').each(function(foreignKey, foreignElement) {
                    if (jQuery(foreignElement).prop("checked") == true) {
                        jQuery('.temp').each(function(foreignDivKey, foreignDivElement) {
                            if (foreignKey == foreignDivKey) {
                                jQuery(foreignDivElement).show();
                            }
                        });

                        jQuery('.defaultCheck').each(function(defaultKey, defaultElement) {
                            if (foreignKey == defaultKey) {
                                var value = jQuery(defaultElement).val().toLowerCase();
                                if (value == 'null') {
                                    jQuery(defaultElement).val('0');
                                }
                            }
                        });


                        jQuery('.unsignedCheck').each(function(unsignedKey, unsignedElement) {
                            if (foreignKey == unsignedKey) {
                                jQuery(unsignedElement).prop("checked", true);
                                jQuery(unsignedElement).prop("disabled", true);
                            }
                        });

                        jQuery('.tableName').each(function(foreignTableKey, foreignTableElement) {
                            if (foreignKey == foreignTableKey) {
                                jQuery(foreignTableElement).addClass('required');
                            }
                        });
                    } else {
                        jQuery('.temp').each(function(foreignDivKey, foreignDivElement) {
                            if (foreignKey == foreignDivKey) {
                                jQuery(foreignDivElement).hide();
                            }
                        });

                        jQuery('.unsignedCheck').each(function(unsignedKey, unsignedElement) {
                            if (foreignKey == unsignedKey) {
                                jQuery(unsignedElement).prop("checked", false);
                                jQuery(unsignedElement).prop("disabled", false);
                            }
                        });


                        jQuery('.tableName').each(function(foreignTableKey, foreignTableElement) {
                            if (foreignKey == foreignTableKey) {
                                jQuery(foreignTableElement).removeClass('required');
                            }
                        });
                    }

                });
            });
        });
    });


    function GenerateModulebox() {
        var element = `
        <div id="sortNoindex" class = "listitemClass" >
        <div class="migration-que-wrapper">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        {!! normalText("columnName[index]", " ", $errors, null, ["class" => "form-control column_name required", "hide_label"=>true , "placeholder" => "Column Name" ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! normalSelect("filterType[index]", " ", $errors, $filterOptions, null, ["class" => "form-control filterArray", "hide_label"=>true]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! normalSelect("inputType[index]", " ", $errors, $inputOptions, null, ["class" => "form-control inputArray", "hide_label"=>true]) !!}
                    </div>
                </div>
            </div>
            <div class= "row">
                <div class="col-md-3">
                    {!! normalCheckbox("gridView[index]", "Grid", $errors, null, ["class" => "form-control gridArray"]) !!}
                </div>
                <div class="col-md-3">
                    {!! normalCheckbox("isRequired[index]", "Is Required", $errors, null, ["class" => "form-control"]) !!}
                </div>
                <div class="col-md-3">
                    {!! normalCheckbox("image[index]", "Image", $errors, null, ["class" => "form-control"]) !!}
                </div>
                @if($folderType == 2)
                    <div class="col-md-3">
                        {!! normalCheckbox("database[translatable_key][index]", "Translatable", $errors, null, ["class" => "form-control"]) !!}
                    </div>
                @endif
            </div>
            <div class = "migration-dashed-que-wrapper">
            </div>
            <h6 class = "text-center">Database</h6>
            <div class = "migration-dashed-que-wrapper">
            </div>
            <div class="row ">
                <div class="col-md-3">
                    <div class="form-group">
                    {!! normalText("database[addFields][index]", " ", $errors, null, ["class" => "form-control add_fields required", "hide_label"=>true , "readonly" => "readonly" ]) !!}
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                    {!! normalSelect("database[dataTypes][index]", " ", $errors, $dataTypes, null, ["class" => "form-control required", "hide_label"=>true]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                    {!! normalText("database[defaultKey][index]", " ", $errors, null, ["class" => "form-control defaultCheck", "hide_label"=>true , "placeholder" => "Default" ]) !!}
                    </div>
                </div>
            </div>
            <div class = "row migration-checkbox-que-wrapper">
                <div class="col-md-2">
                    {!! normalCheckbox("database[nullableKey][index]", "Nullable", $errors, null, ["class" => "form-control nullableCheck"]) !!}
                </div>
                <div class="col-md-2">
                    {!! normalCheckbox("database[uniqueKey][index]", "Unique", $errors, null, ["class" => "form-control"]) !!}
                </div>
                <div class="col-md-2">
                    {!! normalCheckbox("database[iKey][index]", "Index", $errors, null, ["class" => "form-control"]) !!}
                </div>
                <div class="col-md-2">
                    {!! normalCheckbox("database[unsignedKey][index]", "Unsigned", $errors, null, ["class" => "form-control unsignedCheck"]) !!}
                </div>
                <div class="col-md-2">
                    {!! normalCheckbox("database[foreign][index]", "Foreign Key", $errors, null, ["class" => "form-control foreignKeyCheck"]) !!}
                </div>
            </div>
            <div class="row temp" style = "display: none;">
                <div class="col-md-3">
                    <div class="form-group">
                    {!! normalSelect("database[foreignTable][index]", "", $errors, $tables, null, ["class" => "form-control tableName", "hide_label"=>true]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                    {!! normalSelect("database[foreignTableDelete][index]", "", $errors, $deleteOptions, null, ["class" => "form-control", "hide_label"=>true]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                    {!! normalSelect("database[foreignTableUpdate][index]", "", $errors, $updateOptions, null, ["class" => "form-control", "hide_label"=>true]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-10">
                    <div class="form-group">
                    {!! normalText("database[comment][index]", " ", $errors, null, ["class" => "form-control", "hide_label"=>true , "placeholder" => "Comment" ]) !!}
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" value="Remove" class="remove btn"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
    </div>
        `;
        element = element.replace(/index/g, i++);
        return element;
    }



    function getIdsOfImages() {
        var values = [];
        $('.listitemClass').each(function(index) {
            values.push($(this).attr("id")
                .replace("sortNo", ""));
        });
        $('#outputvalues').val(values);
    }


    $("body").on("click", ".remove", function() {
        $(this).parent().parent().parent().remove();
    });
</script>
@endpush
