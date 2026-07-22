var custom = function (options) {
    var form;
    var result;
    var url;
    var replacements;
    var params;
    var method = 'get';
    var type;
    var vars = {
        success: 'success',
        failure: 'fail'
    };

    this.construct = (options) => {
        jQuery.extend(vars, options);
    };

    this.setMethod = (method) => {
        this.method = method;
        return this;
    }

    this.setFormId = (formId) => {
        this.form = jQuery(`#${formId}`);
        return this;
    };

    this.setUrl = (url) => {
        this.url = url;
        return this;
    };

    this.setParams = (params) => {
        this.params = params;
        return this;
    };

    this.showMessage = (type, messsage) => {
        jQuery(".alert").remove();
        var html = '';
        if (type == vars.success) {
            html = `<div class="sufee-alert alert with-close alert-success alert-dismissible fade show">
                <strong>${messsage}</strong>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>`;
        }

        if (type == vars.failure) {
            html = `<div class="sufee-alert alert with-close alert-danger alert-dismissible fade show">
                <strong>${messsage}</strong>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>`;
        }
        jQuery('div.col-12').prepend(html);
    };

    this.replaceContent = (content) => {
        if (typeof content == "object") {
            jQuery(content).each(function (i, n) {
                if (typeof n.html != "undefined") {
                    jQuery(`#${n.element}`).html(n.html);
                } else if (typeof n.append != "undefined") {
                    jQuery(`#${n.element}`).append(n.append);
                } else if (typeof n.changeValue != "undefined") {
                    jQuery(`#${n.element}`).val(n.changeValue);
                } else if (typeof n.removeElement != "undefined") {
                    jQuery(`#${n.element}`).hide();
                } else if (typeof n.redirect != "undefined") {
                    window.location.href = n.redirect;
                }
            });
            syncGridPanelToggles();
        }
    };

    this.
        saveForm = () => {
            var obj = this;
            loaderShow();
            jQuery.ajax({
                type: this.form.attr("method"),
                url: this.form.attr("action"),
                data: this.form.serialize(),
                dataType: "json",
                async: false,
                success: function (rs) {
                    if (rs.type == vars.success) {
                        if (rs.message !== null && typeof rs.message !== "undefined") {
                            obj.showMessage(rs.type, rs.message);
                        }

                        if (typeof rs.content !== "undefined") {
                            obj.replaceContent(rs.content);
                            obj.setCurrentPage();
                        }

                        if (typeof rs.redirectUrl !== "undefined") {
                            window.location = rs.redirectUrl;
                        }
                    } else if (rs.type == vars.failure) {
                        // obj.showMessage(rs.type, rs.message);
                        if (typeof rs.redirectUrl !== "undefined") {
                            window.location = rs.redirectUrl;
                        } else if (typeof rs.message !== "undefined") {
                            obj.showMessage(rs.type, rs.message);
                        }
                    }
                    obj.result = rs;
                    setTimeout(function () { loaderHide(); }, 500);
                },
                statusCode: {
                    403: function () {
                        if (typeof permissionDeniedMessage !== "undefined") {
                            obj.showMessage('error', permissionDeniedMessage);
                        } else {
                            obj.showMessage('error', "Permission denied.");
                        }
                        setTimeout(function () { loaderHide(); }, 500);
                    }
                }
            });
            return obj.result;
        };

    this.setCurrentPage = () => {
        if (jQuery("#current_page").length) {
            var page = jQuery("#current_page").val();
            var lastPage = jQuery("#last_page").val();

            if (parseInt(page) > parseInt(lastPage)) {
                page = lastPage;
            }
            jQuery("#current_page").val(page);
        }
    };

    this.saveFormWithFile = () => {
        var obj = this;
        loaderShow();
        var form = this.form;
        var dataform = new FormData(form[0]);
        jQuery.ajax({
            type: this.form.attr("method"),
            url: this.form.attr("action"),
            data: dataform,
            enctype: 'multipart/form-data',
            cache: false,
            contentType: false,
            processData: false,
            dataType: "json",
            async: false,
            success: function (rs) {
                if (rs.type == vars.success) {
                    if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }

                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                    }

                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    }
                } else if (rs.type == vars.failure) {
                    // obj.showMessage(rs.type, rs.message);
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    } else if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                }
                obj.result = rs;
                loaderHide();
            },
            statusCode: {
                403: function () {
                    if (typeof permissionDeniedMessage !== "undefined") {
                        obj.showMessage('error', permissionDeniedMessage);
                    } else {
                        obj.showMessage('error', "Permission denied.");
                    }
                    loaderHide();
                }
            }
        });

        return obj.result;
    };

    this.getContent = () => {
        var obj = this;
        loaderShow();
        this.params = jQuery.extend(this.params, { 'last_page': jQuery("#last_page").val(), 'page': jQuery("#current_page").val() });
        jQuery.ajax({
            type: this.method,
            url: this.url,
            data: this.params,
            dataType: "json",
            async: false,
            success: function (rs) {
                if (rs.type == vars.success) {
                    if (rs.message !== null && typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                    }
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    }
                } else if (rs.type == vars.failure) {
                    // obj.showMessage(rs.type, rs.message);
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    } else if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }

                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                    }
                }
                obj.result = rs;
                setTimeout(function () { loaderHide(); }, 500);
            },
            statusCode: {
                403: function () {
                    if (typeof permissionDeniedMessage !== "undefined") {
                        obj.showMessage('error', permissionDeniedMessage);
                    } else {
                        obj.showMessage('error', "Permission denied.");
                    }
                    setTimeout(function () { loaderHide(); }, 500);
                }
            }
        });
        return obj.result;
    };

    //Mass update status
    this.massUpdateStatus = () => {
        var obj = this;
        jQuery.ajax({
            type: this.method,
            url: this.url,
            data: this.params,
            dataType: "json",
            async: false,
            success: function (rs) {
                if (rs.type == vars.success) {
                    $('#update-status').modal('hide');
                    if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                    }
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    }
                } else if (rs.type == vars.failure) {
                    // obj.showMessage(rs.type, rs.message);
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    } else if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                }
                obj.result = rs;
                jQuery('.select-item').prop('checked', false);
                jQuery('#massDeleteCheckbox').prop("checked", false);
            },
            statusCode: {
                403: function () {
                    if (typeof permissionDeniedMessage !== "undefined") {
                        obj.showMessage('error', permissionDeniedMessage);
                    } else {
                        obj.showMessage('error', "Permission denied.");
                    }
                }
            }
        });
        return obj.result;
    };
    //End mass update status

    this.save = () => {
        var obj = this;
        loaderShow();
        jQuery.ajax({
            type: this.method,
            url: this.url,
            data: this.params,
            dataType: "json",
            async: false,
            success: function (rs) {
                if (rs.type == vars.success) {
                    if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                    }

                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    }
                } else if (rs.type == vars.failure) {
                    // obj.showMessage(rs.type, rs.message);
                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    } else if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }
                }
                obj.result = rs;
                loaderHide();
            },
            statusCode: {
                403: function () {
                    if (typeof permissionDeniedMessage !== "undefined") {
                        obj.showMessage('error', permissionDeniedMessage);
                    } else {
                        obj.showMessage('error', "Permission denied.");
                    }
                    loaderHide();
                }
            }
        });
        return obj.result;
    };

    this.construct(options);
};

// Default Ajax Loader
var showIndicator = true;
var beenSubmitted = false;
if (!beenSubmitted) {
    loaderHide();
}

function loaderShow() {
    $('.custom-loader').show();
}
function loaderHide() {
    $('.custom-loader').hide();
}
function ajaxindicatorstart(text) {
    if (jQuery('body').find('#resultLoading').attr('id') != 'resultLoading') {
        var html = `<div id="resultLoading"><div class="spinner"><div class="dot1"></div><div class="dot2"></div></div><div class="modal-backdrop fade in"></div></div>`;
        jQuery('body').append(html);
    }
    jQuery('#resultLoading').fadeIn(300);
    jQuery('body').css('cursor', 'wait');
}

function ajaxindicatorstop() {
    jQuery('#resultLoading').fadeOut(300);
    jQuery('body').css('cursor', 'default');
}

jQuery(document).ajaxStart(function () {
    if (showIndicator) {
        //show ajax indicator
        ajaxindicatorstart('please wait..');
    }
}).ajaxStop(function () {
    //hide ajax indicator
    ajaxindicatorstop();
});

var customObj = new custom({
    success: "success",
    failure: "error"
});

function sumbmitForm(elementId, value, formId, openSearchAccordion = false) {
    jQuery(`#${elementId}`).val(value);
    var result = customObj.setFormId(formId).saveForm();
    var dataArray = jQuery(`#${formId}`).serializeArray();
    jQuery(".delete-collection").val(JSON.stringify(dataArray));
    if (openSearchAccordion) {
        jQuery.each(dataArray, function (key, value) {
            if (value.name !== '_token' && value.name !== 'order_by' && value.name !== 'dir' && value.name !== 'last_page' && value.name !== 'page' && value.name != 'per_page') {
                if (value.value !== "") {
                    jQuery(".filterAccordian").removeClass('collapsed');
                    jQuery(".collapseFilter").addClass('show');
                    toggleGridPanel('filters', true);
                    return false;
                }
            }
        });
    }
    if (result !== undefined && typeof result.type !== "undefined" && result.type == 'success') {
        jQuery(".jquery-datepicker").datepicker();
        getDateOption();
        initDatePicker(date_format);
        timeSlot();
        buttonDisableAccToSlctRecords();
    }
    return false;
}

function getDateOption() {
    jQuery("#created_at_from").datepicker({
        maxDate: 0,
        dateFormat: 'dd-mm-yy',
    });
    jQuery('#created_at_to').datepicker({
        maxDate: 0,
        dateFormat: 'dd-mm-yy',
        beforeShow: function () {
            jQuery(this).datepicker('option', 'minDate', jQuery('#created_at_from').val());
        }
    });
}

/**
 * Visibility of the Columns / Filters grid panels. Kept in memory only, so a full page
 * load always starts with both hidden while the state survives an AJAX grid refresh.
 */
var gridPanelVisibility = { columns: false, filters: false };

/**
 * Shows or hides a grid panel together with its accordion body, and keeps the header
 * button, the Bootstrap collapse classes and the aria state in sync.
 */
function toggleGridPanel(key, show) {
    var panel = jQuery('#gp-panel-' + key);

    if (!panel.length) {
        return;
    }

    gridPanelVisibility[key] = show;
    panel.toggle(show);
    panel.find('.collapse').toggleClass('show', show);
    panel.find('.gp-panel-trigger').toggleClass('collapsed', !show).attr('aria-expanded', show);
    jQuery('.gp-toggle[data-gp-panel="' + key + '"]').attr('aria-pressed', show);
}

/**
 * Reveals a header toggle button only when its panel is on the page, then re-applies the
 * remembered visibility. Runs on load and after every AJAX grid re-render.
 */
function syncGridPanelToggles() {
    jQuery.each(['columns', 'filters'], function (index, key) {
        var exists = jQuery('#gp-panel-' + key).length > 0;

        jQuery('.gp-toggle[data-gp-panel="' + key + '"]').toggle(exists);

        if (exists) {
            toggleGridPanel(key, gridPanelVisibility[key]);
        }
    });
}

jQuery('body').on('click', '.gp-toggle', function () {
    var key = jQuery(this).data('gp-panel');

    toggleGridPanel(key, !gridPanelVisibility[key]);
});

jQuery(function () {
    syncGridPanelToggles();
});

function searchFilter(page = 1) {
    var formId = jQuery("#collection").find("form").attr("id");
    var lastPage = jQuery("#last_page").val();

    if (parseInt(page) > parseInt(lastPage)) {
        page = lastPage;
    }

    sumbmitForm("current_page", page, formId, true);
}

jQuery('body').delegate('#current_page', 'keypress', function (e) {
    var code = e.keyCode || e.which;
    if (code == 13) { //Enter keycode
        e.preventDefault();
        searchFilter(jQuery("#current_page").val())
    }
});

function convert24HourFormat(time) {
    var timeSplit = time.split(" ");
    var ampm = timeSplit[1];
    var timeSplit = timeSplit[0].split(":");
    var hour = "00";
    var mins = timeSplit[1];
    if (ampm == 'PM' && timeSplit[0] < 12) {
        hour = parseInt(parseInt(timeSplit[0]) + 12);
    } else {
        hour = timeSplit[0];
    }
    return hour + ":" + mins;
}

$.validator.setDefaults({
    highlight: function(element, errorClass, validClass) {
        $(element).addClass(errorClass).removeClass(validClass);
        updateTabState(element);
    },
    unhighlight: function(element, errorClass, validClass) {
        $(element).removeClass(errorClass).addClass(validClass);
        updateTabState(element);
    }
});

function updateTabState(element) {
    var $tabPane = $(element).closest(".tab-pane");
    var tabId = $tabPane.attr("id");
    var $tabLink = $('a[href="#' + tabId + '"]');

    // If any visible errors inside this tab → keep tab-error
    if ($tabPane.find(".error:visible").length > 0) {
        $tabLink.addClass("tab-error");
    } else {
        $tabLink.removeClass("tab-error");
    }

    var $outerPane = $tabPane.closest(".tab-content").closest(".tab-pane");
    if ($outerPane.length) {
        var outerId = $outerPane.attr("id");
        var $outerLink = $('a[href="#' + outerId + '"]');

        if ($outerPane.find(".error:visible").length > 0) {
            $outerLink.addClass("tab-error");
        } else {
            $outerLink.removeClass("tab-error");
        }
    }
}

function setLocation(url) {
    window.location.href = url;
}

function initDatePicker(date_format) {
    jQuery("#created_at_from").datepicker({
        maxDate: 0,
        dateFormat: date_format,
    });
    console.log(date_format);
    jQuery('#created_at_to').datepicker({
        maxDate: 0,
        dateFormat: date_format,
        beforeShow: function () {
            jQuery(this).datepicker('option', 'minDate', jQuery('#created_at_from').val());
        }
    });
}

function timeSlot() {
    if (jQuery('input').hasClass('clock')) {
        jQuery('.clock').datetimepicker({
            format: 'LT',
            icons: {
                up: "fa fa-chevron-up",
                down: "fa fa-chevron-down",
            }
        });
    }
}

function highlightTab() {

    var tabs = jQuery('.nav-tabs');
    tabs.find("a").removeClass('tab-error');

    jQuery('.tab-pane').find('input.error,textarea.error,select.error,label.error').each(function (key, element) {

        var tabId = $(element).parents('.tab-pane.ctab-pane').attr('id');

        if (tabId == undefined) {
            tabId = $(element).parents('.tab-pane').attr('id');
        }
        var tab = $("[href*=" + tabId + "]");
        if (tab !== undefined) {
            tab.addClass('tab-error');
        }
    });

}


jQuery(document).ready(() => {
    highlightTab();
    jQuery(".nav-treeview").each(function () {
        if (jQuery(this).children().length == 0) {
            jQuery(this).parent().remove();
        }
    });
    jQuery(".save").on("click", function () {
        var formId = jQuery(this).attr("data-form-id");
        jQuery(`#${formId} .snc`).val(0);
        jQuery(`#${formId}`).submit();
        highlightTab();
    });

    jQuery(".savencontinue").on("click", function () {
        var formId = jQuery(this).attr("data-form-id");
        jQuery(`#${formId} .snc`).val(1);
        jQuery(`#${formId}`).submit();
        highlightTab();
    });

    // When the user scrolls the page, execute myFunction
    window.onscroll = function () {
        fixScroll()
    };
    // Add the sticky class to the fixElement when you reach its scroll position. Remove "sticky" when you leave the scroll position
    function fixScroll() {
        // Get the header
        var fixElement = document.getElementsByClassName("content-header");
        if (fixElement.length) {
            // Get the offset position of the navbar
            var sticky = fixElement[0].offsetTop;
            if (window.pageYOffset > sticky) {
                fixElement[0].classList.add("sticky-header");
            } else {
                fixElement[0].classList.remove("sticky-header");
            }
        }
    }

})

/*Summernote textarea on all form*/
if (jQuery(".formated-textarea").length > 0) {
    jQuery('.formated-textarea').summernote({
        'fontNames': ['Helvetica', "sans-serif", "Arial", "Arial Black", "Comic Sans MS", "Courier New", "F37GingerPro-ExtraBold", 'F37GingerPro-Bold', 'Impact', 'Tahoma', 'Times New Roman', 'verdana'
        ],
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear', 'superscript', 'subscript', 'italic', 'strikethrough']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['insert', ['link', 'picture', 'table', 'hr']],
            ['view', ['codeview', 'help', 'undo', 'redo']],
            ['fontsize', ['fontsize']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']]
        ],

        height: 300,

        callbacks: {
            onImageUpload: function (image) {
                uploadImage(image[0], this);
            }
        }
    });
}

function uploadImage(image, element) {
    var formData = new FormData();
    formData.append("image", image);
    formData.append("_token", csrfToken);

    jQuery.ajax({
        type: "POST",
        url: image_url,
        data: formData,
        contentType: false,
        processData: false,

        success: function (responseData) {
            var image = $('<img>').attr('src', responseData.imagePath);
            $(element).summernote("insertNode", image[0]);
        },
        error: function (data) {
            console.log(data);
        }
    });
}

jQuery(document).on('click', '.input-group-append', function () {
    var classes = jQuery(this).find('span').attr('class');
    classes = classes.split(' ');
    if (classes.includes('fa-eye-slash') || classes.includes('fa-eye')) {
        jQuery(this).find('span').toggleClass("fa-eye fa-eye-slash");
        var input = $(jQuery(this).parent().children());
        if (input.attr("type") === "password") {
            input.attr("type", "text");
        } else {
            input.attr("type", "password");
        }
    }
});