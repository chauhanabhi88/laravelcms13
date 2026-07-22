<div class="modal fade" id="create_migration_name" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="module_name_header"></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {{ formStart(false, "POST", 'admin.module.create_migration', updateUrlParams(), ['enctype'=>'multipart/form-data','id' => 'create_migration_form']) }}
                <div class="modal-body">

                    {!! normalInputOfType('hidden','module', "core::core.labels.name", $errors,null,['id'=>'module_name','hide_label' => true]) !!}
                    <div class="row">
                        <div class="col-md-6">

                            {!! normalSelect("dbOperation", "core::core.labels.db_operation", $errors,isset($dbOperations) && !empty($dbOperations) ? $dbOperations : [], null, ["id" => 'dbOperationChange' , "class" => "form-control required"]) !!}
                        </div>
                        <div class="col-md-6" id="tableNameDisplay">
                            {!! normalText("name", "core::core.labels.table_name", $errors, null, ["id"=>"tableNameRequire" , "class" => "form-control required"]) !!}
                        </div>
                        <div class="col-md-6" style="display: none;" id="dbNameDisplay">
                            {!! normalSelect("dbTable", "core::core.labels.db_table", $errors, $tables, null, ["id"=>"dbNameRequire","class" => "form-control"]) !!}
                        </div>
                    </div>
                    <div class="softDelete row" style="display: none;">
                        <div class="col-md-6">
                            {!! normalSelect("softDelete", "core::core.labels.soft_delete", $errors, $yesNoOptions, null, ["id"=>"softDeleteOption","class" => "form-control"]) !!}
                        </div>
                    </div>
                    <input class="btn btn-primary btn-fw save" id="buttonAdd" type="button" value="Add Fields" /><br><br>

                    <div id="TextBoxContainer">
                        <!--Textboxes will be added here -->
                    </div>
                </div>
            {{formEnd()}}
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary btn-fw" data-dismiss="modal">{{ trans('core::core.buttons.cancel') }}</button>
                <button class="btn btn-primary btn-fw save" data-form-id="create_migration_form">{{ trans('core::core.buttons.save') }}</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
<div class="col-md-3" id="table_column" style="display: none;">

</div>

@push('js-stack')
<script type="text/javascript">
    var i = 0;

    $(function() {
        $("#buttonAdd").bind("click", function() {
            var div = $("<div />");
            div.html(GenerateTextbox());
            $("#TextBoxContainer").append(div);


            var after = jQuery("#table_column").html();
            jQuery(".afterColumnDiv").html(after);
            var ajaxCount = 0;
            jQuery('.afterColumnDiv').each(function(defaultKey, defaultElement) {
                var after = jQuery(defaultElement).html();
                after = after.replace(/index/g, ajaxCount);
                jQuery(defaultElement).html(after);
                ajaxCount++;
            });



            jQuery(".dataTypeValue").change(function() {
                value = jQuery(this).val();
                key = jQuery(this).attr('dataTypeKey');
                if (value == 'string') {
                    jQuery(".lengthCol_" + key).show();
                    jQuery(".lengthKey_" + key).attr('max', 255);
                    jQuery(".lengthKey_" + key).attr('min', 1);
                    jQuery(".lengthKey_" + key).addClass('stringNumber');
                    jQuery(".lengthKey_" + key).removeClass('decimalNumber');
                } else if (value == 'decimal') {
                    jQuery(".lengthCol_" + key).show();
                    jQuery(".lengthKey_" + key).removeAttr('max');
                    jQuery(".lengthKey_" + key).removeAttr('min');
                    jQuery(".lengthKey_" + key).addClass('decimalNumber');
                    jQuery(".lengthKey_" + key).removeClass('stringNumber');
                } else if (value == 'integer') {
                    jQuery(".lengthCol_" + key).show();
                    jQuery(".lengthKey_" + key).attr('max', 11);
                    jQuery(".lengthKey_" + key).attr('min', 1);
                    jQuery(".lengthKey_" + key).addClass('stringNumber');
                    jQuery(".lengthKey_" + key).removeClass('decimalNumber');
                } else if (value == 'smallInteger') {
                    jQuery(".lengthCol_" + key).show();
                    jQuery(".lengthKey_" + key).attr('max', 6);
                    jQuery(".lengthKey_" + key).attr('min', 1);
                    jQuery(".lengthKey_" + key).addClass('stringNumber');
                    jQuery(".lengthKey_" + key).removeClass('decimalNumber');
                } else if (value == 'tinyInteger') {
                    jQuery(".lengthCol_" + key).show();
                    jQuery(".lengthKey_" + key).attr('max', 4);
                    jQuery(".lengthKey_" + key).attr('min', 1);
                    jQuery(".lengthKey_" + key).addClass('stringNumber');
                    jQuery(".lengthKey_" + key).removeClass('decimalNumber');
                } else {
                    jQuery(".lengthCol_" + key).hide();
                    jQuery(".lengthKey_" + key).removeAttr('max');
                    jQuery(".lengthKey_" + key).removeAttr('min');
                    jQuery(".lengthKey_" + key).removeClass('decimalNumber');
                    jQuery(".lengthKey_" + key).removeClass('stringNumber');
                }
            });



            //show or hide column list
            var value = jQuery('#dbOperationChange').val();
            if (value != 'add') {
                jQuery(".afterColumnDiv").hide();
            } else {
                jQuery(".afterColumnDiv").show();
            }


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




        $("body").on("click", ".remove", function() {
            $(this).parent().parent().parent().remove();
        });
    });




    function GenerateTextbox() {

        var element = `
                        <div class="migration-que-wrapper">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                    {!! normalText("addFields[index]", " ", $errors, null, ["class" => "form-control required", "hide_label"=>true , "placeholder" => "Column Name" ]) !!}
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                    {!! normalSelect("dataTypes[index]", " ", $errors, isset($dataTypes) && !empty($dataTypes) ? $dataTypes : [], null, ["class" => "form-control required dataTypeValue", "hide_label"=>true,"dataTypeKey" => "index"]) !!}
                                    </div>
                                </div>
                                <div class="col-md-2 lengthCol_index" style = "display: none;">
                                    <div class="form-group">
                                    {!! normalText("lengthKey[index]", " ", $errors, null, ["class" => "form-control lengthKey_index", "hide_label"=>true , "placeholder" => "Length" ]) !!}
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                    {!! normalText("defaultKey[index]", " ", $errors, null, ["class" => "form-control defaultCheck", "hide_label"=>true , "placeholder" => "Default" ]) !!}
                                    </div>
                                </div>
                                <div class="col-md-2 afterColumnDiv"  style = "display: none;">
                                @include('core::backend.partials.table-columns')
                                </div>
                            </div>
                            <div class = "row migration-checkbox-que-wrapper">
                                <div class="col-md-2">
                                    {!! normalCheckbox("nullableKey[index]", "Nullable", $errors, null, ["class" => "form-control nullableCheck"]) !!}
                                </div>
                                <div class="col-md-2">
                                    {!! normalCheckbox("uniqueKey[index]", "Unique", $errors, null, ["class" => "form-control"]) !!}
                                </div>
                                <div class="col-md-2">
                                    {!! normalCheckbox("iKey[index]", "Index", $errors, null, ["class" => "form-control"]) !!}
                                </div>
                                <div class="col-md-2">
                                    {!! normalCheckbox("unsignedKey[index]", "Unsigned", $errors, null, ["class" => "form-control unsignedCheck"]) !!}
                                </div>
                                <div class="col-md-2">
                                    {!! normalCheckbox("foreign[index]", "Foreign Key", $errors, null, ["class" => "form-control foreignKeyCheck"]) !!}
                                </div>
                            </div>
                            <div class="row temp" style = "display: none;">
                                <div class="col-md-3">
                                    <div class="form-group">
                                    {!! normalSelect("foreignTable[index]", "", $errors, $tables, null, ["class" => "form-control tableName", "hide_label"=>true]) !!}
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                    {!! normalSelect("foreignTableDelete[index]", "", $errors, $deleteOptions, null, ["class" => "form-control", "hide_label"=>true]) !!}
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                    {!! normalSelect("foreignTableUpdate[index]", "", $errors, $updateOptions, null, ["class" => "form-control", "hide_label"=>true]) !!}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                    {!! normalText("comment[index]", " ", $errors, null, ["class" => "form-control", "hide_label"=>true , "placeholder" => "Comment" ]) !!}
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" value="Remove" class="remove btn"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>`;

        element = element.replace(/index/g, i++);


        return element;
    }


    jQuery('#dbOperationChange').change(function() {
        var value = jQuery('#dbOperationChange').val();
        if (value != 'create') {
            jQuery("#tableNameDisplay").hide();
            jQuery("#tableNameRequire").removeClass('required');
            jQuery("#dbNameDisplay").show();
            jQuery("#dbNameRequire").addClass('required');
            jQuery(".softDelete").hide();
            jQuery("#softDeleteOption").removeClass('required');

        } else {
            jQuery(".softDelete").show();
            jQuery("#softDeleteOption").addClass('required');
            jQuery("#tableNameDisplay").show();
            jQuery("#tableNameRequire").addClass('required');
            jQuery("#dbNameDisplay").hide();
            jQuery("#dbNameRequire").removeClass('required');
        }

        if (value == 'drop') {
            jQuery('#buttonAdd').hide();
            jQuery(".migration-que-wrapper").remove();
        } else {
            jQuery('#buttonAdd').show();
        }

        if (value != 'add') {
            jQuery(".afterColumnDiv").hide();
        } else {
            jQuery(".afterColumnDiv").show();
        }

    });

    jQuery("#dbNameRequire").change(function() {
        var value = jQuery('#dbNameRequire').val();
        customObj.setUrl('{{route("admin.module.getcolumns",updateUrlParams())}}').setMethod("POST").setParams({
            'value': value,
            '_token': '{{csrf_token()}}'
        }).getContent();


        var after = jQuery("#table_column").html();
        console.log(after);
        jQuery(".afterColumnDiv").html(after);

        var ajaxCount = 0;
        jQuery('.afterColumnDiv').each(function(defaultKey, defaultElement) {
            var after = jQuery(defaultElement).html();
            after = after.replace(/index/g, ajaxCount);
            jQuery(defaultElement).html(after);
            ajaxCount++;
        });


    });

    jQuery.validator.addMethod("stringNumber", function(value, element) {
        return /^[0-9]*$/i.test(value);
    }, 'Enter valid character length for string.');

    jQuery.validator.addMethod("decimalNumber", function(value, element) {
        return /^[1-9]\d*,[0-9]\d*$/i.test(value);
    }, 'Enter valid length for decimal.');
</script>
@endpush