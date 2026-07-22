var custom = function(options) {
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
            html = `<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>${messsage}</div>`;
        }

        if (type == vars.failure) {
            html = `<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>${messsage}</div>`;
        }
        jQuery('div.content').prepend(html);
    };

    this.replaceContent = (content) => {
        if (typeof content == "object") {
            jQuery(content).each(function(i, n) {
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
        }
    };

    this.saveForm = () => {
        var obj = this;
        jQuery.ajax({
            type: this.form.attr("method"),
            url: this.form.attr("action"),
            data: this.form.serialize(),
            dataType: "json",
            async: false,
            success: function(rs) {
                if (rs.type == vars.success) {
                    if (typeof rs.message !== "undefined") {
                        obj.showMessage(rs.type, rs.message);
                    }

                    if (typeof rs.content !== "undefined") {
                        obj.replaceContent(rs.content);
                        obj.setCurrentPage();
                    }

                    if (typeof rs.redirectUrl !== "undefined") {
                        window.location = rs.redirectUrl;
                    }
                } else if (rs.type == obj.failure) {
                    obj.showMessage(rs.type, rs.message);
                }
                obj.result = rs;
            }
        });
        return obj.result;
    };

    this.setCurrentPage = () => {
        if (jQuery("#current_page").length){
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
            success: function(rs) {
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
                } else if (rs.type == obj.failure) {
                    obj.showMessage(rs.type, rs.message);
                }
                obj.result = rs;
            }
        });
        return obj.result;
    };

    this.getContent = () => {
        var obj = this;
        jQuery.ajax({
            type: this.method,
            url: this.url,
            data: this.params,
            dataType: "json",
            async: false,
            success: function(rs) {
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
                } else if (rs.type == obj.failure) {
                    obj.showMessage(rs.type, rs.message);
                }
                obj.result = rs;
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
            success: function(rs) {
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
                } else if (rs.type == obj.failure) {
                    obj.showMessage(rs.type, rs.message);
                }
                obj.result = rs;
                jQuery('.select-item').prop('checked',false);
                jQuery('#massDeleteCheckbox').prop("checked", false);
            }   
        });
        return obj.result;
    };
    //End mass update status

    this.save = () => {
        var obj = this;
        jQuery.ajax({
            type: this.method,
            url: this.url,
            data: this.params,
            dataType: "json",
            async: false,
            success: function(rs) {
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
                    obj.showMessage(rs.type, rs.message);
                }
                obj.result = rs;
            }
        });
        return obj.result;
    };

    this.construct(options);
};

// Default Ajax Loader
var showIndicator = true;

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

jQuery(document).ajaxStart(function() {
    if (showIndicator) {
        //show ajax indicator
        ajaxindicatorstart('please wait..');
    }
}).ajaxStop(function() {
    //hide ajax indicator
    ajaxindicatorstop();
});

var customObj = new custom({
    success: "success",
    failure: "error"
});

function sumbmitForm(elementId, value, formId) {
    jQuery(`#${elementId}`).val(value);
    customObj.setFormId(formId).saveForm();
    return false;
}

function searchFilter() {
    var formId = jQuery("#collection").find("form").attr("id");
    var page = jQuery("#current_page").val();
    var lastPage = jQuery("#last_page").val();

    if (parseInt(page) > parseInt(lastPage)) {
        page = lastPage;
    }
    
    sumbmitForm("current_page", page, formId);
}

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

function setLocation(url) {
    window.location.href = url;
}

function initDatePicker() {
    jQuery( ".jquery-datepicker" ).datepicker({
        dateFormat: 'dd-mm-yy',
        changeMonth: true,
        changeYear: true
    });
}

jQuery(document).ready(() => {
    jQuery(".save").on("click", function() {
        var formId = jQuery(this).attr("data-form-id");
        jQuery(`#${formId} .snc`).val(0);
        jQuery(`#${formId}`).submit();
    });

    jQuery(".savencontinue").on("click", function() {
        var formId = jQuery(this).attr("data-form-id");
        jQuery(`#${formId} .snc`).val(1);
        jQuery(`#${formId}`).submit();
    });

    // When the user scrolls the page, execute myFunction
    window.onscroll = function () {
        fixScroll()
    };
    // Add the sticky class to the fixElement when you reach its scroll position. Remove "sticky" when you leave the scroll position
    function fixScroll() {
        // Get the header
        var fixElement = document.getElementsByClassName("content-header");
        if (fixElement) {
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
