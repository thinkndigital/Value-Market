// user login form
$(".form_authentication").submit(function (e) {
    e.preventDefault();
    var form = $(this);
    var url = form.attr("action");
    var method = form.attr("method");
    var submit_btn = $(".login_button");
    var btn_html = $(".login_button").html();
    var btn_val = $(".login_button").val();
    var button_text =
        btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
    var data = form.serialize();
    var token = $('meta[name="csrf-token"]').attr("content");
    $.ajax({
        url: url,
        method: method,
        data: data,
        beforeSend: function () {
            submit_btn.html("Please Wait..");
            submit_btn.attr("disabled", true);
        },
        success: function (response) {
            // Determine the appropriate redirection URL based on the response
            var location = response.location || "";

            if (response.error_message) {
                if (Array.isArray(response.error_message)) {
                    response.error_message.forEach(function (errorMessage) {
                        iziToast.error({
                            title: "Error",
                            message: errorMessage,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: "Error",
                        message: response.error_message,
                        position: "topRight",
                    });
                }

                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
            }

            if (response.message) {
                // Success message received
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.success({
                    title: "Success",
                    message: response.message,
                    position: "topRight",
                });
                setTimeout(function () {
                    // Redirect to the appropriate URL
                    window.location.href = location;
                }, 1000);
            } else if (response.errors && response.errors.role) {
                // Role-based error message received
                iziToast.error({
                    title: "Error",
                    message: response.errors.role[0],
                    position: "topRight",
                });
                token;
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
            }
            token;
            // Redirect to the appropriate URL
        },
        error: function (xhr) {
            var errors = xhr.responseJSON.errors;
            for (var key in errors) {
                if (errors.hasOwnProperty(key)) {
                    var errorMessages = errors[key];
                    for (var i = 0; i < errorMessages.length; i++) {
                        iziToast.error({
                            title: "Error",
                            message: errorMessages[i],
                            position: "topRight",
                        });
                    }
                    token;
                    submit_btn.html(button_text);
                    submit_btn.attr("disabled", false);
                }
            }
        },
    });
});

$(function () {
    // Function to toggle password visibility
    $(".toggle_new_password").click(function () {
        var input = $(".show_new_password");
        var icon = $(this).find("i");
        var type = input.attr("type") == "password" ? "text" : "password";
        input.attr("type", type);
        icon.toggleClass("bx-show bx-low-vision");
    });
});

function validateNumberInput(input) {
    // Remove any non-numeric characters from the input value
    input.value = input.value.replace(/\D/g, "");
}

function show_password() {
    var passwordInput = $("#show_password");
    var eyeIcon = $(".password_show");
    var lowVisionIcon = $(".low_vision");

    if (passwordInput.attr("type") === "password") {
        passwordInput.attr("type", "text");
        eyeIcon.addClass("d-none");
        lowVisionIcon.removeClass("d-none");
    } else {
        passwordInput.attr("type", "password");
        eyeIcon.removeClass("d-none");
        lowVisionIcon.addClass("d-none");
    }
}
$(function () {
    var eyeIcon = $(".password_show");
    eyeIcon.addClass("d-none");
});

function copyCombinedInfo() {
    var mobileInfo = $("#mobileInfo").text();
    var passwordInfo = $("#passwordInfo").text();

    // Display combinedInfo in mobileInput and passwordInput
    $(".copied_mobile").val(mobileInfo);
    $(".copied_password").val(passwordInfo);
}

iziToast.settings({
    position: "topRight",
});

var appUrl = document.getElementById("app_url").dataset.appUrl;
var from = "admin";
if (
    window.location.href.indexOf("seller/") > -1 &&
    window.location.href.indexOf("admin/") == -1
) {
    from = "seller";
}
if (window.location.href.indexOf("delivery_boy/") > -1) {
    from = "delivery_boy";
}
$("#validationForm").submit(function (e) {
    e.preventDefault(); // Prevent form submission

    var form = $(this);
    var url = form.attr("action");
    var method = form.attr("method");
    var formData = new FormData(form[0]);

    // Clear previous error messages
    $(".text-danger").empty();
    iziToast.destroy();

    // Validate other fields

    $.ajax({
        url: url,
        type: method,
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            // Handle successful response
            iziToast.success({
                title: "Success",
                message: response.message,
                position: "topRight",
            });
            // Reload the page or perform other actions as needed
            $("#validationForm")[0].reset();
            setTimeout(function () {
                window.location.reload();
            }, 3000);
        },
        error: function (xhr) {
            // Handle error response
            if (xhr.status === 422) {
                var errors = xhr.responseJSON.errors;
                // Display validation errors
                $.each(errors, function (field, errorMessages) {
                    $.each(errorMessages, function (index, errorMessage) {
                        $("#" + field)
                            .siblings(".text-danger")
                            .append("<p>" + errorMessage + "</p>");
                    });
                });
                var message =
                    xhr.responseJSON.message || "Validation error(s) occurred.";
                iziToast.error({
                    title: "Error",
                    message: message,
                    position: "topRight",
                });
            } else {
                // Handle other types of errors
                var message = xhr.responseJSON.message || "An error occurred.";
                iziToast.error({
                    title: "Error",
                    message: message,
                    position: "topRight",
                });
            }
        },
    });
});

// general event for form submit

$(".submit_form").on("submit", function (e) {
    e.preventDefault(); // Prevent the default form submission

    var form = $(this);
    var url = form.attr("action");
    var method = form.attr("method");

    var submit_btn = $(".submit_button");
    var btn_html = $(".submit_button").html();
    var btn_val = $(".submit_button").val();
    var button_text =
        btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
    tinymce.triggerSave();
    var formData = new FormData(form[0]);
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    formData.append("_token", csrfToken);
    // Clear previous error messages
    $(".text-danger").text("");

    $.ajax({
        url: url,
        method: method,
        data: formData,
        beforeSend: function () {
            submit_btn.html("Please Wait..");
            submit_btn.attr("disabled", true);
        },
        contentType: false,
        processData: false,
        success: function (response) {
            var location = response.location || "";
            token = $('meta[name="csrf-token"]').attr("content");
            if (response.error_message) {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.error({
                    title: "Error",
                    message: response.error_message,
                    position: "topRight",
                });
            } else {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.success({
                    title: "success",
                    message: response.message,
                    position: "topRight",
                });
                form[0].reset();
                setTimeout(function () {
                    // Redirect to the appropriate URL
                    window.location.href = location;
                }, 4000);
                $(".product-image-container").remove();
            }

            if (
                response.addAttribute != undefined &&
                response.addAttribute == true
            ) {
                var lastDiv = $(
                    "#attributes_process > div.product-attr-selectbox:last"
                );
                $(lastDiv).empty();

                $(".edit-product-attributes").trigger("click");
            }
            $(".table").bootstrapTable("refresh");
            $(".modal").modal("hide");
            $("#sendMailModal").modal("hide");
            $(".search_stores").empty();
        },
        error: function (xhr, status, error) {
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;

                // Display each error message in a separate toast
                $.each(errors, function (field, errorMessages) {
                    if (Array.isArray(errorMessages)) {
                        $.each(errorMessages, function (index, errorMessage) {
                            iziToast.error({
                                title: "Error",
                                message: errorMessage,
                                position: "topRight",
                            });
                        });
                    } else {
                        iziToast.error({
                            title: "Error",
                            message: errorMessages,
                            position: "topRight",
                        });
                    }
                });
            } else {
                iziToast.error({
                    title: "Error",
                    message: xhr.responseJSON.message,
                    position: "topRight",
                });
            }
        },
    });
});

$(".media_submit_form").on("submit", function (e) {
    e.preventDefault(); // Prevent the default form submission

    var form = $(this);
    var url = form.attr("action");
    var method = form.attr("method");

    var submit_btn = $(".media_upload_button");
    var btn_html = $(".media_upload_button").html();
    var btn_val = $(".media_upload_button").val();
    var button_text =
        btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;

    var formData = new FormData(form[0]);
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    formData.append("_token", csrfToken);
    // Clear previous error messages
    $(".text-danger").text("");

    $.ajax({
        url: url,
        method: method,
        data: formData,
        beforeSend: function () {
            submit_btn.html("Please Wait..");
            submit_btn.attr("disabled", true);
        },
        contentType: false,
        processData: false,
        success: function (response) {
            var location = response.location || "";
            token = $('meta[name="csrf-token"]').attr("content");
            if (response.error_message) {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.error({
                    title: "Error",
                    message: response.error_message,
                    position: "topRight",
                });
            } else {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.success({
                    title: "success",
                    message: response.message,
                    position: "topRight",
                });
                form[0].reset();

                let filePondElements2 =
                    document.getElementsByClassName("filepond-input");

                // Iterate over all elements with the specified class
                for (let i = 0; i < filePondElements2.length; i++) {
                    let filePond = FilePond.find(filePondElements2[i]);

                    if (filePond != null) {
                        // This will remove all files for each FilePond instance
                        filePond.removeFiles();
                    }
                }
                $(".modal").modal("hide");
            }

            if (
                response.addAttribute != undefined &&
                response.addAttribute == true
            ) {
                var lastDiv = $(
                    "#attributes_process > div.product-attr-selectbox:last"
                );
                $(lastDiv).empty();

                $(".edit-product-attributes").trigger("click");
            }
            $(".table").bootstrapTable("refresh");
            $("#sendMailModal").modal("hide");
            $(".search_stores").empty();
        },
        error: function (xhr, status, error) {
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;

                // Display each error message in a separate toast
                $.each(errors, function (field, errorMessages) {
                    if (Array.isArray(errorMessages)) {
                        $.each(errorMessages, function (index, errorMessage) {
                            iziToast.error({
                                title: "Error",
                                message: errorMessage,
                                position: "topRight",
                            });
                        });
                    } else {
                        iziToast.error({
                            title: "Error",
                            message: errorMessages,
                            position: "topRight",
                        });
                    }
                });
            } else {
                iziToast.error({
                    title: "Error",
                    message: xhr.responseJSON.message,
                    position: "topRight",
                });
            }
        },
    });
});

var current_selected_image;

$("#upload-media").on("click", function () {
    var result = $("#media-upload-table").bootstrapTable("getSelections");

    var path =
        appUrl + "storage/" + result[0].sub_directory + "/" + result[0].name;

    var sub_directory = result[0].sub_directory + "/" + result[0].name;
    var media_type = $("#media-upload-modal")
        .find('input[name="media_type"]')
        .val();

    var input = $("#media-upload-modal")
        .find('input[name="current_input"]')
        .val();
    var is_removable = $("#media-upload-modal")
        .find('input[name="remove_state"]')
        .val();
    var ismultipleAllowed = $("#media-upload-modal")
        .find('input[name="multiple_images_allowed_state"]')
        .val();

    var removable_btn =
        is_removable == "1"
            ? '<a class="remove-image text-danger"><i class="far fa-trash-alt me-1"></i>Remove</a>'
            : "";

    $(current_selected_image)
        .closest(".form-group")
        .find(".image")
        .removeClass("d-none");
    if (ismultipleAllowed == "1") {
        for (let index = 0; index < result.length; index++) {
            var isPublicDisk = result[index].disk == "public" ? 1 : 0;
            var imagePath = isPublicDisk
                ? media_type != "image"
                    ? appUrl + "assets/admin/images/" + media_type + "-file.png"
                    : encodeURI(
                        appUrl +
                        "storage/" +
                        result[index].sub_directory +
                        "/" +
                        result[index].name
                    )
                : media_type != "image"
                    ? appUrl + "assets/admin/images/" + media_type + "-file.png"
                    : result[index].object_url;

            var inputImgPath = isPublicDisk
                ? encodeURI(
                    result[index].sub_directory + "/" + result[index].name
                )
                : result[index].object_url;

            $(current_selected_image)
                .closest(".form-group")
                .find(".image-upload-section")
                .append(
                    '<div class="bg-white grow image product-image-container rounded shadow text-center m-2"><div class="image-upload-div"><img class="img-fluid mb-2" alt="' +
                    result[index].name +
                    '" title="' +
                    result[index].name +
                    '" src=' +
                    imagePath +
                    ' ><input type="hidden" name=' +
                    input +
                    " value=" +
                    inputImgPath +
                    "></div>" +
                    removable_btn +
                    "</div>"
                );
        }
    } else {
        var isPublicDisk = result[0].disk == "public" ? 1 : 0;
        var imagePath = isPublicDisk
            ? media_type != "image"
                ? appUrl + "assets/admin/images/" + media_type + "-file.png"
                : encodeURI(path)
            : media_type != "image"
                ? appUrl + "assets/admin/images/" + media_type + "-file.png"
                : result[0].object_url;

        var inputImgPath = isPublicDisk
            ? encodeURI(result[0].sub_directory + "/" + result[0].name)
            : result[0].object_url;
        $(current_selected_image)
            .closest(".form-group")
            .find(".image-upload-section")
            .html(
                '<div class="bg-white grow image product-image-container rounded shadow text-center m-2"><div class="image-upload-div"><img class="img-fluid" alt="' +
                result[0].name +
                '" title="' +
                result[0].name +
                '" src=' +
                imagePath +
                ' ><input type="hidden" name=' +
                input +
                " value=" +
                inputImgPath +
                "></div>" +
                removable_btn +
                "</div>"
            );
    }

    current_selected_image = "";
    $("#media-upload-modal").modal("hide");
});

$(document).on("show.bs.modal", "#media-upload-modal", function (event) {
    var triggerElement = $(event.relatedTarget);
    current_selected_image = triggerElement;
    var input = $(current_selected_image).data("input");
    var isremovable = $(current_selected_image).data("isremovable");
    var max_file_allow = $(current_selected_image).data("max_files_allow");

    var ismultipleAllowed = $(current_selected_image).data(
        "is-multiple-uploads-allowed"
    );
    var media_type = $(current_selected_image).is("[data-media_type]")
        ? $(current_selected_image).data("media_type")
        : "image";
    $("#media_type").val(media_type);
    if (ismultipleAllowed == 1) {
        $("#media-upload-table").bootstrapTable("refreshOptions", {
            singleSelect: false,
        });
    } else {
        $("#media-upload-table").bootstrapTable("refreshOptions", {
            singleSelect: true,
        });
    }

    if (max_file_allow === "" || max_file_allow == undefined) {
        max_file_allow = "";
    }

    $(this).find('input[name="current_input"]').val(input);
    $(this).find('input[name="remove_state"]').val(isremovable);
    $(this).find('input[name="max_file_allow"]').val(max_file_allow);
    $(this)
        .find('input[name="multiple_images_allowed_state"]')
        .val(ismultipleAllowed);
});

$(document).on("click", ".remove-image", function (e) {
    e.preventDefault();
    $(this).closest(".image").remove();
});

// copy image path and relative path in media

$(document).on("click", ".copy-to-clipboard", function () {
    var element = $(this).siblings(".path");

    if (element) {
        copyToClipboard(element);
        iziToast.success({
            message: "Text copied to clipboard",
            position: "topRight",
        });
    }
});

function copyToClipboard(element) {
    var textToCopy = $(element).text();
    var temp = $("<input>");
    $("body").append(temp);
    temp.val(textToCopy).select();
    try {
        navigator.clipboard
            .writeText(temp.val())
            .then(function () { })
            .catch(function (err) {
                iziToast.error({
                    message: "Failed to copy text: ",
                    err,
                    position: "topRight",
                });
            });
    } catch (err) {
        fallbackCopyToClipboard(textToCopy);
    }
    temp.remove();
}

// Fallback function for browsers that do not support Clipboard API
function fallbackCopyToClipboard(text) {
    var tempTextArea = document.createElement("textarea");
    tempTextArea.value = text;
    tempTextArea.style.position = "fixed";
    document.body.appendChild(tempTextArea);

    tempTextArea.select();
    var successful = document.execCommand("copy");

    document.body.removeChild(tempTextArea);
}

$(document).on("click", ".copy-relative-path", function () {
    var element = $(this).siblings(".relative-path");
    copyToClipboard(element);
    iziToast.success({
        message: "Image path copied to clipboard",
        position: "topRight",
    });
});

$("input[name='delivery_charge_type']").on("change", function () {
    if (
        $(this).attr("id") == "zipcode_wise_delivery_charge_switch" ||
        $(this).attr("id") == "product_wise_delivery_charge_switch" ||
        $(this).attr("id") == "city_wise_delivery_charge_switch"
    ) {
        $(
            "#delivery_charge_amount_field, #minimum_free_delivery_amount_field"
        ).addClass("d-none");

        if ($(this).attr("id") == "zipcode_wise_delivery_charge_switch") {
            $("input[name='delivery_charge_type_value']").val(
                "zipcode_wise_delivery_charge"
            );
        }
        if ($(this).attr("id") == "city_wise_delivery_charge_switch") {
            $("input[name='delivery_charge_type_value']").val(
                "city_wise_delivery_charge"
            );
        }
        if ($(this).attr("id") == "product_wise_delivery_charge_switch") {
            $("input[name='delivery_charge_type_value']").val(
                "product_wise_delivery_charge"
            );
        }
    } else {
        $(
            "#delivery_charge_amount_field, #minimum_free_delivery_amount_field"
        ).removeClass("d-none");

        $("input[name='delivery_charge_type_value']").val(
            "global_delivery_charge"
        );
    }
});

$("input[name='product_deliverability']").on("change", function () {
    if ($(this).attr("id") == "zipcode_wise_deliverability_switch") {
        $(".city_wise_delivery_charge").addClass("d-none");
        $(".zipcode_wise_delivery_charge").removeClass("d-none");

        $("input[name='product_deliverability_type_value']").val(
            "zipcode_wise_deliverability"
        );
    } else {
        $(".zipcode_wise_delivery_charge").addClass("d-none");
        $(".city_wise_delivery_charge").removeClass("d-none");

        $("input[name='product_deliverability_type_value']").val(
            "city_wise_deliverability"
        );
    }
});

// change status active or deactive in bootstrap table

$(document).on("change", ".change_toggle_status", function () {
    var id = $(this).data("id");
    var status = $(this).val();
    var url = $(this).data("url");
    $.ajax({
        method: "GET",
        url: url,
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
            status: status,
        },
        success: function (response) {
            if (response.error_message) {
                iziToast.error({
                    title: "Error",
                    message: response.error_message,
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            }
            if (response.status_error) {
                iziToast.error({
                    title: "Error",
                    message: response.status_error,
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            }
            if (response.success) {
                iziToast.success({
                    title: "Success",
                    message: "Status Update Successfully",
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            }
        },
        fail: function (response) {
            iziToast.error({
                title: "Error",
                message: "Something Went Wrong!!",
                position: "topRight",
            });
        },
    });
});

// general event for deleet bootstrap table data

$(document).on("click", ".delete-data", function (event) {
    event.preventDefault(); // Prevent the default behavior of the link

    var url = $(this).data("url");

    var subString = "media/destroy";

    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    method: "GET", // Change the method to DELETE
                    url: url,
                    data: {
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (response, textStatus) {
                        if (response.error == false) {
                            Swal.fire("Deleted!", response.message, "success");
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                            $("table").bootstrapTable("refresh");
                            csrfName = response["csrfName"];
                            csrfHash = response["csrfHash"];
                        } else {
                            if (response.error_message) {
                                Swal.fire(
                                    "Error",
                                    response.error_message,
                                    "error"
                                );
                            } else {
                                Swal.fire("Oops...", response.error, "warning");
                            }
                            // Swal.fire("Oops...", response.error, "warning");
                            $("table").bootstrapTable("refresh");
                            csrfName = response["csrfName"];
                            csrfHash = response["csrfHash"];
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                        csrfName = response["csrfName"];
                        csrfHash = response["csrfHash"];
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$(function () {
    promo_code_repeat_usage();
    $("#similar_product").addClass("d-none");
    $(document).on("change", "#repeat_usage", function () {
        promo_code_repeat_usage();
    });

    function promo_code_repeat_usage() {
        var repeat_usage = $("#repeat_usage").val();
        var no_of_repeat_usage = $("#no_of_repeat_usage").val();

        if (
            repeat_usage === "1" ||
            (repeat_usage !== "0" && no_of_repeat_usage !== "")
        ) {
            $("#repeat_usage_html").removeClass("d-none");
        } else {
            $("#repeat_usage_html").addClass("d-none");
        }
    }
});

// delivery boy

$(document).on("change", ".bonus_type", function (e, data) {
    e.preventDefault();
    var bonus_type = $(this).val();
    if (bonus_type == "fixed_amount_per_order_item" && bonus_type != " ") {
        $(".fixed_amount_per_order_item").removeClass("d-none");
    } else {
        $(".fixed_amount_per_order_item").addClass("d-none");
        $(".edit_bonus_amount").val("");
    }
    if (bonus_type == "percentage_per_order_item" && bonus_type != " ") {
        $(".percentage_per_order_item").removeClass("d-none");
    } else {
        $(".percentage_per_order_item").addClass("d-none");
        $(".edit_bonus_percentage").val("");
    }
});

// query params

function seller_wallet_query_params(p) {
    return {
        transaction_type: "wallet",
        user_type: "seller",
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        start_date: p.start_date,
        end_date: p.end_date,
    };
}

function home_query_params(p) {
    return {
        start_date: $("#start_date").val(),
        end_date: $("#end_date").val(),
        order_status: $("#order_status").val(),
        payment_method: $("#payment_method").val(),
        limit: p.limit,
        sort: "oi.id",
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function retun_order_query_params(p) {
    return {
        start_date: $("#start_date").val(),
        end_date: $("#end_date").val(),
        order_status: $("#order_status").val(),
        payment_method: $("#payment_method").val(),
        limit: p.limit,
        sort: "id",
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
function mediaParams(p) {
    return {
        type: $("#media_type").val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function brand_query_params(p) {
    return {
        brand_id: $("#brand_id").val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        status: p.status,
    };
}

function orderTrackingQueryParams(p) {
    return {
        order_id: $('input[name="order_id"]').val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function PromoqueryParams(p) {
    return {
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        status: p.status,
    };
}

function category_query_params(p) {
    return {
        category_id: $("#category_id").val(),
        status: p.status,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function store_query_params(p) {
    return {
        store_id: $("#store_id").val(),
        status: p.status,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function stock_query_params(p) {
    return {
        status: $("#status_filter").val(),
        limit: p.limit,
        offset: p.offset,
        sort: p.sort,
        order: p.order,
        search: p.search,
        category_id: p.category_id,
        seller_id: p.seller_id,
    };
}

function blog_query_params(p) {
    return {
        category_id: p.blogCategoryId,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function cash_collection_query_params(p) {
    return {
        filter_date: $("#filter_date").val(),
        filter_status: p.cashCollectionType,
        filter_d_boy: p.deliveryBoyFilter,
        start_date: p.start_date,
        end_date: p.end_date,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function address_query_params(p) {
    return {
        user_id: $("#address_user_id").val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function transaction_query_params(p) {
    return {
        transaction_type: "transaction",
        user_id: $("#transaction_user_id").val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        start_date: p.start_date,
        end_date: p.end_date,
    };
}

function customer_wallet_query_params(p) {
    return {
        transaction_type: "wallet",
        user_type: "members",
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function orders_query_params(p) {
    return {
        start_date: p.start_date,
        end_date: p.end_date,
        order_status: p.order_status,
        user_id: $("#order_user_id").val(),
        seller_id: p.seller_id,
        payment_method: p.payment_method,
        order_type: p.order_type,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

function queryParams(p) {
    return {
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        order_status: p.order_status,
        active_status: p.order_status,
        start_date: p.start_date,
        end_date: p.end_date,
        payment_method: p.payment_method,
        order_type: p.order_type,
        status: p.status,
        category_id: p.category_id,
        product_type: p.product_type,
        brand_id: p.brand_id,
        payment_request_status: p.payment_request_status,
        productStatus: p.productStatus,
        seller_id: p.seller_id,
        user_id: p.user_id,
    };
}

function sales_report_query_params(p) {
    return {
        start_date: $("#start_date").val(),
        end_date: $("#end_date").val(),
        seller_id: $("#seller_id").val(),
        limit: p.limit,
        sort: "orders.id",
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
function parcel_query_params(p) {
    return {
        order_id: $("#order_id").val(),
        seller_id: $(".seller_id").val(),
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
$(document).on("show.bs.modal", "#customer-address-modal", function (event) {
    var triggerElement = $(event.relatedTarget);
    current_selected_image = triggerElement;
    var id = $(current_selected_image).data("id");
    var existing_url = $(this).find("#customer-address-table").data("url");

    if (existing_url.indexOf("?") > -1) {
        var temp = $(existing_url).text().split("?");
        var new_url = temp[0] + "?user_id=" + id;
    } else {
        var new_url = existing_url + "?user_id=" + id;
    }
    $("#address_user_id").val(id);
    $("#customer-address-table").bootstrapTable("refreshOptions", {
        url: new_url,
    });
});

$(document).on("change", "#seller_filter", function () {
    $("#manage_stock_table").bootstrapTable("refresh");
    $("#pickup_location_table").bootstrapTable("refresh");
});

$(document).on("change", "#category_parent", function () {
    $("#manage_stock_table").bootstrapTable("refresh");
});

// attributes

$(document).on("click", "#add_attribute_value", function (e) {
    e.preventDefault();
    load_attribute_section();
});

function load_attribute_section() {
    var html =
        '<div class="form-group row">' +
        '<div class="col-sm-5">' +
        '<input type="text" step="any" class="form-control" placeholder="Enter Attribute Value" name="attribute_value[]">' +
        "</div>" +
        '<div class="col-sm-5">' +
        '<select class="form-select swatche_type w-100" name="swatche_type[]">' +
        '<option value="0">Default</option>' +
        '<option value="1">Color</option>' +
        '<option value="2">Image</option>' +
        "</select>" +
        '<input type="color" class="form-control color_picker" id="swatche_value" name="swatche_value[]" style="display: none;">' +
        '<div style="display: none;" class="uploadFile img border file_upload_border text-white btn-sm upload_media mt-4" data-input="swatche_value[]" name="attribute_img[]" data-isremovable="0" data-is-multiple-uploads-allowed="0" data-bs-toggle="modal" data-bs-target="#media-upload-modal" value="Upload Photo"><h4><i class="bx bx-upload"></i> Upload</h4></div></div>' +
        '<div class="col-sm-2"> ' +
        '<button class="btn btn-primary btn-sm remove_attribute_section"><i class=" bx bx-window-close"></i></button>' +
        "</div>" +
        '<div class="container-fluid row col-md-12 image-upload-section ms-1">' +
        '<div style="display: none;" class="shadow p-3 mb-5 bg-white rounded m-4 text-center grow">' +
        '<div class="image-upload-div"><img class="img-fluid mb-2 image" src="" alt="Image Not Found"></div>' +
        '<input type="hidden" value="">' +
        "</div>" +
        "</div>" +
        "</div>";
    $("#attribute_section").append(html);
    $(".swatche_type").each(function () {
        $(".swatche_type").select2({
            width: $(".swatche_type").data("width")
                ? $(".swatche_type").data("width")
                : $(".swatche_type").hasClass("w-100")
                    ? "100%"
                    : "style",
            placeholder: $(".swatche_type").data("placeholder"),
            allowClear: Boolean($(".swatche_type").data("allow-clear")),
        });
    });
}

$("#swatche_color").hide();
$("#swatche_image").hide();
$(document.body).on("change", ".swatche_type", function (e) {
    e.preventDefault();
    var swatche_type = $(this).val();
    if (swatche_type == "1") {
        $("#swatche_image").hide();
        $("#swatche_color").show();
        $("#swatche_image").val("");
    } else if (swatche_type == "2") {
        $("#swatche_color").hide();
        $("#swatche_image").show();
        $("#swatche_color").val("");
    } else {
        $("#swatche_color").hide();
        $("#swatche_image").hide();
        $("#swatche_color").val("");
        $("#swatche_image").val("");
    }
});

$(document).on("change", ".swatche_type", function () {
    if ($(this).val() == "1") {
        $(this).siblings(".color_picker").show();
        $(this).siblings(".upload_media").hide();
        $(this).siblings(".grow").hide();
    }
    if ($(this).val() == "2") {
        $(this).siblings(".color_picker").hide();
        $(this).siblings(".color_picker").attr("name", null);
        $(this).siblings(".upload_media").show();
        $(this).siblings(".grow").show();
    }
    if ($(this).val() == "0") {
        $(".color_picker").hide();
        $(".upload_media").hide();
        $(".grow").hide();
    }
});

$(document).on("click", ".remove_attribute_section", function () {
    $(this).closest(".row").remove();
});

$(document).on("change", ".type_event_trigger", function (e, data) {
    e.preventDefault();
    var type_val = $(this).val();
    if (type_val != "default" && type_val != " ") {
        if (type_val == "categories") {
            $(".slider-categories").removeClass("d-none");
            $(".slider-combo-products").addClass("d-none");
            $(".notification-categories").removeClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".notification-products").addClass("d-none");
            $(".slider-url").addClass("d-none");
            $(".offer-url").addClass("d-none");
            $(".notification-url").addClass("d-none");
        } else if (type_val == "products") {
            $(".slider-products").removeClass("d-none");
            $(".notification-products").removeClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".slider-combo-products").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".offer-url").addClass("d-none");
            $(".slider-url").addClass("d-none");
            $(".notification-url").addClass("d-none");
            $(".slider-brand").addClass("d-none");
        } else if (type_val == "combo_products") {
            $(".slider-combo-products").removeClass("d-none");
            $(".notification-products").removeClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".offer-url").addClass("d-none");
            $(".slider-url").addClass("d-none");
            $(".notification-url").addClass("d-none");
            $(".slider-brand").addClass("d-none");
        } else if (type_val == "slider_url") {
            $(".slider-url").removeClass("d-none");
            $(".slider-combo-products").addClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".offer-url").removeClass("d-none");
            $(".notification-products").addClass("d-none");
            $(".notification-url").addClass("d-none");
        } else if (type_val == "offer_url") {
            $(".offer-url").removeClass("d-none");
            $(".slider-combo-products").addClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".notification-products").addClass("d-none");
            $(".notification-url").addClass("d-none");
            $(".slider-brand").addClass("d-none");
        } else if (type_val == "notification_url") {
            $(".notification-url").removeClass("d-none");
            $(".slider-combo-products").addClass("d-none");
            $(".offer-url").addClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".notification-products").addClass("d-none");
        } else if (type_val == "all_products") {
            $(".slider-combo-products").addClass("d-none");
            $(".slider-all-products").removeClass("d-none");
            $(".notification-all-products").removeClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".notification-products").addClass("d-none");
            $(".slider-brand").addClass("d-none");
            $(".offer-url").addClass("d-none");
        } else if (type_val == "brand") {
            $(".slider-combo-products").addClass("d-none");
            $(".slider-all-products").removeClass("d-none");
            $(".notification-all-products").removeClass("d-none");
            $(".slider-categories").addClass("d-none");
            $(".notification-categories").addClass("d-none");
            $(".slider-brand").removeClass("d-none");
            $(".notification-products").removeClass("d-none");
            $(".slider-products").addClass("d-none");
            $(".offer-url").addClass("d-none");
        }
    } else {
        $(".slider-categories").addClass("d-none");
        $(".slider-url").addClass("d-none");
        $(".slider-products").addClass("d-none");
        $(".offer-url").addClass("d-none");
        $(".notification-categories").addClass("d-none");
        $(".notification-products").addClass("d-none");
        $(".notification-url").addClass("d-none");
        $(".slider-brand").addClass("d-none");
    }
});

$(document).on("change", "#send_to", function (e) {
    e.preventDefault();
    var type_val = $(this).val();
    if (type_val == "specific_user") {
        $(".notification-users").removeClass("d-none");
    } else {
        $(".notification-users").addClass("d-none");
    }
});
$(document).on("change", "#send_seller_notification", function (e) {
    e.preventDefault();
    var type_val = $(this).val();
    console.log(type_val);
    if (type_val == "specific_seller") {
        $(".notification-sellers").removeClass("d-none");
    } else {
        $(".notification-sellers").addClass("d-none");
    }
});
$("#image_checkbox").on("click", function () {
    if (this.checked) {
        $(this).prop("checked", true);
        $(".include_image").removeClass("d-none");
    } else {
        $(this).prop("checked", false);
        $(".include_image").addClass("d-none");
    }
});

var noti_user_id = 0;
$("#select_user_id").on("change", function () {
    noti_user_id = $("#select_user_id").val();
});

// search user

$(".search_user").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + "admin/user/search_user",
            type: "GET",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                };
            },
            processResults: function (response) {
                return {
                    results: response,
                };
            },
            cache: true,
        },
        minimumInputLength: 1,
        placeholder: "Search for countries",
    });
});
$(".search_seller").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + "admin/user/search_seller",
            type: "GET",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                };
            },
            processResults: function (response) {
                return {
                    results: response,
                };
            },
            cache: true,
        },
        placeholder: "Search for sellers",
    });
});

//tagify inputs

if ($("#zipcodes").length) {
    var zipcodes_element = document.querySelector("input[name=zipcodes]");
    new Tagify(zipcodes_element);
}

if ($("#custom_options").length) {
    var custom_options_element = document.querySelector("input[name=options]");
    new Tagify(custom_options_element);
}

if ($("#tags").length) {
    var tags_element = document.querySelector("input[name=tags]");
    new Tagify(tags_element);
}

if ($("#attribute_values").length) {
    var tags_element = document.querySelector("input[name='value']");
    new Tagify(tags_element);
}
// general ajax request for get cities and zipcodes

$(".tax_list").select2({
    ajax: {
        url: appUrl + from + "/tax/get_taxes",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },
        cache: true,
    },

    placeholder: "Search for taxes...",
});

$(".delivery_boy_city_list").select2({
    ajax: {
        url: appUrl + "delivery_boy/area/get_cities",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },
        cache: true,
    },

    minimumInputLength: 1,
    placeholder: "Search for cities",
    dropdownParent: $(".city_list_parent"),
});
$(".delivery_boy_search_zipcode").select2({
    ajax: {
        url: appUrl + "delivery_boy/area/get_zipcode",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },
        cache: true,
    },

    minimumInputLength: 1,
    placeholder: "Search for zipcode",
});

// seller assign category comisison

var cat_html = "";
var count_view = 0;
$(document).on("click", "#seller_model", function (e) {
    e.preventDefault();
    cat_html = $("#cat_html").html();

    var cat_ids = $(this).data("cat_ids") + ",";
    var cat_array = cat_ids.split(",");
    cat_array = cat_array.filter(function (v) {
        return v !== "";
    });
    cat_array.sort(function (a, b) {
        return a - b;
    });
    // console.log(cat_array);
    var seller_id = $(this).data("seller_id");

    if (
        cat_ids != "" &&
        cat_ids != "," &&
        cat_ids != "undefined" &&
        seller_id != "" &&
        seller_id != "undefined" &&
        count_view == 0
    ) {
        $.ajax({
            type: "POST",
            data: {
                id: seller_id,
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            url: appUrl + "admin/sellers/get_seller_commission_data",
            dataType: "json",
            success: function (result) {
                // console.log(result);
                if (result.error === "false") {
                    var option_html = $("#cat_html").html();

                    result.data.forEach(function (e, i) {
                        var is_selected =
                            e.id == cat_array[i] && e.seller_id == seller_id
                                ? "selected"
                                : "";
                        // console.log(is_selected);
                        if (is_selected == "") {
                            load_category_section(cat_html);
                        } else {
                            option_html +=
                                '<option value="' +
                                e.category_id +
                                '" ' +
                                is_selected +
                                ">" +
                                e.name +
                                "</option>";

                            load_category_section(
                                "",
                                true,
                                option_html,
                                e.commission
                            );
                        }
                    });
                }
            },
        });

        count_view = 1;
    } else {
        if (count_view == 0) {
            load_category_section(cat_html);
        }
        count_view = 1;
    }
});

$(document).on("click", "#add_category", function (e) {
    e.preventDefault();
    load_category_section(cat_html, false);
});

function load_seller_category() {
    $.ajax({
        type: "GET",
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        url: appUrl + from + "/categories/get_seller_categories_filter",
        dataType: "json",
        success: function (result) {
            var html =
                '<select id="category_id" name="category_id" class="form-select ">' +
                '<option value="">Select category</option>';
            result.forEach(function (e, i) {
                html += '<option value="' + e.id + '">' + e.name + "</option>";
            });
            html += "</select>";
            $(".search_seller_category").html(html);
        },
    });
}
function load_blog_category() {
    $.ajax({
        type: "GET",
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        url: appUrl + "admin/blogs/get_blog_categories",
        dataType: "json",
        success: function (result) {
            var html =
                '<select id="blog_category_id" name="category_id" class="form-select ">' +
                '<option value="">Select category</option>';
            result.forEach(function (e, i) {
                html += '<option value="' + e.id + '">' + e.text + "</option>";
            });
            html += "</select>";
            $(".get_filter_blog_categories").html(html);
        },
    });
}
function load_delievry_boys() {
    $.ajax({
        type: "GET",
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        url: appUrl + "admin/delivery_boys/getDeliveryBoys",
        dataType: "json",
        success: function (result) {
            var html = '<option value="">Select deliveryboy</option>';
            result.data.forEach(function (e, i) {
                html +=
                    '<option value="' + e.id + '">' + e.username + "</option>";
            });
            html += "</select>";
            $(".get_filter_delivery_boy").html(html);
        },
    });
}

function load_category_section(
    cat_html,
    is_edit = false,
    option_html = "",
    commission = 0
) {
    // console.log(option_html);
    if (is_edit == true) {
        var html =
            ' <div class="form-group  row">' +
            '<div class="col-sm-5 category_drop_down">' +
            '<select name="category_id" class="form-select select_multiple w-100 seller_modal_category" data-placeholder=" Select Category">' +
            '<option value="">Select Category </option>' +
            option_html +
            "</select>" +
            "</div>" +
            '<div class="col-sm-5">' +
            '<input type="number" step="any"  min="0" max="100" class="form-control"  placeholder="Enter Commission" name="commission" required value="' +
            commission +
            '">' +
            "</div>" +
            '<div class="col-sm-2"> ' +
            '<button type="button" class="btn btn-tool remove_category_section" > <i class="text-danger bx bx-trash fa-2x "></i> </button>' +
            "</div>" +
            "</div>" +
            "</div>";
    } else {
        var html =
            ' <div class="form-group row">' +
            '<div class="col-sm-5 category_drop_down">' +
            '<select name="category_id" class="form-select select_multiple w-100 test seller_modal_category" data-placeholder="Select Category">' +
            '<option value="">Select Category </option>' +
            cat_html +
            "</select>" +
            "</div>" +
            '<div class="col-sm-5">' +
            '<input type="number" step="any"  min="0" max="100" class="form-control"  placeholder="Enter Commission" name="commission"  value="0">' +
            "</div>" +
            '<div class="col-sm-2"> ' +
            '<button type="button" class="btn btn-tool remove_category_section" > <i class="text-danger bx bx-trash fa-2x "></i> </button>' +
            "</div>" +
            "</div>" +
            "</div>";
    }
    $("#category_section").append(html);
    $(".select_multiple").each(function () {
        $(".select_multiple").select2({
            theme: "bootstrap4",
            width: $(".select_multiple").data("width")
                ? $(".select_multiple").data("width")
                : $(".select_multiple").hasClass("w-100")
                    ? "100%"
                    : "style",
            placeholder: $(".select_multiple").data("placeholder"),
            allowClear: Boolean($(".select_multiple").data("allow-clear")),
            dropdownParent: $("#set_commission_model"),
        });
    });
}

//5.Featured_Section-Module
$(".select_multiple").each(function () {
    $(this).select2({
        theme: "bootstrap4",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        placeholder: $(this).data("placeholder"),
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

$(document).on("click", ".remove_category_section", function () {
    $(this).closest(".row").remove();
});

$("#add-seller-commission-form").on("submit", function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    var object = {};
    formData.forEach((value, key) => {
        if (!Reflect.has(object, key)) {
            object[key] = value;
            return;
        }
        if (!Array.isArray(object[key])) {
            object[key] = [object[key]];
        }
        object[key].push(value);
    });

    // Check if category is selected
    if (
        !object.hasOwnProperty("category_id") ||
        object["category_id"].length === 0
    ) {
        iziToast.error({
            title: "Error",
            message: "Please select at least one category.",
            position: "topRight",
        });
        return;
    }

    var json = JSON.stringify(object);
    // console.log(json);
    $("#cat_data").val(json);

    iziToast.success({
        title: "Success",
        message: "Data Saved Successfully",
        position: "topRight",
    });

    setTimeout(function () {
        $("#set_commission_model").modal("hide");
    }, 2000);
});

// category tree view

var edit_id = $('input[name="category_id"]').val();
var seller_id = $('input[name="seller_id"]').val();
var ignore_status = $.isNumeric(edit_id) && edit_id > 0 ? 1 : 0;

if (
    window.location.href.indexOf("admin/products") !== -1 ||
    window.location.href.indexOf("seller/products") !== -1
) {
    // Check if seller_id is numeric and greater than 0
    if ($.isNumeric(seller_id) && seller_id > 0) {
        get_seller_categories(seller_id, ignore_status, edit_id, from);
    } else {
        if (from !== "seller" && from !== "delivery_boy") {
            $.ajax({
                type: "GET",
                url: appUrl + from + "/categories/getCategories",
                data: {
                    ignore_status: ignore_status,
                },
                dataType: "json",
                success: function (result) {
                    var edit_id = $('input[name="category_id"]').val();
                    $("#product_category_tree_view_html").jstree({
                        plugins: ["checkbox", "themes"],
                        core: {
                            data: result,
                            multiple: false,
                        },
                        checkbox: {
                            three_state: false,
                            cascade: "none",
                        },
                    });
                    $("#product_category_tree_view_html").bind(
                        "ready.jstree",
                        function (e, data) {
                            $(this).jstree(true).select_node(edit_id);
                        }
                    );
                },
            });
        }
    }
}

function get_seller_categories(seller_id, ignore_status, edit_id, from) {
    $.ajax({
        type: "GET",
        url: appUrl + from + "/categories/get_seller_categories",
        data: {
            ignore_status: ignore_status,
            seller_id: seller_id,
        },
        dataType: "json",
        success: function (result) {
            if (
                result.length > 0 &&
                (result[0].deliverable_type == "2" ||
                    result[0].deliverable_type == "3")
            ) {
                $(".all_deliverable_type").addClass("d-none");
            } else {
                $(".all_deliverable_type").removeClass("d-none");
            }

            let categoryDropdown = $("select[name='category_id']");
            categoryDropdown
                .empty()
                .append(`<option value="">Select Category</option>`);

            if (!result || result.length === 0) {
                categoryDropdown.append(
                    `<option value="">No categories available</option>`
                );
                return;
            }
            $.each(result, function (index, category) {
                let categoryName = category.name ?? "";
                let isParentSelected =
                    edit_id && parseInt(category.id) === parseInt(edit_id);

                if (
                    Array.isArray(category.children) &&
                    category.children.length > 0
                ) {
                    // Add parent as normal option (selectable)
                    categoryDropdown.append(
                        `<option value="${category.id}" ${isParentSelected ? "selected" : ""
                        } style="font-weight: bold;">${categoryName}</option>`
                    );

                    // Add child options with indentation
                    $.each(category.children, function (subIndex, subCategory) {
                        let subCategoryName = subCategory.name ?? "";
                        let subSelected =
                            edit_id &&
                                parseInt(subCategory.id) === parseInt(edit_id)
                                ? "selected"
                                : "";
                        categoryDropdown.append(
                            `<option value="${subCategory.id}" ${subSelected}>&nbsp;&nbsp; ${subCategoryName}</option>`
                        );
                    });
                } else {
                    let selected = isParentSelected ? "selected" : "";
                    categoryDropdown.append(
                        `<option value="${category.id}" ${selected} style="font-weight: bold;">${categoryName}</option>`
                    );
                }
            });
        },
        error: function () {
            $("select[name='category_id']")
                .empty()
                .append(`<option value="">Error loading categories</option>`);
        },
    });
}

$(document).on("change", "#seller_id", function (e) {
    e.preventDefault();
    var edit_id = $('input[name="category_id"]').val();
    var seller_id = $(this).val();
    $("#seller_id").val(seller_id);
    var ignore_status = $.isNumeric(edit_id) && edit_id > 0 ? 1 : 0;
    get_seller_pickup_location(seller_id);
    get_seller_categories(seller_id, ignore_status, edit_id, "admin");
});

function get_seller_pickup_location(seller_id) {
    $.ajax({
        type: "GET",
        url: appUrl + "admin/pickup_location/list",
        data: {
            seller_id: seller_id,
            status: 1,
        },
        dataType: "json",
        success: function (result) {
            var html = "";
            html = ' <option value=" ">Select Pickup Location</option>';
            if (result.rows.length > 0) {
                result.rows.forEach((value, key) => {
                    html +=
                        '<option value="' +
                        value.pickup_location +
                        '">' +
                        value.pickup_location +
                        "</option>";
                    $("#pickup_location").html(html);
                });
            }
            $("#pickup_location").html(html);
        },
    });
}

// video type in product

$(document).on("change", "#video_type", function () {
    var video_type = $(this).val();

    if (video_type == "youtube" || video_type == "vimeo") {
        $("#video_link_container").removeClass("d-none");
        $("#video_media_container").addClass("d-none");
    } else if (video_type == "self_hosted") {
        $("#video_link_container").addClass("d-none");
        $("#video_media_container").removeClass("d-none");
    } else {
        $("#video_link_container").addClass("d-none");
        $("#video_media_container").addClass("d-none");
    }
});

// product attributes and variants

// -------------------------------------------------

// pre defined variables for add variants and attributes of product

var attributes_values_selected = [];
var variant_values_selected = [];
var value_check_array = [];
var attributes_selected_variations = [];
var attributes_values = [];
var pre_selected_attr_values = [];
var current_attributes_selected = [];
var current_variants_selected = [];
var attribute_flag = 0;
var pre_selected_attributes_name = [];
var current_selected_image;
var attributes_values = [];
var all_attributes_values = [];
var counter = 0;
var variant_counter = 0;
var variantsCreated = false;

// select 2

$(".select_single , .multiple_values , #product-type, #attribute").each(
    function () {
        $(this).select2({
            width: $(this).data("width")
                ? $(this).data("width")
                : $(this).hasClass("w-100")
                    ? "100%"
                    : "style",
            placeholder: $(this).data("placeholder"),
            allowClear: Boolean($(this).data("allow-clear")),
        });
    }
);
$(document).on("select2:selecting", ".select_single", function (e) {
    if ($.inArray($(this).val(), attributes_values_selected) > -1) {
        //Remove value if further selected
        attributes_values_selected.splice(
            attributes_values_selected.indexOf(
                $(this).select2().find(":selected").val()
            ),
            1
        );
    }
});
$(document).on(
    "select2:selecting",
    ".select_single .variant_attributes",
    function (e) {
        if ($.inArray($(this).val(), variant_values_selected) > -1) {
            //Remove value if further selected
            variant_values_selected.splice(
                variant_values_selected.indexOf(
                    $(this).select2().find(":selected").val()
                ),
                1
            );
        }
    }
);
$("#category_id").on("change", function (e) {
    e.preventDefault();
    $.ajax({
        type: "POST",
        data: {
            category_id: $(this).val(),
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        url: appUrl + from + "/attribute/getAttributes",
        dataType: "json",
        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");

            var html = "";
            html += '<option value=" ">Select Attribute</option>';
            html += '<option value="0">Other</option>';
            $.each(result.data, function (i, e) {
                html +=
                    "<option value=" + e[0].attr_id + " >" + i + "</option>";
            });
            $("#attribute").html(html);
        },
    });
});
$("#attribute").on("change", function (e) {
    e.preventDefault();
    var value = $(this).val();
    if (value == 0 || value == -1) {
        $(".attribute_name").removeClass("d-none");
        $(".attribute").addClass("d-none");
    } else {
        $(".attribute_name").addClass("d-none");
        $(".attribute_name").val("");
    }
});

// type change event

$(document).on("select2:select", "#product-type", function () {
    var value = $(this).val();

    if ($.trim(value) != "") {
        if (value == "simple_product") {
            $("#variant_stock_level").hide(200);
            $("#general_price_section").show(200);
            $(".simple-product-save").show(700);
            $(".product-attributes").addClass("disabled");
            $(".product-variants").addClass("disabled");
            $("#digital_product_setting").hide(200);
            $(".cod_allowed").removeClass("d-none");
            $(".is_returnable").removeClass("d-none");
            $(".is_cancelable").removeClass("d-none");
        }
        if (value == "variable_product") {
            $("#general_price_section").hide(200);
            $(".simple-product-level-stock-management").hide(200);
            $(".simple-product-save").hide(200);
            $(".product-attributes").addClass("disabled");
            $(".product-variants").addClass("disabled");
            $("#variant_stock_level").show();
            $("#digital_product_setting").hide(200);
            $(".cod_allowed").removeClass("d-none");
            $(".is_returnable").removeClass("d-none");
            $(".is_cancelable").removeClass("d-none");
        }
    } else {
        $(".product-attributes").addClass("disabled");
        $(".product-variants").addClass("disabled");
        $("#general_price_section").hide(200);
        $(".simple-product-level-stock-management").hide(200);
        $(".simple-product-save").hide(200);
        $("#variant_stock_level").hide(200);
    }
});
$(function () {
    if ($("#combo_type").val() == "combo_product") {
        $("#variant_stock_level").hide(200);
        $("#general_price_section").show(200);
        $(".simple-product-save").show(700);
        $(".product-attributes").addClass("disabled");
        $(".product-variants").addClass("disabled");
        $(".cod_allowed").removeClass("d-none");
        $(".is_returnable").removeClass("d-none");
        $(".is_cancelable").removeClass("d-none");
    }
});

// if tyep is digital product

$(document).on("click", "#product_type_menu", function () {
    var value = $(this).val();
    if (value === "digital_product") {
        var html = '<option value="digital_product">Digital Product</option>';
        $("#product-type").html(html);
        $("#variant_stock_level").hide(200);
        $("#general_price_section").show(200);
        $(".simple-product-save").hide(200);
        $(".simple-product-level-stock-management").addClass("d-none");
        $(".simple_stock_management").addClass("d-none");
        $(".product-quantities").addClass("d-none");
        $(".delivery-shipping").addClass("d-none");
        $(".product-attributes").addClass("disabled");
        $(".product-variants").addClass("disabled");
        $("#digital_product_setting").show();
        $(".cod_allowed").addClass("d-none");
        $(".is_returnable").addClass("d-none");
        $(".is_cancelable").addClass("d-none");
        $(".indicator").addClass("d-none");
        $(".total_allowed_quantity").addClass("d-none");
        $(".minimum_order_quantity").addClass("d-none");
        $(".guarantee_period").addClass("d-none");
        $(".warranty_period").addClass("d-none");
        $(".quantity_step_size").addClass("d-none");
        $(".deliverable_type").addClass("d-none");
        $(".hsn_code").addClass("d-none");
        $("#product-dimensions").addClass("d-none");
        $(".standdard_shipping").addClass("d-none");
        $(".product_quantity_and_others").addClass("d-none");
        $(".delivery_and_shipping_settings").addClass("d-none");
        $(".combo_product_quantity_and_others").addClass("d-none");
        $(".combo_delivery_and_shipping_setting").addClass("d-none");
        $(".digital_product_in_combo").removeClass("d-none");
        $(".physical_product_in_combo").addClass("d-none");
    } else {
        var html =
            ' <option value=" ">Select Type</option>' +
            '<option value="simple_product">Simple Product</option>' +
            '<option value="variable_product">Variable Product</option>';
        $("#product-type").html(html);
        $(".cod_allowed").removeClass("d-none");
        $(".is_returnable").removeClass("d-none");
        $(".is_cancelable").removeClass("d-none");
        $(".indicator").removeClass("d-none");
        $(".total_allowed_quantity").removeClass("d-none");
        $(".minimum_order_quantity").removeClass("d-none");
        $(".guarantee_period").removeClass("d-none");
        $(".warranty_period").removeClass("d-none");
        $(".quantity_step_size").removeClass("d-none");
        $(".deliverable_type").removeClass("d-none");
        $(".hsn_code").removeClass("d-none");
        $("#product-dimensions").removeClass("d-none");
        $(".standdard_shipping").removeClass("d-none");
        $(".delivery-shipping").removeClass("d-none");
        $("#digital_product_setting").hide();
        $(".product_quantity_and_others").removeClass("d-none");
        $(".delivery_and_shipping_settings").removeClass("d-none");
        $(".combo_product_quantity_and_others").removeClass("d-none");
        $(".combo_delivery_and_shipping_setting").removeClass("d-none");
        $(".digital_product_in_combo").addClass("d-none");
        $(".physical_product_in_combo").removeClass("d-none");
    }
});

// stock management simple product

$(document).on("change", ".simple_stock_management_status", function () {
    if ($(this).prop("checked") == true) {
        $(this).attr("checked", true);
        $(".simple-product-level-stock-management").show(200);
        $(".simple-product-level-stock-management").removeClass("d-none");
    } else {
        $(this).attr("checked", false);
        $(".simple-product-level-stock-management").hide(200);
        $(".simple-product-level-stock-management").find("input").val("");
    }
});

// stock management variable product

$(document).on("change", ".variant-stock-level-type", function () {
    if ($(".variant-stock-level-type").val() == "product_level") {
        $(".variant-product-level-stock-management").show();
    }
    if ($.trim($(".variant-stock-level-type").val()) != "product_level") {
        $(".variant-product-level-stock-management").hide();
    }
});

$(document).on("change", ".variant_stock_status", function () {
    if ($(this).prop("checked") == true) {
        $(this).attr("checked", true);
        $("#stock_level").show(200);
    } else {
        $(this).attr("checked", false);
        $("#stock_level").hide(200);
    }
});

// when update product and change tab to attribute select2 will initialize

$(function () {
    $(".edit-product-attributes").on("shown.bs.tab", function (e) {
        if ($(this).attr("id") === "tab-for-attributes") {
            // Apply select2 initialization code when "Attributes" tab is shown
            $("#attributes_process")
                .last()
                .find(".multiple_values")
                .select2({
                    width: $(this).data("width")
                        ? $(this).data("width")
                        : $(this).hasClass("w-100")
                            ? "100%"
                            : "style",
                    placeholder: $(this).data("placeholder"),
                    allowClear: Boolean($(this).data("allow-clear")),
                });
            $("#attributes_process .collapse").each(function () {
                $(this).removeClass("collapse show").removeAttr("style");
            });
        }
    });
});

$(document).on("click", "#sendDigitalProductMail", function (e) {
    e.preventDefault();
    var order_item_id = $(this).data("id");
    $('input[name="order_item_id"]').val(order_item_id);
});
// save simple product setting

$(document).on("click", ".save-settings", function (e) {
    e.preventDefault();

    if ($(".simple_stock_management_status").is(":checked")) {
        var len = 0;
    } else {
        var len = 1;
    }

    if (
        $(".stock-simple-mustfill-field").filter(function () {
            return this.value === "";
        }).length === len
    ) {
        $(".additional-info").block({
            message: "<h6>Saving Settings</h6>",
            css: {
                border: "3px solid #E7F3FE",
            },
        });

        $('input[name="product_type"]').val($("#product-type").val());
        if ($(".simple_stock_management_status").is(":checked")) {
            $('input[name="simple_product_stock_status"]').val(
                $("#simple_product_stock_status").val()
            );
        } else {
            $('input[name="simple_product_stock_status"]').val("");
        }
        $("#product-type").prop("disabled", true);
        $(".product-attributes").removeClass("disabled");
        $(".product-variants").removeClass("disabled");
        $(".simple_stock_management_status").prop("disabled", true);
        setTimeout(function () {
            $(".additional-info").unblock();
        }, 2000);
    } else {
        iziToast.error({
            message: "Please Fill All Fields",
            position: "topRight",
        });
    }
});

// save variable produt setting

$(document).on("click", ".save-variant-general-settings", function (e) {
    e.preventDefault();
    var $saveButton = $(this);

    if ($(".variant_stock_status").is(":checked")) {
        // Check if the required fields are filled
        if (
            $(".variant-stock-level-type").filter(function () {
                return this.value === "";
            }).length === 0 &&
            $.trim($(".variant-stock-level-type").val()) != ""
        ) {
            if (
                $(".variant-stock-level-type").val() == "product_level" &&
                $(".variant-stock-mustfill-field").filter(function () {
                    return this.value === "";
                }).length !== 0
            ) {
                iziToast.error({
                    message: "Please Fill All The Fields",
                    position: "topRight",
                });
            } else {
                $('input[name="product_type"]').val($("#product-type").val());
                $('input[name="variant_stock_level_type"]').val(
                    $("#stock_level_type").val()
                );
                $('input[name="variant_stock_status"]').val("0");
                $("#product-type").prop("disabled", true);
                $("#stock_level_type").prop("disabled", true);
                $saveButton.removeClass("save-variant-general-settings");
                $(".product-attributes").removeClass("disabled");
                $(".product-variants").removeClass("disabled");
                $(".variant-stock-level-type").prop("readonly", true);
                $("#stock_status_variant_type").attr("readonly", true);
                $(".variant-product-level-stock-management")
                    .find("input,select")
                    .prop("readonly", true);
                $("#tab-for-variations").removeClass("d-none");
                $(".variant_stock_status").prop("disabled", true);
                $('#product-tab a[href="#product-attributes"]').tab("show");
                Swal.fire(
                    "Settings Saved !",
                    "Attributes & Variations Can Be Added Now",
                    "success"
                );
            }
        } else {
            iziToast.error({
                message: "Please Fill All The Fields",
                position: "topRight",
            });
        }
    } else {
        $('input[name="product_type"]').val($("#product-type").val());
        $('#product-tab a[href="#product-attributes"]').tab("show");
        $("#product-type").prop("disabled", true);
        $(".product-attributes").removeClass("disabled");
        $(".product-variants").removeClass("disabled");
        $("#tab-for-variations").removeClass("d-none");
        Swal.fire(
            "Settings Saved !",
            "Attributes & Variations Can Be Added Now",
            "success"
        );
    }
});

// add attribute
$(document).on(
    "click",
    "#add_attributes, #tab-for-variations, .edit-product-attributes",
    function (e) {
        if (e.target.id == "add_attributes") {
            $(".no-attributes-added").hide();
            $(".save_attributes").removeClass("d-none");
            $("#create_attributes").removeClass("d-none");
            counter++;
            var $attribute = $("#attributes_values_json_data").find(
                ".select_single"
            );
            var $options = $($attribute).clone().html();

            var attr_name = "pro_attr_" + counter;

            if ($("#product-type").val() == "simple_product") {
                var html =
                    '<div class="form-group move row my-auto p-2 rounded bg-gray-light product-attr-selectbox" id=' +
                    attr_name +
                    '><div class="col-md-5 col-sm-12"> <select name="attribute_id[]" class="attributes select_single" data-placeholder=" Type to search and select attributes"><option value="">Select attribute</option>' +
                    $options +
                    '</select></div><div class="col-md-5 col-sm-12 "> <select name="attribute_value_ids[]" class="multiple_values" multiple="" data-placeholder=" Type to search and select attributes values"><option value=""></option> </select></div><div class="col-md-1 col-sm-6 text-center py-1 align-self-center"> <button type="button" class="btn btn-tool remove_attributes"> <i class="text-danger bx bx-trash fa-2x "></i> </button></div></div>';
            } else {
                $("#note").removeClass("d-none");
                var html =
                    '<div class="form-group row move my-auto p-2 rounded bg-gray-light product-attr-selectbox" id=' +
                    attr_name +
                    ">" +
                    '<div class="col-md-5 col-sm-12">' +
                    '<select name="attribute_id[]" class="attributes select_single" data-placeholder=" Type to search and select attributes">' +
                    $options +
                    "</select>" +
                    "</div>" +
                    '<div class="col-md-5 col-sm-12 ">' +
                    '<select name="attribute_value_ids[]" class="multiple_values"  multiple="" data-placeholder=" Type to search and select attributes values">' +
                    '<option value=""></option> ' +
                    "</select>" +
                    "</div>" +
                    '<div class="col-md-1 col-sm-6 text-center py-1 align-self-center"><input type="checkbox" name="variations[]" class="is_attribute_checked custom-checkbox form-check-input">' +
                    "</div>" +
                    '<div class="col-md-1 col-sm-6 text-center py-1 align-self-center "> ' +
                    '<button type="button" class="btn btn-tool remove_attributes"> ' +
                    '<i class="bx bx-trash">' +
                    "</i> " +
                    "</button>" +
                    "</div>" +
                    "</div>";
            }
            $("#attributes_process").append(html);
            $("#attributes_process")
                .last()
                .find(".attributes")
                .select2({
                    width: $(this).data("width")
                        ? $(this).data("width")
                        : $(this).hasClass("w-100")
                            ? "100%"
                            : "style",
                    placeholder: $(this).data("placeholder"),
                    allowClear: Boolean($(this).data("allow-clear")),
                });

            $("#attributes_process")
                .last()
                .find(".multiple_values")
                .select2({
                    width: $(this).data("width")
                        ? $(this).data("width")
                        : $(this).hasClass("w-100")
                            ? "100%"
                            : "style",
                    placeholder: $(this).data("placeholder"),
                    allowClear: Boolean($(this).data("allow-clear")),
                });
        }
        if (e.target.id == "tab-for-variations") {
            $(".additional-info").block({
                message: "<h6>Loading Variations</h6>",
                css: {
                    border: "3px solid #E7F3FE",
                },
            });
            if (attributes_values.length > 0) {
                $(".no-variants-added").hide();
                create_variants(false, from);
            }
            setTimeout(function () {
                $(".additional-info").unblock();
            }, 3000);
        }
    }
);

$(".multiple_values").select2({
    width: $(this).data("width")
        ? $(this).data("width")
        : $(this).hasClass("w-100")
            ? "100%"
            : "style",
    placeholder: $(this).data("placeholder"),
    allowClear: Boolean($(this).data("allow-clear")),
});

$(document).on("select2:select", ".select_single", function (e) {
    var select = $(this)
        .closest(".row")
        .find(".multiple_values")
        .text(null)
        .trigger("change");

    select.empty();
    var data = $(this).select2().find(":selected").data("values");

    if (data !== null && data !== undefined) {
        if (typeof data === "string") {
            data = JSON.parse(data);
        }

        data.forEach((d) => {
            if (d.text !== undefined) {
                d.text = d.text;
            } else {
                d.text = d.value;
            }
            $(select).append(`<option value="${d.id}">${d.text}</option>`);
        });
    }
});

// remove attributes and variants

$(document).on("click", ".remove_attributes , .remove_variants", function (e) {
    Swal.fire({
        title: "Are you sure want to delete!",
        text: "You won't be able to revert this after update!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085D6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes",
    }).then((result) => {
        if (result.value) {
            var text = this.className;
            if (text.search("remove_attributes") != -1) {
                var edit_id = $("#edit_product_id").val();

                attributes_values_selected.splice(
                    attributes_values_selected.indexOf(
                        $(this).select2().find(":selected").val()
                    ),
                    1
                );
                $(this).closest(".row").remove();
                counter -= 1;
                var numItems = $(".product-attr-selectbox").length;
                if (numItems == 0) {
                    $(".no-attributes-added").show();
                    $(".save_attributes").addClass("d-none");
                    $("#note").addClass("d-none");
                }
            }
            if (text.search("remove_variants") != -1) {
                variant_values_selected.splice(
                    variant_values_selected.indexOf(
                        $(this).select2().find(":selected").val()
                    ),
                    1
                );
                $(this).closest(".form-group").remove();
                variant_counter -= 1;
                var numItems = $(".product-variant-selectbox").length;
                if (numItems == 0) {
                    $(".no-variants-added").show();
                }
            }
        }
    });
});
// save attributes

$(document).on("click", ".save_attributes", function () {
    Swal.fire({
        title: "Are you sure want to save changes!",
        text: "Do not save attributes if you made no changes! It will reset the variants if there are no changes in attributes or its values !",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085D6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes",
    }).then((result) => {
        if (result.value) {
            attribute_flag = 1;
            save_attributes();

            create_fetched_variants_html(true, from);

            iziToast.success({
                message: "Attributes Saved Successfully",
                position: "topRight",
            });
        }
    });
});

// save attributes function

function save_attributes() {
    attributes_values = [];
    all_attributes_values = [];
    var tmp = $(".product-attr-selectbox");
    $.each(tmp, function (index) {
        var data = $(tmp[index])
            .closest(".row")
            .find(".multiple_values")
            .select2("data");
        var tmp_values = [];
        for (var i = 0; i < data.length; i++) {
            if (!$.isEmptyObject(data[i])) {
                tmp_values[i] = data[i].id;
            }
        }
        if (!$.isEmptyObject(data)) {
            all_attributes_values.push(tmp_values);
        }
        if ($(tmp[index]).find(".is_attribute_checked").is(":checked")) {
            if (!$.isEmptyObject(data)) {
                attributes_values.push(tmp_values);
            }
        }
    });
}

// Permutation formula

function containsAll(needles, haystack) {
    for (var i = 0; i < needles.length; i++) {
        if ($.inArray(needles[i], haystack) == -1) return false;
    }
    return true;
}

function getPermutation(args) {
    var r = [],
        max = args.length - 1;

    function helper(arr, i) {
        for (var j = 0, l = args[i].length; j < l; j++) {
            var a = arr.slice(0); // clone arr
            a.push(args[i][j]);
            if (i == max) r.push(a);
            else helper(a, i + 1);
        }
    }
    helper([], 0);
    return r;
}

// fetch variants

function create_fetched_variants_html(
    add_newly_created_variants = false,
    from
) {
    var newArr1 = [];
    for (var i = 0; i < pre_selected_attr_values.length; i++) {
        var temp = newArr1.concat(pre_selected_attr_values[i]);
        newArr1 = [...new Set(temp)];
    }
    var newArr2 = [];
    for (var i = 0; i < attributes_values.length; i++) {
        newArr2 = newArr2.concat(attributes_values[i]);
    }

    current_attributes_selected = $.grep(newArr2, function (x) {
        return $.inArray(x, newArr1) < 0;
    });

    if (containsAll(newArr1, newArr2)) {
        var temp = [];
        if (!$.isEmptyObject(current_attributes_selected)) {
            $.ajax({
                type: "GET",
                url: appUrl + from + "/products/fetch_attribute_values_by_id",
                data: {
                    id: current_attributes_selected,
                },
                dataType: "json",
                success: function (result) {
                    temp = result;
                    $.each(result, function (key, value) {
                        if (
                            pre_selected_attributes_name.indexOf(
                                $.trim(value.name)
                            ) > -1
                        ) {
                            delete temp[key];
                        }
                    });
                    var resetArr = temp.filter(function () {
                        return true;
                    });
                    setTimeout(function () {
                        var edit_id = $('input[name="edit_product_id"]').val();

                        get_variants(edit_id, from).done(function (data) {
                            create_editable_variants(
                                data.result,
                                resetArr,
                                add_newly_created_variants
                            );
                        });
                    }, 1000);
                },
            });
        } else {
            if (attribute_flag == 0) {
                var edit_id = $('input[name="edit_product_id"]').val();

                get_variants(edit_id, from).done(function (data) {
                    create_editable_variants(
                        data.result,
                        false,
                        add_newly_created_variants
                    );
                });
            }
        }
    } else {
        var edit_id = $('input[name="edit_product_id"]').val();

        get_variants(edit_id, from).done(function (data) {
            create_editable_variants(
                data.result,
                false,
                add_newly_created_variants
            );
        });
    }
}

// get variants function

function get_variants(edit_id, from) {
    var from = "admin";

    if (
        window.location.href.indexOf("seller/") > -1 &&
        window.location.href.indexOf("admin/") == -1
    ) {
        from = "seller";
    }

    return $.ajax({
        type: "GET",
        url: appUrl + from + "/products/fetch_variants_values_by_pid",
        data: {
            edit_id: edit_id,
        },
        dataType: "json",
    }).done(function (data) {
        return data.responseCode != 200 ? $.Deferred().reject(data) : data;
    });
}

// create ediatable variants

function create_editable_variants(
    data,
    newly_selected_attr = false,
    add_newly_created_variants = false
) {
    if (data.length > 0 && data[0].variant_ids) {
        $("#reset_variants").show();
        var html = "";

        if (
            !$.isEmptyObject(attributes_values) &&
            add_newly_created_variants == true
        ) {
            var permuted_value_result = getPermutation(attributes_values);
        }
        html +=
            '<div ondragstart="return false;" class="d-flex justify-content-end"><button type="button" class="btn btn-primary btn-sm mb-3"  id="expand_all">Expand All</button>' +
            '<button type="button" class="btn btn-primary btn-sm mb-3 ml-4 ms-3" id="collapse_all">Collapse All</button></div>';
        $.each(data, function (a, b) {
            // console.log(b);
            if (
                !$.isEmptyObject(permuted_value_result) &&
                add_newly_created_variants == true
            ) {
                var permuted_value_result_temp = permuted_value_result;
                var varinat_ids = b.variant_ids.split(",");
                $.each(permuted_value_result_temp, function (index, value) {
                    if (containsAll(varinat_ids, value)) {
                        permuted_value_result.splice(index, 1);
                    }
                });
            }

            variant_counter++;
            var attr_name = "pro_attr_" + variant_counter;
            html +=
                '<div class="form-group move p-2 pe-0 product-variant-selectbox ps-0 pt-3 rounded row">';
            html +=
                '<input type="hidden" name="edit_variant_id[]" value=' +
                b.id +
                ">";
            var tmp_variant_value_id = "";
            var varaint_array = [];
            var varaint_ids_temp_array = [];
            var flag = 0;
            var variant_images = "";
            var image_html = "";
            if (b.images) {
                // variant_images = JSON.parse(b.images);
                variant_images = Array.isArray(b.images) ? b.images : [];
                // console.log(variant_images);
            }

            $.each(b.variant_ids.split(","), function (key) {
                varaint_ids_temp_array[key] = $.trim(this);
            });

            $.each(b.variant_values.split(","), function (key) {
                varaint_array[key] = $.trim(this);
            });
            if (variant_images) {
                $.each(variant_images, function (img_key, img_value) {
                    image_html +=
                        '<div class="col-md-3 col-sm-12 shadow bg-white rounded m-3 p-3 text-center grow product-image-container"><div class="image-upload-div"><img src=' +
                        img_value +
                        ' alt="Image Not Found"></div> <a href="javascript:void(0)" class="delete-img" data-id="' +
                        b.id +
                        '" data-field="images" data-img=' +
                        img_value +
                        ' data-table="product_variants" data-path=' +
                        img_value +
                        ' data-isjson="true"> <span class="btn btn-block bg-gradient-danger text-danger btn-xs"><i class="far fa-trash-alt me-1"></i> Delete</span></a> <input type="hidden" name="variant_images[' +
                        a +
                        '][]"  value=' +
                        img_value +
                        "></div>";
                });
            }
            for (var i = 0; i < varaint_array.length; i++) {
                html +=
                    '<div class="col-md-5 variant_col"> <input type="hidden"  value="' +
                    varaint_ids_temp_array[i] +
                    '"><input type="text" class="col form-control" value="' +
                    varaint_array[i] +
                    '" readonly></div>';
            }
            if (
                newly_selected_attr != false &&
                newly_selected_attr.length > 0
            ) {
                for (var i = 0; i < newly_selected_attr.length; i++) {
                    var tempVariantsIds = [];
                    var tempVariantsValues = [];
                    $.each(
                        newly_selected_attr[i].attribute_values_id.split(","),
                        function () {
                            tempVariantsIds.push($.trim(this));
                        }
                    );
                    html +=
                        '<div class="col-md-2"><select class="col new-added-variant form-control" ><option value="">Select Attribute</option>';
                    $.each(
                        newly_selected_attr[i].attribute_values.split(","),
                        function (key) {
                            tempVariantsValues.push($.trim(this));
                            html +=
                                '<option value="' +
                                tempVariantsIds[key] +
                                '">' +
                                tempVariantsValues[key] +
                                "</option>";
                        }
                    );
                    html += "</select></div>";
                }
            }

            html +=
                '<input type="hidden" name="variants_ids[]" value="' +
                b.attribute_value_ids +
                '"><div class="col-md-1"> <button type="button" data-bs-toggle="collapse" class="btn btn-tool product-variant-expand-btn" data-bs-target="#' +
                attr_name +
                '" aria-expanded="true"><i class="bx bx-chevron-down-circle"></i> </button></div><div class="col-md-1"> <button type="button" class="btn btn-tool remove_variants"> <i class="bx bx-trash"></i> </button></div><div class="col-12" id="variant_stock_management_html"><div id=' +
                attr_name +
                ' style="" class="collapse">';

            if (
                $(".variant_stock_status").is(":checked") &&
                $(".variant-stock-level-type").val() == "variable_level"
            ) {
                var selected = b.availability == "0" ? "selected" : " ";
                html +=
                    '<div class="row mt-5">' +
                    '<div class="col-md-6">' +
                    "<ul>" +
                    "<li><h6>Price Info</h6></li>" +
                    "</ul>" +
                    '<div class="col-md-12">' +
                    '<div class="form-group">' +
                    '<label for="simple_price" min="0.01" class="col-md-6 form-label">Price: <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="number" name="variant_price[]" class="col form-control price varaint-must-fill-field" min="0.01" step="0.01" value="' +
                    b.price +
                    '">' +
                    "</div>" +
                    "</div>" +
                    '<div class="col-md-12">' +
                    '<div class="form-group">' +
                    '<label for="type" class="col-md-6 form-label">Special Price: <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="number" name="variant_special_price[]" class="col form-control discounted_price" min="0" step="0.01" value="' +
                    b.special_price +
                    '">' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="col-md-6">' +
                    '<div class="dimensions" id="product-dimensions">' +
                    "<ul>" +
                    "<li><h6>Standard shipping weightage</h6></li>" +
                    "</ul>" +
                    '<div class="form-group row">' +
                    '<div class="col-6">' +
                    '<label for="weight" class="form-label col-md-12">Weight <small>(kg)</small> <span class="text-danger text-xs">*</span></label>' +
                    '<input type="number" class="form-control" name="weight[]" placeholder="Weight" id="weight" value="' +
                    b.weight +
                    '" step="0.01">' +
                    "</div>" +
                    '<div class="col-6">' +
                    '<label for="height" class="form-label col-md-12">Height <small>(cms)</small></label>' +
                    '<input type="number" class="form-control" name="height[]" placeholder="Height" id="height" value="' +
                    b.height +
                    '" step="0.01">' +
                    "</div>" +
                    "</div>" +
                    '<div class="form-group row">' +
                    '<div class="col-6">' +
                    '<label for="breadth" class="form-label col-md-12">Breadth <small>(cms)</small> </label>' +
                    '<input type="number" class="form-control" name="breadth[]" placeholder="Breadth" id="breadth" value="' +
                    b.breadth +
                    '" step="0.01">' +
                    "</div>" +
                    '<div class="col-6">' +
                    '<label for="length" class="form-label col-md-12">Length <small>(cms)</small> </label>' +
                    '<input type="number" class="form-control" name="length[]" placeholder="Length" id="length" value="' +
                    b.length +
                    '" step="0.01">' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="col-md-12">' +
                    "<ul>" +
                    "<li>" +
                    "<h6>Stock Management</h6>" +
                    "</li>" +
                    "</ul>" +
                    '<div class="form-group row">' +
                    '<div class="col-3">' +
                    '<label for="variant_sku" class="form-label col-md-12">SKU <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="text" name="variant_sku[]" class="col form-control varaint-must-fill-field" value="' +
                    b.sku +
                    '">' +
                    "</div>" +
                    '<div class="col-3">' +
                    '<label for="variant_total_stock" class="form-label col-md-12">Total Stock <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="number" min="1" name="variant_total_stock[]" class="col form-control varaint-must-fill-field" value="' +
                    b.stock +
                    '">' +
                    "</div>" +
                    '<div class="col-3">' +
                    '<label for="variant_level_stock_status" class="form-label col-md-12">Stock Status</label>' +
                    '<select type="text" name="variant_level_stock_status[]" class="col form-control varaint-must-fill-field">' +
                    '<option value="1" ' +
                    selected +
                    ">In Stock</option>" +
                    '<option value="0" ' +
                    selected +
                    ">Out Of Stock</option>" +
                    "</select>" +
                    "</div>" +
                    "</div>" +
                    "</div>";
            } else {
                var selected = b.availability == "0" ? "selected" : " ";
                html +=
                    '<div class="row mt-5">' +
                    '<div class="col-md-6">' +
                    "<ul>" +
                    "<li><h6>Price Info</h6></li>" +
                    "</ul>" +
                    '<div class="col-md-12">' +
                    '<div class="form-group">' +
                    '<label for="simple_price" min="0.01" class="col-md-6 form-label">Price: <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="number" name="variant_price[]" class="col form-control price varaint-must-fill-field" min="0.01" step="0.01" value="' +
                    b.price +
                    '">' +
                    "</div>" +
                    "</div>" +
                    '<div class="col-md-12">' +
                    '<div class="form-group">' +
                    '<label for="type" class="col-md-6 form-label">Special Price: <span class="text-asterisks text-sm">*</span></label>' +
                    '<input type="number" name="variant_special_price[]" class="col form-control discounted_price" min="0" step="0.01" value="' +
                    b.special_price +
                    '">' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="col-md-6">' +
                    '<div class="dimensions" id="product-dimensions">' +
                    "<ul>" +
                    "<li><h6>Standard shipping weightage</h6></li>" +
                    "</ul>" +
                    '<div class="form-group row">' +
                    '<div class="col-6">' +
                    '<label for="weight" class="form-label col-md-12">Weight <small>(kg)</small> <span class="text-danger text-xs">*</span></label>' +
                    '<input type="number" class="form-control" name="weight[]" placeholder="Weight" id="weight" value="' +
                    b.weight +
                    '" step="0.01">' +
                    "</div>" +
                    '<div class="col-6">' +
                    '<label for="height" class="form-label col-md-12">Height <small>(cms)</small></label>' +
                    '<input type="number" class="form-control" name="height[]" placeholder="Height" id="height" value="' +
                    b.height +
                    '" step="0.01">' +
                    "</div>" +
                    "</div>" +
                    '<div class="form-group row">' +
                    '<div class="col-6">' +
                    '<label for="breadth" class="form-label col-md-12">Breadth <small>(cms)</small> </label>' +
                    '<input type="number" class="form-control" name="breadth[]" placeholder="Breadth" id="breadth" value="' +
                    b.breadth +
                    '" step="0.01">' +
                    "</div>" +
                    '<div class="col-6">' +
                    '<label for="length" class="form-label col-md-12">Length <small>(cms)</small> </label>' +
                    '<input type="number" class="form-control" name="length[]" placeholder="Length" id="length" value="' +
                    b.length +
                    '" step="0.01">' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>";
            }

            html +=
                '<div class="col-md-6 file_upload_box border file_upload_border mt-2">' +
                '<div class="mt-2">' +
                '<div class="col-md-12 text-center">' +
                "<div>" +
                '<a class="media_link uploadFile img btn text-white " data-input="variant_images[' +
                a +
                '][]" data-isremovable="1" data-is-multiple-uploads-allowed="1" data-bs-toggle="modal" data-bs-target="#media-upload-modal" value="Upload Photo">' +
                '<h4><i class="bx bx-upload"></i> Upload</h4>' +
                "</a>" +
                '<p class="image_recommendation">Recommended Size : larger than 400 x 260 & smaller than 600 x 300 pixels.</p>' +
                "</div>" +
                "</div>" +
                "</div>" +
                "</div>" +
                '<div class=" container-fluid row image-upload-section ms-1">' +
                image_html +
                "</div>";

            html += "</div></div></div>";

            $("#variants_process").html(html);
        });

        if (
            !$.isEmptyObject(attributes_values) &&
            add_newly_created_variants == true
        ) {
            create_variants(permuted_value_result, from);
        }
    }
}

function create_variants(preproccessed_permutation_result = false, from) {
    var html = "";
    var is_appendable = false;
    var permutated_attribute_value = [];
    if (preproccessed_permutation_result != false) {
        var response = preproccessed_permutation_result;
        is_appendable = true;
    } else {
        var response = getPermutation(attributes_values);
    }
    var selected_variant_ids = JSON.stringify(response);
    var selected_attributes_values = JSON.stringify(attributes_values);

    $(".no-variants-added").hide();
    $.ajax({
        type: "GET",
        url: appUrl + from + "/products/get_variants_by_id",
        data: {
            variant_ids: selected_variant_ids,
            attributes_values: selected_attributes_values,
        },
        dataType: "json",
        success: function (data) {
            var result = data["result"];
            html +=
                '<div ondragstart="return false;" class="d-flex justify-content-end"><button type="button" class="btn btn-primary btn-sm mb-3"  id="expand_all">Expand All</button>' +
                '<button type="button" class="btn btn-primary btn-sm mb-3 ml-4 ms-3" id="collapse_all">Collapse All</button></div>';
            $.each(result, function (a, b) {
                variant_counter++;
                var attr_name = "pro_attr_" + variant_counter;
                html +=
                    '<div class="form-group move p-2 pe-0 product-variant-selectbox ps-0 pt-3 rounded row">';
                var tmp_variant_value_id = " ";
                $.each(b, function (key, value) {
                    tmp_variant_value_id =
                        tmp_variant_value_id + " " + value.id;
                    html +=
                        '<div class="col-md-5"> <input type="text" class="col form-control" value="' +
                        value.value +
                        '" readonly></div>';
                });
                html +=
                    '<input type="hidden" name="variants_ids[]" value="' +
                    tmp_variant_value_id +
                    '"><div class="col-md-1"> <button type="button" data-bs-toggle="collapse" class="btn btn-tool product-variant-expand-btn" data-bs-target="#' +
                    attr_name +
                    '" aria-expanded="true"><i class="bx bx-chevron-down-circle"></i> </button></div><div class="col-md-1"> <button type="button" class="btn btn-tool remove_variants"> <i class="bx bx-trash"></i> </button></div><div class="col-12" id="variant_stock_management_html"><div id=' +
                    attr_name +
                    ' style="" class="collapse">';
                if (
                    $(".variant_stock_status").is(":checked") &&
                    $(".variant-stock-level-type").val() == "variable_level"
                ) {
                    html +=
                        '<div class="row mt-5">' +
                        '<div class="col-md-6">' +
                        "<ul>" +
                        "<li><h6>Price Info</h6></li>" +
                        "</ul>" +
                        '<div class="col-md-12">' +
                        '<div class="form-group">' +
                        '<label for="simple_price" min="0.01" class="col-md-6 form-label">Price: <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="number" name="variant_price[]" class="col form-control price varaint-must-fill-field" min="0.01" step="0.01" value="">' +
                        "</div>" +
                        "</div>" +
                        '<div class="col-md-12">' +
                        '<div class="form-group">' +
                        '<label for="type" class="col-md-6 form-label">Special Price: <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="number" name="variant_special_price[]" class="col form-control discounted_price" min="0" step="0.01" value="">' +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        '<div class="col-md-6">' +
                        '<div class="dimensions" id="product-dimensions">' +
                        "<ul>" +
                        "<li><h6>Standard shipping weightage</h6></li>" +
                        "</ul>" +
                        '<div class="form-group row">' +
                        '<div class="col-6">' +
                        '<label for="weight" class="form-label col-md-12">Weight <small>(kg)</small> <span class="text-danger text-xs">*</span></label>' +
                        '<input type="number" class="form-control" name="weight[]" placeholder="Weight" id="weight" value="0" step="0.01">' +
                        "</div>" +
                        '<div class="col-6">' +
                        '<label for="height" class="form-label col-md-12">Height <small>(cms)</small></label>' +
                        '<input type="number" class="form-control" name="height[]" placeholder="Height" id="height" value="0" step="0.01">' +
                        "</div>" +
                        "</div>" +
                        '<div class="form-group row">' +
                        '<div class="col-6">' +
                        '<label for="breadth" class="form-label col-md-12">Breadth <small>(cms)</small> </label>' +
                        '<input type="number" class="form-control" name="breadth[]" placeholder="Breadth" id="breadth" value="0" step="0.01">' +
                        "</div>" +
                        '<div class="col-6">' +
                        '<label for="length" class="form-label col-md-12">Length <small>(cms)</small> </label>' +
                        '<input type="number" class="form-control" name="length[]" placeholder="Length" id="length" value="0" step="0.01">' +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        '<div class="col-md-12">' +
                        "<ul>" +
                        "<li>" +
                        "<h6>Stock Management</h6>" +
                        "</li>" +
                        "</ul>" +
                        '<div class="form-group row">' +
                        '<div class="col-3">' +
                        '<label for="variant_sku" class="form-label col-md-12">SKU <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="text" name="variant_sku[]" class="col form-control varaint-must-fill-field">' +
                        "</div>" +
                        '<div class="col-3">' +
                        '<label for="variant_total_stock" class="form-label col-md-12">Total Stock <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="number" min="1" name="variant_total_stock[]" class="col form-control varaint-must-fill-field">' +
                        "</div>" +
                        '<div class="col-3">' +
                        '<label for="variant_level_stock_status" class="form-label col-md-12">Stock Status</label>' +
                        '<select type="text" name="variant_level_stock_status[]" class="col form-control varaint-must-fill-field">' +
                        '<option value="1">In Stock</option>' +
                        '<option value="0">Out Of Stock</option>' +
                        "</select>" +
                        "</div>" +
                        "</div>" +
                        "</div>";
                } else {
                    html +=
                        '<div class="row mt-5">' +
                        '<div class="col-md-6">' +
                        "<ul>" +
                        "<li><h6>Price Info</h6></li>" +
                        "</ul>" +
                        '<div class="col-md-12">' +
                        '<div class="form-group">' +
                        '<label for="simple_price" min="0.01" class="col-md-6 form-label">Price: <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="number" name="variant_price[]" class="col form-control price varaint-must-fill-field" min="0.01" step="0.01" value="">' +
                        "</div>" +
                        "</div>" +
                        '<div class="col-md-12">' +
                        '<div class="form-group">' +
                        '<label for="type" class="col-md-6 form-label">Special Price: <span class="text-asterisks text-sm">*</span></label>' +
                        '<input type="number" name="variant_special_price[]" class="col form-control discounted_price" min="0" step="0.01" value="">' +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        '<div class="col-md-6">' +
                        '<div class="dimensions" id="product-dimensions">' +
                        "<ul>" +
                        "<li><h6>Standard shipping weightage</h6></li>" +
                        "</ul>" +
                        '<div class="form-group row">' +
                        '<div class="col-6">' +
                        '<label for="weight" class="form-label col-md-12">Weight <small>(kg)</small> <span class="text-danger text-xs">*</span></label>' +
                        '<input type="number" class="form-control" name="weight[]" placeholder="Weight" id="weight" value="0" step="0.01">' +
                        "</div>" +
                        '<div class="col-6">' +
                        '<label for="height" class="form-label col-md-12">Height <small>(cms)</small></label>' +
                        '<input type="number" class="form-control" name="height[]" placeholder="Height" id="height" value="0" step="0.01">' +
                        "</div>" +
                        "</div>" +
                        '<div class="form-group row">' +
                        '<div class="col-6">' +
                        '<label for="breadth" class="form-label col-md-12">Breadth <small>(cms)</small> </label>' +
                        '<input type="number" class="form-control" name="breadth[]" placeholder="Breadth" id="breadth" value="0" step="0.01">' +
                        "</div>" +
                        '<div class="col-6">' +
                        '<label for="length" class="form-label col-md-12">Length <small>(cms)</small> </label>' +
                        '<input type="number" class="form-control" name="length[]" placeholder="Length" id="length" value="0" step="0.01">' +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        "</div>" +
                        "</div>";
                }

                html +=
                    '<div class="col-md-6 file_upload_box border file_upload_border mt-2">' +
                    '<div class="mt-2">' +
                    '<div class="col-md-12 text-center">' +
                    "<div>" +
                    '<a class="media_link uploadFile img btn text-white" data-input="variant_images[' +
                    a +
                    '][]" data-isremovable="1" data-is-multiple-uploads-allowed="1" data-bs-toggle="modal" data-bs-target="#media-upload-modal" value="Upload Photo">' +
                    '<h4><i class="bx bx-upload"></i> Upload</h4>' +
                    "</a>" +
                    '<p class="image_recommendation">Recommended Size : larger than 400 x 260 & smaller than 600 x 300 pixels.</p>' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class=" container-fluid row image-upload-section ms-1">' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>";

                html += "</div></div></div></div></div>";
            });
            if (is_appendable == false) {
                $("#variants_process").html(html);
            } else {
                $("#variants_process").append(html);
            }
            $("#variants_process").unblock();
        },
    });
}

// reset variants

$(document).on("click", "#reset_variants", function () {
    Swal.fire({
        title: "Are You Sure To Reset!",
        text: "You won't be able to revert this after update!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, Reset it!",
        showLoaderOnConfirm: true,
        allowOutsideClick: false,
    }).then((result) => {
        if (result.value) {
            $(".additional-info").block({
                message: "<h6>Reseting Variations</h6>",
                css: {
                    border: "3px solid #E7F3FE",
                },
            });
            if (attributes_values.length > 0) {
                $(".no-variants-added").hide();
                create_variants(false, from);
            }
            setTimeout(function () {
                $(".additional-info").unblock();
            }, 2000);
        }
    });
});

$(document).on("click", "#expand_all", function () {
    $(".product-variant-selectbox .collapse").addClass("show");
    $(".product-variant-selectbox .btn.btn-tool.text-primary").attr(
        "aria-expanded",
        "true"
    );
});

$(document).on("click", "#collapse_all", function () {
    $(".product-variant-selectbox .collapse").removeClass("show");
    $(".product-variant-selectbox .btn.btn-tool.text-primary").attr(
        "aria-expanded",
        "false"
    );
});

//expand and collapse all variants

$(document).on("click", "#expand_all", function () {
    $(".product-variant-selectbox .collapse").addClass("show");
    $(".product-variant-selectbox .btn.btn-tool.text-primary").attr(
        "aria-expanded",
        "true"
    );
});

$(document).on("click", "#collapse_all", function () {
    $(".product-variant-selectbox .collapse").removeClass("show");
    $(".product-variant-selectbox .btn.btn-tool.text-primary").attr(
        "aria-expanded",
        "false"
    );
});
//for display attributes while edit simple product
var edit_product_id = $("input[name=edit_product_id]").val();
var edit_combo_product_id = $("input[name=edit_combo_product_id]").val();

if (edit_product_id) {
    create_fetched_attributes_html(from).done(function () {
        $(".no-attributes-added").hide();
        $(".save_attributes").removeClass("d-none");
        $(".no-variants-added").hide();
        save_attributes();
        create_fetched_variants_html(false, from);
    });
}

if (edit_combo_product_id) {
    create_combo_fetched_attributes_html(from).done(function () {
        $(".no-attributes-added").hide();
        $(".save_attributes").removeClass("d-none");
        $(".no-variants-added").hide();
        save_attributes();
    });
}

function create_fetched_attributes_html(from) {
    var edit_id = $('input[name="edit_product_id"]').val();
    $.ajax({
        type: "GET",
        url: appUrl + from + "/products/fetch_attributes_by_id",
        data: {
            edit_id: edit_id,
        },
        dataType: "json",
        success: function (data) {
            var result = data["result"];

            if (!$.isEmptyObject(result.attr_values)) {
                $.each(result.attr_values, function (key, value) {
                    create_attributes(
                        value,
                        result.pre_selected_variants_names
                    );
                });

                $.each(
                    result["pre_selected_variants_ids"],
                    function (key, val) {
                        var tempArray = [];
                        if (val.variant_ids) {
                            $.each(val.variant_ids.split(","), function (k, v) {
                                tempArray.push($.trim(v));
                            });
                            pre_selected_attr_values[key] = tempArray;
                        }
                    }
                );

                if (result.pre_selected_variants_names) {
                    $.each(
                        result.pre_selected_variants_names.split(","),
                        function (key, value) {
                            pre_selected_attributes_name.push($.trim(value));
                        }
                    );
                }
            } else {
                $(".no-attributes-added").show();
                $(".save_attributes").addClass("d-none");
            }
        },
    });
    return $.Deferred().resolve();
}

function create_combo_fetched_attributes_html(from) {
    var edit_id = $('input[name="edit_combo_product_id"]').val();
    $.ajax({
        type: "GET",
        url: appUrl + from + "/combo_products/fetch_attributes_by_id",
        data: {
            edit_id: edit_id,
        },
        dataType: "json",
        success: function (data) {
            var result = data["result"];

            if (!$.isEmptyObject(result.attr_values)) {
                $.each(result.attr_values, function (key, value) {
                    create_attributes(
                        value,
                        result.pre_selected_variants_names
                    );
                });

                $.each(
                    result["pre_selected_variants_ids"],
                    function (key, val) {
                        var tempArray = [];
                        if (val.variant_ids) {
                            $.each(val.variant_ids.split(","), function (k, v) {
                                tempArray.push($.trim(v));
                            });
                            pre_selected_attr_values[key] = tempArray;
                        }
                    }
                );

                if (result.pre_selected_variants_names) {
                    $.each(
                        result.pre_selected_variants_names.split(","),
                        function (key, value) {
                            pre_selected_attributes_name.push($.trim(value));
                        }
                    );
                }
            } else {
                $(".no-attributes-added").show();
                $(".save_attributes").addClass("d-none");
            }
        },
    });
    return $.Deferred().resolve();
}

function create_attributes(value, selected_attr) {
    counter++;
    var $attribute = $("#attributes_values_json_data").find(".select_single");

    var $options = $($attribute).clone().html();
    var $selected_attrs = [];
    if (selected_attr) {
        $.each(selected_attr.split(","), function () {
            $selected_attrs.push($.trim(this));
        });
    }

    var attr_name = "pro_attr_" + counter;

    // product-attr-selectbox
    if ($("#product-type").val() == "simple_product") {
        var html =
            '<div class="form-group move row my-auto p-2 rounded bg-gray-light product-attr-selectbox" id=' +
            attr_name +
            '><div class="col-md-5 col-sm-12"> <select name="attribute_id[]" class="attributes select_single" data-placeholder=" Type to search and select attributes"><option value="">Select attribute</option>' +
            $options +
            '</select></div><div class="col-md-5 col-sm-12 "> <select name="attribute_value_ids[]" class="multiple_values" multiple="" data-placeholder=" Type to search and select attributes values"><option value=""></option> </select></div><div class="col-md-1 col-sm-6 text-center py-1 align-self-center"> <button type="button" class="btn btn-tool remove_attributes"> <i class="text-danger bx bx-trash fa-2x "></i> </button></div></div>';
    } else {
        $("#note").removeClass("d-none");
        var html =
            '<div class="form-group row move my-auto p-2  rounded bg-gray-light product-attr-selectbox" id=' +
            attr_name +
            '><div class="col-md-5 col-sm-12"> <select name="attribute_id[]" class="attributes select_single" data-placeholder=" Type to search and select attributes"><option value="">Select attribute</option>' +
            $options +
            '</select></div><div class="col-md-5 col-sm-12"> <select name="attribute_value_ids[]" class="multiple_values" multiple="" data-placeholder=" Type to search and select attributes values"><option value=""></option> </select></div><div class="col-md-1 col-sm-6 text-center py-1 align-self-center"><input type="checkbox" name="variations[]" class="is_attribute_checked custom-checkbox mt-2 form-check-input"></div><div class="col-md-1 col-sm-6 text-center py-1 align-self-center"> <button type="button" class="btn btn-tool remove_attributes"> <i class="bx bx-trash"></i> </button></div></div>';
    }
    $("#attributes_process").append(html);
    if (selected_attr) {
        var found = $selected_attrs.some(function (item) {
            return item.indexOf(value.name) !== -1;
        });

        if ($.inArray(value.name, $selected_attrs) > -1) {
            $("#attributes_process")
                .find(".product-attr-selectbox")
                .last()
                .find(".is_attribute_checked")
                .prop("checked", true)
                .addClass("custom-checkbox mt-2");
            $("#attributes_process")
                .find(".product-attr-selectbox")
                .last()
                .find(".remove_attributes")
                .addClass("remove_edit_attribute")
                .removeClass("remove_attributes");
        }
    }

    $("#attributes_process")
        .find(".product-attr-selectbox")
        .last()
        .find(".attributes")
        .select2({
            theme: "bootstrap4",
            width: $(this).data("width")
                ? $(this).data("width")
                : $(this).hasClass("w-100")
                    ? "100%"
                    : "style",
            placeholder: $(this).data("placeholder"),
            allowClear: Boolean($(this).data("allow-clear")),
        })
        .val(value.name);

    $("#attributes_process")
        .find(".product-attr-selectbox")
        .last()
        .find(".attributes")
        .trigger("change");
    $("#attributes_process")
        .find(".product-attr-selectbox")
        .last()
        .find(".select_single")
        .trigger("select2:select");

    var multiple_values = [];
    $.each(value.ids.split(","), function () {
        multiple_values.push($.trim(this));
    });

    $("#attributes_process")
        .find(".product-attr-selectbox")
        .last()
        .find(".multiple_values")
        .select2({
            theme: "bootstrap4",
            width: $(this).data("width")
                ? $(this).data("width")
                : $(this).hasClass("w-100")
                    ? "100%"
                    : "style",
            placeholder: $(this).data("placeholder"),
            allowClear: Boolean($(this).data("allow-clear")),
        })
        .val(multiple_values);
    $("#attributes_process")
        .find(".product-attr-selectbox")
        .last()
        .find(".multiple_values")
        .trigger("change");
}

$.ajaxSetup({
    headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
    },
});
if (document.getElementById("system-update-dropzone")) {
    var systemDropzone = new Dropzone("#system-update-dropzone", {
        url: appUrl + "admin/settings/system-update",
        paramName: "update_file",
        autoProcessQueue: false,
        parallelUploads: 1,
        maxFiles: 1,
        timeout: 360000,
        autoDiscover: false,
        addRemoveLinks: true,
        dictRemoveFile: "x",
        dictMaxFilesExceeded: "Only 1 file can be uploaded at a time ",
        dictResponseError: "Error",
        uploadMultiple: true,
        dictDefaultMessage:
            '<p><input type="submit" value="Select Files" class="btn btn-success" /><br> or <br> Drag & Drop System Update / Installable / Plugin\'s .zip file Here</p>',
    });

    systemDropzone.on("addedfile", function (file) {
        var i = 0;
        if (this.files.length) {
            var _i, _len;
            for (_i = 0, _len = this.files.length; _i < _len - 1; _i++) {
                if (
                    this.files[_i].name === file.name &&
                    this.files[_i].size === file.size &&
                    this.files[_i].lastModifiedDate.toString() ===
                    file.lastModifiedDate.toString()
                ) {
                    this.removeFile(file);
                    i++;
                }
            }
        }
    });

    systemDropzone.on("error", function (file, response) { });

    systemDropzone.on("sending", function (file, xhr, formData) {
        xhr.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                var response = JSON.parse(this.response);
                if (response["error"] == false) {
                    iziToast.success({
                        message: response["message"],
                    });
                } else {
                    iziToast.error({
                        title: "Error",
                        message: response["message"],
                    });
                }
                $(file.previewElement)
                    .find(".dz-error-message")
                    .text(response.message);
            }
        };
    });
    $("#system_update_btn").on("click", function (e) {
        e.preventDefault();
        systemDropzone.processQueue();
    });
}

// change event of cancelable allowed or not

$("#is_cancelable_checkbox").change(function () {
    // If the checkbox is checked (turned on), show the dropdown, otherwise hide it
    if ($(this).prop("checked")) {
        $("#cancelable_till").show();
    } else {
        $("#cancelable_till").hide();
    }
});

$(function () {
    function toggleSimilarProductSection() {
        if ($(".has_similar_product").prop("checked")) {
            $("#similar_product").removeClass("d-none");
        } else {
            $("#similar_product").addClass("d-none");
        }
    }

    // Initial check on page load
    toggleSimilarProductSection();

    // Toggle on change
    $(".has_similar_product").change(function () {
        toggleSimilarProductSection();
    });
});

//change event for digital products

$("#download_allowed").change(function () {
    if ($(this).prop("checked")) {
        $("#download_type").show();
    } else {
        $("#download_type").hide();
        $("#digital_link_container").addClass("d-none");
        $("#digital_media_container").addClass("d-none");
    }
});

$(document).on("change", "#download_link_type", function () {
    var download_link_type = $(this).val();
    if (download_link_type == "add_link") {
        $("#digital_link_container").removeClass("d-none");
        $("#digital_media_container").addClass("d-none");
    } else if (download_link_type == "self_hosted") {
        $("#digital_link_container").addClass("d-none");
        $("#digital_media_container").removeClass("d-none");
    } else {
        $("#digital_media_container").addClass("d-none");
        $("#digital_link_container").addClass("d-none");
    }
});

// save product

$(document).on("submit", "#save-product", function (e) {
    e.preventDefault();
    var product_type = $("#product-type").val();
    // console.log(product_type);
    var counter = 0;
    if (product_type != "undefined" && product_type != " ") {
        if ($.trim(product_type) == "simple_product") {
            if ($(".simple_stock_management_status").is(":checked")) {
                var len = 0;
            } else {
                var len = 1;
            }

            if (
                $(".stock-simple-mustfill-field").filter(function () {
                    return this.value === "";
                }).length === len
            ) {
                $('input[name="product_type"]').val($("#product-type").val());
                if ($(".simple_stock_management_status").is(":checked")) {
                    $('input[name="simple_product_stock_status"]').val(
                        $("#simple_product_stock_status").val()
                    );
                } else {
                    $('input[name="simple_product_stock_status"]').val("");
                }
                $("#product-type").prop("disabled", true);
                $(".product-attributes").removeClass("disabled");
                $(".product-variants").removeClass("disabled");
                $(".simple_stock_management_status").prop("disabled", true);

                save_product(this);
            } else {
                iziToast.error({
                    message: "Please Fill All Stock Related Fields",
                    position: "topRight",
                });
            }
        }

        if ($.trim(product_type) == "variable_product") {
            if ($(".variant_stock_status").is(":checked")) {
                var variant_stock_level_type = $(
                    ".variant-stock-level-type"
                ).val();
                if (variant_stock_level_type == "product_level") {
                    if (
                        $(".variant-stock-level-type").filter(function () {
                            return this.value === "";
                        }).length === 0 &&
                        $.trim($(".variant-stock-level-type").val()) != ""
                    ) {
                        if (
                            $(".variant-stock-level-type").val() ==
                            "product_level" &&
                            $(".variant-stock-mustfill-field").filter(
                                function () {
                                    return this.value === "";
                                }
                            ).length !== 0
                        ) {
                            iziToast.error({
                                message: "Please Fill All The Fields",
                                position: "topRight",
                            });
                        } else {
                            var varinat_price = $(
                                'input[name="variant_price[]"]'
                            ).val();

                            if (
                                $('input[name="variant_price[]"]').length >= 1
                            ) {
                                if (
                                    $(".varaint-must-fill-field").filter(
                                        function () {
                                            return this.value === "";
                                        }
                                    ).length == 0
                                ) {
                                    $('input[name="product_type"]').val(
                                        $("#product-type").val()
                                    );
                                    $(
                                        'input[name="variant_stock_level_type"]'
                                    ).val($("#stock_level_type").val());
                                    $('input[name="varaint_stock_status"]').val(
                                        "0"
                                    );
                                    $("#product-type").prop("disabled", true);
                                    $("#stock_level_type").prop(
                                        "disabled",
                                        true
                                    );
                                    $(this).removeClass(
                                        "save-variant-general-settings"
                                    );
                                    $(".product-attributes").removeClass(
                                        "disabled"
                                    );
                                    $(".product-variants").removeClass(
                                        "disabled"
                                    );
                                    $(".variant-stock-level-type").prop(
                                        "readonly",
                                        true
                                    );
                                    $("#stock_status_variant_type").attr(
                                        "readonly",
                                        true
                                    );
                                    $(".variant-product-level-stock-management")
                                        .find("input,select")
                                        .prop("readonly", true);
                                    $("#tab-for-variations").removeClass(
                                        "d-none"
                                    );
                                    $(".variant_stock_status").prop(
                                        "disabled",
                                        true
                                    );
                                    $(
                                        '#product-tab a[href="#product-attributes"]'
                                    ).tab("show");
                                    save_product(this);
                                } else {
                                    $(".varaint-must-fill-field").each(
                                        function () {
                                            $(this).css("border", "");
                                            if ($(this).val() == "") {
                                                $(this).css(
                                                    "border",
                                                    "2px solid red"
                                                );
                                                $(this)
                                                    .closest(
                                                        "#variant_stock_management_html"
                                                    )
                                                    .find("div:first")
                                                    .addClass("show");
                                                $(
                                                    '#product-tab a[href="#product-variants"]'
                                                ).tab("show");
                                                counter++;
                                            }
                                        }
                                    );
                                }
                            } else {
                                Swal.fire(
                                    "Variation Needed !",
                                    "Atleast Add One Variation To Add The Product.",
                                    "warning"
                                );
                            }
                        }
                    } else {
                        iziToast.error({
                            message: "Please Fill All The Fields",
                            position: "topRight",
                        });
                    }
                } else {
                    if ($('input[name="variant_price[]"]').length >= 1) {
                        if (
                            $(".varaint-must-fill-field").filter(function () {
                                return this.value === "";
                            }).length == 0
                        ) {
                            $('input[name="product_type"]').val(
                                $("#product-type").val()
                            );
                            $(".variant_stock_status").prop("disabled", true);
                            $("#product-type").prop("disabled", true);
                            $(".product-attributes").removeClass("disabled");
                            $(".product-variants").removeClass("disabled");
                            $("#tab-for-variations").removeClass("d-none");
                            save_product(this);
                        } else {
                            $(".varaint-must-fill-field").each(function () {
                                $(this).css("border", "");
                                if ($(this).val() == "") {
                                    $(this).css("border", "2px solid red");
                                    $(this)
                                        .closest(
                                            "#variant_stock_management_html"
                                        )
                                        .find("div:first")
                                        .addClass("show");
                                    $(
                                        '#product-tab a[href="#product-variants"]'
                                    ).tab("show");
                                    counter++;
                                }
                            });
                        }
                    } else {
                        Swal.fire(
                            "Variation Needed !",
                            "Atleast Add One Variation To Add The Product.",
                            "warning"
                        );
                    }
                }
            } else {
                if ($('input[name="variant_price[]"]').length == 0) {
                    Swal.fire(
                        "Variation Needed!",
                        "At least add one variation to add the product.",
                        "warning"
                    );
                } else {
                    let counter = 0;
                    let priceError = false;
                    let imageError = false;

                    // Loop through each variant
                    $('input[name="variant_price[]"]').each(function (index) {
                        const price = parseFloat($(this).val()) || 0;
                        const specialPrice =
                            parseFloat(
                                $('input[name="variant_special_price[]"]')
                                    .eq(index)
                                    .val()
                            ) || 0;

                        // Check if special price is greater than price
                        if (specialPrice > price) {
                            priceError = true;
                            $('input[name="variant_special_price[]"]')
                                .eq(index)
                                .css("border", "2px solid red");
                            $('input[name="variant_price[]"]')
                                .eq(index)
                                .css("border", "2px solid red");

                            $('#product-tab a[href="#product-variants"]').tab(
                                "show"
                            );
                        }

                        let is_image_selected = false;

                        $(`[name^='variant_images']`).each(function () {
                            if (
                                $(this).val() !== "" &&
                                $(this).val() !== undefined
                            ) {
                                is_image_selected = true;
                            }
                        });

                        if (!is_image_selected) {
                            imageError = true;
                        }
                    });

                    // Check for empty required fields
                    if (
                        $(".varaint-must-fill-field").filter(function () {
                            return this.value === "";
                        }).length == 0
                    ) {
                        if (imageError) {
                            iziToast.error({
                                message:
                                    "Please upload at least one image for each variant.",
                                position: "topRight",
                            });
                        } else if (priceError) {
                            iziToast.error({
                                message:
                                    "Special price cannot be greater than the main price.",
                                position: "topRight",
                            });
                        } else {
                            save_product(this);
                        }
                    } else {
                        $(".varaint-must-fill-field").each(function () {
                            $(this).css("border", "");
                            if ($(this).val() == "") {
                                $(this).css("border", "2px solid red");
                                $(this)
                                    .closest("#variant_stock_management_html")
                                    .find("div:first")
                                    .addClass("show");
                                $(
                                    '#product-tab a[href="#product-variants"]'
                                ).tab("show");
                                counter++;
                            }
                        });
                    }
                }
            }
        }
        if ($.trim(product_type) == "digital_product") {
            save_product(this);
        }
    } else {
        iziToast.error({
            message: "Please Select Product Type !",
        });
    }

    if (counter > 0) {
        iziToast.error({
            message:
                "Please fill all the required fields in the variation tab !",
            position: "topRight",
        });
    }
});

function save_product(form) {
    // console.log('here');
    $('input[name="product_type"]').val($("#product-type").val());
    if ($(".simple_stock_management_status").is(":checked")) {
        $('input[name="simple_product_stock_status"]').val(
            $("#simple_product_stock_status").val()
        );
    } else {
        $('input[name="simple_product_stock_status"]').val("");
    }
    $("#product-type").prop("disabled", true);
    $(".product-attributes").removeClass("disabled");
    $(".product-variants").removeClass("disabled");
    $(".simple_stock_management_status").prop("disabled", true);

    // var catid = $("#product_category_tree_view_html").jstree("get_selected");
    var formData = new FormData(form);
    // console.log(formData);
    var submit_btn = $(".submit_button");
    var btn_html = $(".submit_button").html();
    var btn_val = $(".submit_button").val();
    var button_text =
        btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
    save_attributes();
    token = $('meta[name="csrf-token"]').attr("content");
    // formData.append("category_id", catid);
    formData.append("attribute_values", all_attributes_values);
    $.ajax({
        type: "POST",
        url: $(form).attr("action"),
        data: formData,
        beforeSend: function () {
            submit_btn.html("Please Wait..");
            submit_btn.attr("disabled", true);
        },
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            // console.log(result);
            var location = result["location"] || "";
            token = $('meta[name="csrf-token"]').attr("content");
            if (result["error"] == true) {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                if (result["error_message"]) {
                    iziToast.error({
                        message: result["error_message"],
                    });
                } else if (result["message"]) {
                    iziToast.error({
                        message: result["message"],
                    });
                }
            } else {
                submit_btn.html(button_text);
                submit_btn.attr("disabled", false);
                iziToast.success({
                    message: result["message"],
                });
                $("#save-product")[0].reset();
                setTimeout(function () {
                    // Redirect to the appropriate URL
                    window.location.href = location;
                }, 2000);
            }
        },
        error: function (xhr, status, error) {
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;

                // Display each error message in a separate toast
                $.each(errors, function (field, errorMessages) {
                    if (Array.isArray(errorMessages)) {
                        $.each(errorMessages, function (index, errorMessage) {
                            iziToast.error({
                                title: "Error",
                                message: errorMessage,
                                position: "topRight",
                            });
                        });
                    } else {
                        iziToast.error({
                            title: "Error",
                            message: errorMessages,
                            position: "topRight",
                        });
                    }
                });
            } else {
                iziToast.error({
                    title: "Error",
                    message: xhr.responseJSON.message,
                    position: "topRight",
                });
            }
        },
    });
}
// tinymce editor

$(".editSendMail").on("shown.bs.modal", function (e) {
    if ($(".textarea").length > 0) {
        tinymce.init({
            selector: ".textarea",
            plugins: [
                "a11ychecker",
                "advlist",
                "advcode",
                "advtable",
                "autolink",
                "checklist",
                "export",
                "lists",
                "link",
                "image",
                "charmap",
                "preview",
                "code",
                "anchor",
                "searchreplace",
                "visualblocks",
                "powerpaste",
                "fullscreen",
                "formatpainter",
                "insertdatetime",
                "media",
                "image",
                "directionality",
                "fullscreen",
                "table",
                "help",
                "wordcount",
            ],
            toolbar:
                "undo redo | image media | code fullscreen| formatpainter casechange blocks fontsize | bold italic forecolor backcolor | " +
                "alignleft aligncenter alignright alignjustify | " +
                "bullist numlist checklist outdent indent | removeformat | ltr rtl |a11ycheck table help",

            font_size_formats: "8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt",
            image_uploadtab: false,

            relative_urls: false,
            remove_script_host: false,
            file_picker_types: "image media",
            media_poster: false,
            media_alt_source: false,

            setup: function (editor) {
                editor.on("change keyup", function (e) {
                    editor.save(); // updates this instance's textarea
                    $(editor.getElement()).trigger("change"); // for garlic to detect change
                });
            },
        });
    }
});

tinymce.init({
    selector: ".addr_editor",
    menubar: true,
    plugins: [
        "a11ychecker",
        "advlist",
        "advcode",
        "advtable",
        "autolink",
        "checklist",
        "export",
        "lists",
        "link",
        "image",
        "charmap",
        "preview",
        "code",
        "anchor",
        "searchreplace",
        "visualblocks",
        "powerpaste",
        "fullscreen",
        "formatpainter",
        "insertdatetime",
        "media",
        "image",
        "directionality",
        "fullscreen",
        "table",
        "help",
        "wordcount",
    ],
    toolbar:
        "undo redo | image media | code fullscreen| formatpainter casechange blocks fontsize | bold italic forecolor backcolor | " +
        "alignleft aligncenter alignright alignjustify | " +
        "bullist numlist checklist outdent indent | removeformat | ltr rtl |a11ycheck table help",

    font_size_formats: "8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt",
    image_uploadtab: false,
    relative_urls: false,
    remove_script_host: false,
    file_picker_types: "image media",
    media_poster: false,
    media_alt_source: false,
    setup: function (editor) {
        editor.on("change keyup", function (e) {
            editor.save(); // updates this instance's textarea
            $(editor.getElement()).trigger("change"); // for garlic to detect change
        });
    },
});

// general ajax request for fetch brands data in admin panel

$(".admin_brand_list").select2({
    ajax: {
        url: appUrl + from + "/products/get_brands",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
                limit: 5,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },

        cache: true,
    },
    placeholder: "Search for brands",
});
$(".admin_product_brand_list").select2({
    ajax: {
        url: appUrl + from + "/products/get_brands",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
                limit: 5,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },

        cache: true,
    },
    placeholder: "Search for brands",
});

// get countries data

$(".country_list").select2({
    ajax: {
        url: appUrl + from + "/products/get_countries",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },
        cache: true,
    },
    dropdownParent: $(".country_list_div"),
    minimumInputLength: 1,
    placeholder: "Search for countries",
});

// search zipcode

function toggleDeliverableZones(type) {
    if (type == "0" || type == "1") {
        $("#deliverable_zones")
            .prop("disabled", true)
            .removeAttr("required")
            .trigger("change.select2");
    } else {
        $("#deliverable_zones")
            .prop("disabled", false)
            .attr("required", "required")
            .trigger("change.select2");
    }
}
$(document).on("change", "#deliverable_type", function () {
    toggleDeliverableZones($(this).val());
});

var search_zipcodes = searchable_zipcodes();

search_zipcodes.on("select2:select", function (e) {
    var data = e.params.data;
    if (data.link != undefined && data.link != null) {
        window.location.href = data.link;
    }
});

function searchable_zipcodes() {
    var search_zipcodes = $(".search_zipcode").select2({
        ajax: {
            url: appUrl + from + "/area/get_zipcodes",
            type: "GET",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                };
            },
            processResults: function (response) {
                return {
                    results: response,
                };
            },
            cache: true,
        },

        placeholder: "Search for zipcodes",
        allowClear: Boolean($(this).data("allow-clear")),
    });

    return search_zipcodes;
}
var storeId = "";
$(document).on("change", ".seller_register_store_id", function () {
    storeId = $(this).val();
});
$(".search_admin_category").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/categories/get_category_details",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },

        escapeMarkup: function (markup) {
            return markup;
        },
        placeholder: $(this).data("placeholder") || "Search for categories",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});
// search product in sliders and offers and feature section
var combo_seller_id = "";

$(document).on("change", ".combo_seller_id", function (e) {
    e.preventDefault();
    var combo_seller_id = $(this).val();
    $(".main_combo_seller_id").val(combo_seller_id);
    $.ajax({
        url: appUrl + from + "/seller/get_seller_deliverable_type",
        method: "GET",
        data: { seller_id: combo_seller_id },
        success: function (response) {
            // console.log(response);
            if (
                (response && response.deliverable_type == "2") ||
                response.deliverable_type == "3"
            ) {
                $(".combo_all_deliverable_type").addClass("d-none");
            } else {
                $(".combo_all_deliverable_type").removeClass("d-none");
            }
        },
        error: function (xhr, status, error) {
            console.error("Error fetching deliverable type: " + error);
            $("#deliverable_type_display").text(
                "Error fetching deliverable type"
            );
        },
    });
});

$(".search_admin_product").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/products/get_product_details",
            dataType: "json",
            delay: 250,
            data: function (data) {
                var page = data.page || 1;
                return {
                    search: data.term,
                    page: page,
                    offset: (page - 1) * 30, // Calculate offset based on page number
                    seller_id: $(".main_combo_seller_id").val(),
                    limit: 30, // Number of items per page
                };
            },
            processResults: function (response, params) {
                params.page = params.page || 1;
                return {
                    results: response.results,
                    pagination: {
                        more: params.page * 30 < response.total,
                    },
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        placeholder: $(this).data("placeholder") || "Search for products",
        dropdownParent: $(".search_admin_product_parent"),
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

$(".search_admin_product_for_combo").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/products/get_product_details_for_combo",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                    seller_id: $(".main_combo_seller_id").val(),
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        placeholder: $(this).data("placeholder") || "Search for products",
        // dropdownParent: $(".search_admin_product_for_combo_parent"),
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

$(".search_admin_digital_product").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/products/get_digital_product_data",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        placeholder: $(this).data("placeholder") || "Search for products",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

$(".search_admin_combo_product").each(function () {
    var is_update = $(this).data("update");
    var product_id = $(this).data("product_id");
    $(this).select2({
        ajax: {
            url: appUrl + from + "/combo_products/get_product_details",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    limit: 10,
                    page: params.page || 1,
                    is_update: is_update,
                    product_id: product_id,
                };
            },
            processResults: function (response, params) {
                params.page = params.page || 1;
                return {
                    results: response.results,
                    pagination: {
                        more: response.pagination.more,
                    },
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        placeholder: $(this).data("placeholder") || "Search for products",
        // dropdownParent: $(".search_admin_combo_product_parent"),
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

$(".search_seller_combo_product").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/combo_products/get_product_details",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },

        placeholder: $(this).data("placeholder") || "Search for products",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
        // dropdownParent: $(".search_seller_combo_product_parent"),
    });
});

$(".search_seller_product").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/products/get_product_details",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },

        placeholder: $(this).data("placeholder") || "Search for products",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
        dropdownParent: $(".search_seller_product_parent"),
    });
});

$(".search_seller_digital_product").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + from + "/products/get_digital_product_data",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 10,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        minimumInputLength: 1,
        placeholder: $(this).data("placeholder") || "Search for products",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

//change event for feature section
$(document).on("change", ".product_type", function (e) {
    e.preventDefault();
    var sort_type_val = $(this).val();

    // Check for custom_products type
    if (sort_type_val == "custom_products" && sort_type_val != " ") {
        $(".custom_products").removeClass("d-none");
        $(".select-categories").addClass("d-none");
    } else {
        $(".custom_products").addClass("d-none");
    }

    // Check for custom_combo_products type
    if (sort_type_val == "custom_combo_products" && sort_type_val != " ") {
        $(".custom_combo_products").removeClass("d-none");
        $(".select-categories").addClass("d-none");
    } else {
        $(".custom_combo_products").addClass("d-none");
    }

    // Check for digital_product type
    if (sort_type_val == "digital_product" && sort_type_val != " ") {
        $(".digital_products").removeClass("d-none");
        $(".select-categories").addClass("d-none");
    } else {
        $(".digital_products").addClass("d-none");
    }

    // If no matching type, show the category options
    if (
        sort_type_val != "custom_products" &&
        sort_type_val != "custom_combo_products" &&
        sort_type_val != "digital_product"
    ) {
        $(".select-categories").removeClass("d-none");
    }
});

// select2 initilization for default currency

$(".default_currency").select2({
    width: $(this).data("width")
        ? $(this).data("width")
        : $(this).hasClass("w-100")
            ? "100%"
            : "style",
    placeholder: $(this).data("placeholder"),
    allowClear: Boolean($(this).data("allow-clear")),
});

// change event for system users

$(document).on("change", ".system-user-role", function () {
    var role = $(this).val();
    if (role > 0) {
        $(".permission-table").removeClass("d-none");
    } else {
        $(".permission-table").addClass("d-none");
    }
});

// update orders using sortable

$(function () {
    $("#sortable").sortable({
        axis: "y",
        opacity: 0.6,
        cursor: "grab",
    });
});

// brand bulk upload form submit

// $("#bulk_upload_form").on("submit", function (e) {
//     e.preventDefault();
//     var type = $("#type").val();
//     if (type != "") {
//         var formdata = new FormData(this);
//         token = $('meta[name="csrf-token"]').attr("content");
//         $.ajax({
//             type: "POST",
//             data: formdata,
//             url: $(this).attr("action"),
//             dataType: "json",
//             cache: false,
//             contentType: false,
//             processData: false,

//             success: function (result) {
//                 token = $('meta[name="csrf-token"]').attr("content");
//                 if (result.error == true && result.error_message) {
//                     iziToast.show({
//                         title: "Error",
//                         message: result.error_message,
//                         color: "red",
//                     });
//                 } else if (result.error == "false") {
//                     iziToast.show({
//                         title: "Success",
//                         message: result.message,
//                         color: "green",
//                     });
//                 } else {
//                     iziToast.show({
//                         title: "Error",
//                         message: result.message,
//                         color: "red",
//                     });
//                 }
//             },
//         });
//     } else {
//         iziToast.error({
//             message: "Please select type",
//         });
//     }
// });
$("#bulk_upload_form").on("submit", function (e) {
    e.preventDefault();

    var type = $("#type").val();
    var submitButton = $(".submit_button"); // Assuming your submit button has this ID

    if (type != "") {
        var formdata = new FormData(this);
        token = $('meta[name="csrf-token"]').attr("content");

        // Disable button and change text to "Please wait..."
        submitButton.prop("disabled", true).text("Please wait...");

        $.ajax({
            type: "POST",
            data: formdata,
            url: $(this).attr("action"),
            dataType: "json",
            cache: false,
            contentType: false,
            processData: false,

            success: function (result) {
                token = $('meta[name="csrf-token"]').attr("content");

                if (result.error == true && result.error_message) {
                    iziToast.show({
                        title: "Error",
                        message: result.error_message,
                        color: "red",
                    });
                } else if (result.error == "false") {
                    iziToast.show({
                        title: "Success",
                        message: result.message,
                        color: "green",
                    });
                    setTimeout(function () {
                        location.reload();
                    }, 2000); // Reload after 2 seconds
                } else {
                    iziToast.show({
                        title: "Error",
                        message: result.message,
                        color: "red",
                    });
                }
            },
            complete: function () {
                // Re-enable button and reset text after the request is complete
                submitButton.prop("disabled", false).text("Upload");
            },
        });
    } else {
        iziToast.error({
            message: "Please select type",
        });
    }
});

// location bulk upload

$("#location_bulk_upload_form").on("submit", function (e) {
    e.preventDefault();
    var type = $("#type").val();
    var location_type = $("#location_type").val();
    if (
        type != "" &&
        location_type != "" &&
        type != "undefined" &&
        location_type != "undefined"
    ) {
        var formdata = new FormData(this);
        token = $('meta[name="csrf-token"]').attr("content");
        $.ajax({
            type: "POST",
            data: formdata,
            url: $(this).attr("action"),
            dataType: "json",
            cache: false,
            contentType: false,
            processData: false,
            success: function (result) {
                token = $('meta[name="csrf-token"]').attr("content");
                if (result.error == "false") {
                    iziToast.show({
                        title: "Success",
                        message: result.message,
                        color: "green",
                    });
                } else {
                    iziToast.show({
                        title: "Error",
                        message: result.message,
                        color: "red",
                    });
                }
            },
        });
    } else {
        iziToast.error({
            message: "Please select Type and Location Type",
        });
    }
});

// click event of edit data from model

$(function () {
    $(document).on("click", ".edit-tax", function () {
        var tax_id = $(this).data("id");
        var fields_to_update = {
            edit_title: "title",
            edit_percentage: "percentage",
        };
        edit_data_with_translation(
            "/admin/tax",
            "edit_tax_id",
            tax_id,
            fields_to_update,
            "title"
        );
    });

    $(document).on("click", ".edit-city", function () {
        var city_id = $(this).data("id");
        var fields_to_update = {
            name: "name",
            minimum_free_delivery_order_amount:
                "minimum_free_delivery_order_amount",
            delivery_charges: "delivery_charges",
        };
        edit_data_with_translation(
            "/admin/city",
            "edit_city_id",
            city_id,
            fields_to_update,
            "name"
        );
    });

    $(document).on("click", ".edit-storage-type", function () {
        var storage_type_id = $(this).data("id");
        var fields_to_update = {
            name: "name",
        };
        edit_data(
            "/admin/storage_type",
            "edit_storage_type_id",
            storage_type_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-blog-category", function () {
        var category_id = $(this).data("id");
        var fields_to_update = {
            name: "name",
            image: "image",
        };
        edit_data(
            "/admin/blog_category",
            "edit_category_id",
            category_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-faq", function () {
        var faq_id = $(this).data("id");
        var fields_to_update = {
            edit_question: "question",
            edit_answer: "answer",
        };
        edit_data("/admin/faq", "edit_faq_id", faq_id, fields_to_update);
    });

    $(document).on("click", ".edit-ticket-type", function () {
        var ticket_type_id = $(this).data("id");
        var fields_to_update = {
            title: "title",
        };
        edit_data(
            "/admin/ticket_types",
            "edit_ticket_type_id",
            ticket_type_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-seller-stock", function () {
        var variant_id = $(this).data("id");

        var fields_to_update = {
            product_name: "product_name",
            stock: "stock",
            quantity: "quantity",
            type: "type",
        };
        edit_data(
            "/" + from + "/manage_stock",
            "edit_variant_id",
            variant_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-pickup_location", function () {
        var edit_id = $(this).data("id");

        var fields_to_update = {
            pickup_location: "pickup_location",
            name: "name",
            email: "email",
            phone: "phone",
            city: "city",
            state: "state",
            country: "country",
            pincode: "pincode",
            address: "address",
            address2: "address2",
            latitude: "latitude",
            longitude: "longitude",
            seller_id: "seller_id",
        };
        edit_data(
            "/" + from + "/pickup_location",
            "edit_id",
            edit_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-combo-stock", function () {
        var product_id = $(this).data("id");

        var fields_to_update = {
            product_name: "product_name",
            stock: "stock",
            quantity: "quantity",
            type: "type",
        };
        edit_data(
            "/" + from + "/manage_combo_stock",
            "edit_product_id",
            product_id,
            fields_to_update
        );
    });
    $(document).on("click", ".edit-product-faq", function () {
        var edit_id = $(this).data("id");
        var fields_to_update = {
            question: "question",
            answer: "answer",
        };
        edit_data(
            "/" + from + "/product_faqs",
            "edit_faq_id",
            edit_id,
            fields_to_update
        );
    });
    $(document).on("click", ".edit-combo-product-faq", function () {
        var edit_id = $(this).data("id");
        var fields_to_update = {
            question: "question",
            answer: "answer",
        };
        edit_data(
            "/" + from + "/combo_product_faqs",
            "edit_faq_id",
            edit_id,
            fields_to_update
        );
    });

    $(document).on("click", ".edit-currency", function () {
        var currency_id = $(this).data("id");
        var fields_to_update = {
            code: "code",
            symbol: "symbol",
            name: "name",
            exchange_rate: "exchange_rate",
        };
        edit_data(
            "/admin/currency",
            "edit_currency_id",
            currency_id,
            fields_to_update
        );
    });
});

// general function for edit data from model

function edit_data(url_prefix, id_field, id, fields_to_update) {
    $.ajax({
        url: url_prefix + "/edit/" + id,
        type: "GET",
        success: function (data) {
            console.log(data);
            $("." + id_field).val(data.id);
            // Loop through the fields_to_update and set their values
            $.each(fields_to_update, function (field_id, data_field) {
                $("." + field_id).val(data[data_field]);
            });

            // Set the form action dynamically
            $(".submit_form").attr("action", url_prefix + "/update/" + id);

            $("#edit_modal").modal("show");
        },
    });
}
function edit_data_with_translation(
    url_prefix,
    id_field,
    id,
    fields_to_update,
    translated_field = ""
) {
    $.ajax({
        url: url_prefix + "/edit/" + id,
        type: "GET",
        success: function (data) {
            console.log("AJAX Response:", data);

            $("." + id_field).val(data.id);

            let translatedData = {}; // Object to store translated values

            // Determine which field to use
            if (translated_field === "title") {
                translatedData = JSON.parse(data.title);
            } else if (translated_field === "name") {
                translatedData = JSON.parse(data.name);
            }

            console.log("Parsed translated data:", translatedData);

            // Set English value separately
            $(".edit_title").val(translatedData.en || "");
            $(".name").val(translatedData.en || "");

            $.each(fields_to_update, function (field_id, data_field) {
                if (data_field !== translated_field) {
                    $("." + field_id).val(data[data_field]);
                }
            });

            // Loop through available languages and set their values
            $.each(translatedData, function (lang_code, lang_value) {
                if (lang_code !== "en") {
                    console.log(`Setting value for ${lang_code}:`, lang_value);

                    // Ensure field exists before setting value
                    let inputField = $(".translated_name_" + lang_code);
                    if (inputField.length) {
                        inputField.val(lang_value);
                    } else {
                        console.warn(`Input field for ${lang_code} not found!`);
                    }
                }
            });

            $(".submit_form").attr("action", url_prefix + "/update/" + id);

            $("#edit_modal").modal("show");
        },
        error: function (xhr) {
            console.error("AJAX Error:", xhr.responseText);
        },
    });
}

$(function () {
    $(document).on("click", ".edit-zipcode", function () {
        var zipcodeId = $(this).data("id");

        // $.ajax({
        //     url: "/admin/zipcode/" + zipcodeId,
        //     method: "GET",
        //     success: function (data) {
        //         $(".edit_zipcode_id").val(data.id);
        //         $(".zipcode").val(data.zipcode);

        //         // Set selected city in the dropdown
        //         $("#city").val(data.city_id);

        //         $(".minimum_free_delivery_order_amount").val(
        //             data.minimum_free_delivery_order_amount
        //         );
        //         $(".delivery_charges").val(data.delivery_charges);

        //         // Open the modal
        //         $("#edit_modal").modal("show");
        //         $(".submit_form").attr(
        //             "action",
        //             "/admin/zipcodes/update/" + data.id
        //         );
        //     },
        // });
        $.ajax({
            url: "/admin/zipcode/" + zipcodeId,
            method: "GET",
            success: function (data) {
                $(".edit_zipcode_id").val(data.id);
                $(".zipcode").val(data.zipcode);

                // ✅ Inject the selected city into Select2
                var newOption = new Option(data.city_name, data.city_id, true, true);
                $("#city").empty().append(newOption).trigger('change');

                $(".minimum_free_delivery_order_amount").val(data.minimum_free_delivery_order_amount);
                $(".delivery_charges").val(data.delivery_charges);

                $("#edit_modal").modal("show");
                $(".submit_form").attr("action", "/admin/zipcodes/update/" + data.id);
            }
        });
    });
});

$(".get_blog_category").select2({
    ajax: {
        url: appUrl + "admin/blogs/get_blog_categories",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term,
            };
        },
        processResults: function (response) {
            return {
                results: response,
            };
        },
        cache: true,
    },

    // dropdownParent: $(".get_blog_category_parent"),
    placeholder: "Search for products",
});

// light box

$(function () {
    $(document).on("click", '[data-toggle="lightbox"]', function (event) {
        event.preventDefault();
        $(this).ekkoLightbox();
    });
});
$(function () {
    // Extract the current URL
    var current_url = window.location.href;

    var category_url;

    // Check if the URL contains the specific strings
    if (current_url.includes("seller/categories")) {
        // If the URL contains "seller/categories/", set the category URL for the seller
        category_url = appUrl + "seller/categories/get_seller_categories";
    } else if (current_url.includes("admin/categories")) {
        // If the URL contains "admin/categories/", set the category URL for the admin
        category_url = appUrl + "admin/categories/getCategories";
    }
    // Load the JavaScript using the determined URL
    if (category_url) {
        $.ajax({
            type: "GET",
            url: category_url,
            dataType: "json",
            success: function (result) {
                // Initialize jsTree with the received data
                $(".tree_view_html").jstree({
                    plugins: ["themes", "checkbox", "types"],
                    core: {
                        themes: {
                            variant: "large",
                        },
                        data: result.categories,
                        multiple: false,
                    },
                    types: {
                        boss: {
                            icon:
                                appUrl +
                                "/storage/app_images_and_videos/checkbox_checked.png",
                        },
                    },
                    checkbox: {
                        three_state: false,
                        cascade: "none",
                        keep_selected_style: false,
                    },
                });
            },
        });
    }
});

// custom message type change event

$(function () {
    var sort_type_val = $(".custom_message_type").val();
    if (sort_type_val == "place_order" && sort_type_val != " ") {
        $(".place_order").removeClass("d-none");
    } else {
        $(".place_order").addClass("d-none");
    }
    if (sort_type_val == "settle_cashback_discount" && sort_type_val != " ") {
        $(".settle_cashback_discount").removeClass("d-none");
    } else {
        $(".settle_cashback_discount").addClass("d-none");
    }
    if (sort_type_val == "settle_seller_commission" && sort_type_val != " ") {
        $(".settle_seller_commission").removeClass("d-none");
    } else {
        $(".settle_seller_commission").addClass("d-none");
    }
    if (sort_type_val == "customer_order_received" && sort_type_val != " ") {
        $(".customer_order_received").removeClass("d-none");
    } else {
        $(".customer_order_received").addClass("d-none");
    }
    if (sort_type_val == "customer_order_processed" && sort_type_val != " ") {
        $(".customer_order_processed").removeClass("d-none");
    } else {
        $(".customer_order_processed").addClass("d-none");
    }
    if (sort_type_val == "customer_order_shipped" && sort_type_val != " ") {
        $(".customer_order_shipped").removeClass("d-none");
    } else {
        $(".customer_order_shipped").addClass("d-none");
    }
    if (sort_type_val == "customer_order_delivered" && sort_type_val != " ") {
        $(".customer_order_delivered").removeClass("d-none");
    } else {
        $(".customer_order_delivered").addClass("d-none");
    }
    if (sort_type_val == "customer_order_cancelled" && sort_type_val != " ") {
        $(".customer_order_cancelled").removeClass("d-none");
    } else {
        $(".customer_order_cancelled").addClass("d-none");
    }
    if (sort_type_val == "customer_order_returned" && sort_type_val != " ") {
        $(".customer_order_returned").removeClass("d-none");
    } else {
        $(".customer_order_returned").addClass("d-none");
    }
    if (
        sort_type_val == "customer_order_returned_request_approved" &&
        sort_type_val != " "
    ) {
        $(".customer_order_returned_request_approved").removeClass("d-none");
    } else {
        $(".customer_order_returned_request_approved").addClass("d-none");
    }
    if (
        sort_type_val == "customer_order_returned_request_decline" &&
        sort_type_val != " "
    ) {
        $(".customer_order_returned_request_decline").removeClass("d-none");
    } else {
        $(".customer_order_returned_request_decline").addClass("d-none");
    }
    if (sort_type_val == "delivery_boy_order_deliver" && sort_type_val != " ") {
        $(".delivery_boy_order_deliver").removeClass("d-none");
    } else {
        $(".delivery_boy_order_deliver").addClass("d-none");
    }
    if (sort_type_val == "wallet_transaction" && sort_type_val != " ") {
        $(".wallet_transaction").removeClass("d-none");
    } else {
        $(".wallet_transaction").addClass("d-none");
    }
    if (sort_type_val == "ticket_status" && sort_type_val != " ") {
        $(".ticket_status").removeClass("d-none");
    } else {
        $(".ticket_status").addClass("d-none");
    }
    if (sort_type_val == "ticket_message" && sort_type_val != " ") {
        $(".ticket_message").removeClass("d-none");
    } else {
        $(".ticket_message").addClass("d-none");
    }
    if (
        sort_type_val == "bank_transfer_receipt_status" &&
        sort_type_val != " "
    ) {
        $(".bank_transfer_receipt_status").removeClass("d-none");
    } else {
        $(".bank_transfer_receipt_status").addClass("d-none");
    }
    if (sort_type_val == "bank_transfer_proof" && sort_type_val != " ") {
        $(".bank_transfer_proof").removeClass("d-none");
    } else {
        $(".bank_transfer_proof").addClass("d-none");
    }
});

$(document).on("change", ".custom_message_type", function (e, data) {
    e.preventDefault();
    var sort_type_val = $(this).val();
    if (sort_type_val == "place_order" && sort_type_val != " ") {
        $(".place_order").removeClass("d-none");
    } else {
        $(".place_order").addClass("d-none");
    }
    if (sort_type_val == "settle_cashback_discount" && sort_type_val != " ") {
        $(".settle_cashback_discount").removeClass("d-none");
    } else {
        $(".settle_cashback_discount").addClass("d-none");
    }
    if (sort_type_val == "settle_seller_commission" && sort_type_val != " ") {
        $(".settle_seller_commission").removeClass("d-none");
    } else {
        $(".settle_seller_commission").addClass("d-none");
    }
    if (sort_type_val == "customer_order_received" && sort_type_val != " ") {
        $(".customer_order_received").removeClass("d-none");
    } else {
        $(".customer_order_received").addClass("d-none");
    }
    if (sort_type_val == "customer_order_processed" && sort_type_val != " ") {
        $(".customer_order_processed").removeClass("d-none");
    } else {
        $(".customer_order_processed").addClass("d-none");
    }
    if (sort_type_val == "customer_order_shipped" && sort_type_val != " ") {
        $(".customer_order_shipped").removeClass("d-none");
    } else {
        $(".customer_order_shipped").addClass("d-none");
    }
    if (sort_type_val == "customer_order_delivered" && sort_type_val != " ") {
        $(".customer_order_delivered").removeClass("d-none");
    } else {
        $(".customer_order_delivered").addClass("d-none");
    }
    if (sort_type_val == "customer_order_cancelled" && sort_type_val != " ") {
        $(".customer_order_cancelled").removeClass("d-none");
    } else {
        $(".customer_order_cancelled").addClass("d-none");
    }
    if (sort_type_val == "customer_order_returned" && sort_type_val != " ") {
        $(".customer_order_returned").removeClass("d-none");
    } else {
        $(".customer_order_returned").addClass("d-none");
    }
    if (
        sort_type_val == "customer_order_returned_request_approved" &&
        sort_type_val != " "
    ) {
        $(".customer_order_returned_request_approved").removeClass("d-none");
    } else {
        $(".customer_order_returned_request_approved").addClass("d-none");
    }
    if (
        sort_type_val == "customer_order_returned_request_decline" &&
        sort_type_val != " "
    ) {
        $(".customer_order_returned_request_decline").removeClass("d-none");
    } else {
        $(".customer_order_returned_request_decline").addClass("d-none");
    }
    if (sort_type_val == "delivery_boy_order_deliver" && sort_type_val != " ") {
        $(".delivery_boy_order_deliver").removeClass("d-none");
    } else {
        $(".delivery_boy_order_deliver").addClass("d-none");
    }
    if (sort_type_val == "wallet_transaction" && sort_type_val != " ") {
        $(".wallet_transaction").removeClass("d-none");
    } else {
        $(".wallet_transaction").addClass("d-none");
    }
    if (sort_type_val == "ticket_status" && sort_type_val != " ") {
        $(".ticket_status").removeClass("d-none");
    } else {
        $(".ticket_status").addClass("d-none");
    }
    if (sort_type_val == "ticket_message" && sort_type_val != " ") {
        $(".ticket_message").removeClass("d-none");
    } else {
        $(".ticket_message").addClass("d-none");
    }
    if (
        sort_type_val == "bank_transfer_receipt_status" &&
        sort_type_val != " "
    ) {
        $(".bank_transfer_receipt_status").removeClass("d-none");
    } else {
        $(".bank_transfer_receipt_status").addClass("d-none");
    }
    if (sort_type_val == "bank_transfer_proof" && sort_type_val != " ") {
        $(".bank_transfer_proof").removeClass("d-none");
    } else {
        $(".bank_transfer_proof").addClass("d-none");
    }
});

$(function () {
    $(document).on("click", ".hashtag", function () {
        var txt = $.trim($(this).text());
        var box = $("#text-box");
        box.val(box.val() + txt);
    });
    $(document).on("click", ".hashtag_input", function () {
        var txt = $.trim($(this).text());
        var box = $("#custom_message_title");
        box.val(box.val() + txt);
    });
});

$(document).on("click", ".update_status_admin_bulk", function (e) {
    var order_item_id = [];
    if ($('input[name="seller_id"]:checked').val() != undefined) {
        var seller_id = $('input[name="seller_id"]:checked').val();
    } else {
        var seller_id = $(this).data("seller_id");
    }

    if ($('input[name="edit_order_id"]').val() != undefined) {
        var order_id = $('input[name="edit_order_id"]').val();
    } else {
        var order_id = $('input[name="order_id"]').val();
    }
    var status = $(".status").val();
    var deliver_by = $("#deliver_by").val();
    var order_item_ids = $(
        'input[name="order_item_id"]:checked'
    ).serializeArray();
    var order_item_ids = $(
        'input[name="order_item_id"]:checked'
    ).serializeArray();
    $.each(order_item_ids, function (i, field) {
        order_item_id.push(field.value);
    });

    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + from + "/orders/update_order_status",
                    data: {
                        seller_id: seller_id,
                        order_id: order_id,
                        status: status,
                        deliver_by: deliver_by,
                        order_item_id: order_item_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },

                    dataType: "json",
                    success: function (result) {
                        if (result["error"] == false) {
                            iziToast.success({
                                message: result["message"],
                            });
                        } else {
                            iziToast.error({
                                message: result["message"],
                            });
                        }
                        swal.close();
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
});

$(document).on("change", "input[type=radio][name=seller_id]", function () {
    $("input[type=checkbox]").attr("disabled", true);
    $(".check_create_order").removeAttr("disabled");
    var seller_id = $('input[type=radio][name="seller_id"]:checked').val();
    $("input[type=checkbox][id='" + seller_id + "']").removeAttr("disabled");
});

$(".check_create_order").on("change", function (e) {
    e.preventDefault();
    if ($(this).is(":checked")) {
        $(".create_shiprocket_order").attr("disabled", false);
        var pickup_location = $(this).attr("id");
        var seller_id = $(this).data("id");
        $("#pickup_location").attr("value", pickup_location);
        $('input[type=hidden][name="shiprocket_seller_id"]').val(seller_id);
    } else {
        $(".create_shiprocket_order").attr("disabled", true);
    }
});

$(document).on("submit", "#shiprocket_order_parcel_form", function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    var fromAdmin = $("#fromadmin").val();
    var fromSeller = $("#fromseller").val();
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    var formdata = new FormData(this);
    formdata.append("_token", csrfToken);
    if (fromSeller != "undefined" && fromSeller == 1) {
        var url = appUrl + "seller/orders/create_shiprocket_order";
    }
    if (fromAdmin != "undefined" && fromAdmin == 1) {
        var url = appUrl + "admin/orders/create_shiprocket_order";
    }
    $(".create_shiprocket_parcel")
        .html("Please Wait...")
        .attr("disabled", true);
    $.ajax({
        type: "POST",
        url: url,
        dataType: "json",
        data: formData,
        processData: false,
        contentType: false,
        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");
            if (result.error == false) {
                iziToast.success({
                    message: result.message,
                });
                location.reload();
            } else {
                $.each(result.message, function (index, errorMessage) {
                    iziToast.error({
                        title: "Error",
                        message: errorMessage,
                        position: "topRight",
                    });
                });
                $(".create_shiprocket_parcel")
                    .html("Create Order")
                    .attr("disabled", false);
            }
        },
    });
});

$(".generate_awb").on("click", function (e) {
    e.preventDefault();

    var shipment_id = $(this).attr("id");
    var fromSeller = $(this).data("fromseller");
    var fromAdmin = $(this).data("fromadmin");
    if (fromSeller != "undefined" && fromSeller == 1) {
        var url = appUrl + "seller/orders/generate_awb";
    }
    if (fromAdmin != "undefined" && fromAdmin == 1) {
        var url = appUrl + "admin/orders/generate_awb";
    }
    Swal.fire({
        title: "Are You Sure !",
        text: "you want to generate AWb!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, generate AWB!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        shipment_id: shipment_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (result, textStatus) {
                        if (result["error"] == false) {
                            Swal.fire(
                                "AWB Generated!",
                                result["message"],
                                "success"
                            );
                            location.reload();
                        } else {
                            Swal.fire("Oops...", result["message"], "warning");
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$(".send_pickup_request").on("click", function (e) {
    e.preventDefault();
    var shipment_id = $(this).attr("name");
    var fromSeller = $(this).data("fromseller");
    var fromAdmin = $(this).data("fromadmin");
    if (fromSeller != "undefined" && fromSeller == 1) {
        var url = appUrl + "seller/orders/send_pickup_request";
    }
    if (fromAdmin != "undefined" && fromAdmin == 1) {
        var url = appUrl + "admin/orders/send_pickup_request";
    }
    Swal.fire({
        title: "Are You Sure !",
        text: "you want to send pickup request!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, send request!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        shipment_id: shipment_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (result, textStatus) {
                        if (result["error"] == false) {
                            Swal.fire(
                                "Request send!",
                                result["message"],
                                "success"
                            );
                            location.reload();
                        } else {
                            Swal.fire("Oops...", result["message"], "warning");
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$(".cancel_shiprocket_order").on("click", function (e) {
    e.preventDefault();
    let shiprocket_order_id = $("#shiprocket_order_id").val();
    if (
        shiprocket_order_id == undefined ||
        shiprocket_order_id == null ||
        shiprocket_order_id == ""
    ) {
        iziToast.error({
            message: "Shiprocket Order Id Not Found",
        });
        return;
    }
    Swal.fire({
        title: "Are You Sure !",
        text: "you want to cancel order!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, cancel it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + from + "/orders/cancel_shiprocket_order",
                    data: {
                        shiprocket_order_id: shiprocket_order_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (result, textStatus) {
                        if (result["error"] == false) {
                            Swal.fire(
                                "Order cancelled !",
                                result["message"],
                                "success"
                            );
                            location.reload();
                        } else {
                            Swal.fire("Oops...", result["message"], "warning");
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$(".generate_label").on("click", function (e) {
    e.preventDefault();
    var shipment_id = $(this).attr("name");
    var fromSeller = $(this).data("fromseller");
    var fromAdmin = $(this).data("fromadmin");
    if (fromSeller != "undefined" && fromSeller == 1) {
        var url = appUrl + "seller/orders/generate_label";
    }
    if (fromAdmin != "undefined" && fromAdmin == 1) {
        var url = appUrl + "admin/orders/generate_label";
    }
    Swal.fire({
        title: "Are You Sure !",
        text: "you want to generate label!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, generate label!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        shipment_id: shipment_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (result, textStatus) {
                        if (result["error"] == false) {
                            Swal.fire(
                                "Label generated!",
                                result["message"],
                                "success"
                            );
                            location.reload();
                        } else {
                            Swal.fire("Oops...", result["message"], "warning");
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$(".generate_invoice").on("click", function (e) {
    e.preventDefault();
    var order_id = $(this).attr("name");
    var fromSeller = $(this).data("fromseller");
    var fromAdmin = $(this).data("fromadmin");
    if (fromSeller != "undefined" && fromSeller == 1) {
        var url = appUrl + "seller/orders/generate_invoice";
    }
    if (fromAdmin != "undefined" && fromAdmin == 1) {
        var url = appUrl + "admin/orders/generate_invoice";
    }
    Swal.fire({
        title: "Are You Sure !",
        text: "you want to generate invoice!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, generate invoice!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {
                        order_id: order_id,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                })
                    .done(function (result, textStatus) {
                        if (result["error"] == false) {
                            Swal.fire(
                                "Invoice generated!",
                                result["message"],
                                "success"
                            );
                            location.reload();
                        } else {
                            Swal.fire("Oops...", result["message"], "warning");
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$("#order_tracking_form").on("submit", function (e) {
    e.preventDefault();
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;

    var formdata = new FormData(this);
    formdata.append("_token", csrfToken);

    $.ajax({
        type: "POST",
        url: $(this).attr("action"),
        data: formdata,
        beforeSend: function () {
            $("#submit_btn").html("Please Wait..").attr("disabled", true);
        },
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");
            $("#submit_btn").html("Update Details").attr("disabled", false);
            if (result.error == false) {
                iziToast.success({
                    message: result.message,
                    position: "topRight",
                });
                $("table").bootstrapTable("refresh");
                setTimeout(function () {
                    location.reload();
                }, 1000);
                $("#order_tracking_modal").modal("hide");
            }
            if (result.error === true) {
                const messageToShow = result.message || result.error_message;

                if (Array.isArray(messageToShow)) {
                    $.each(messageToShow, function (index, errorMessage) {
                        iziToast.error({
                            title: "Error",
                            message: errorMessage,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: "Error",
                        message: messageToShow,
                        position: "topRight",
                    });
                }
            }
        },
    });
});

$(document).on("click", ".edit_seller_order_tracking", function () {
    console.log("here");
    var parcelId = $(this).data("id");

    $.ajax({
        url: appUrl + "seller/orders/get_order_tracking",
        type: "GET",
        data: { parcel_id: parcelId },
        success: function (response) {
            if (!response.error) {
                // Populate the modal fields with existing data
                $('input[name="parcel_id"]').val(parcelId);
                $("#courier_agency").val(response.data.courier_agency);
                $("#tracking_id").val(response.data.tracking_id);
                $("#url").val(response.data.url);
            } else {
                // If no data, clear the modal fields
                $('input[name="parcel_id"]').val(parcelId);
                $("#courier_agency").val("");
                $("#tracking_id").val("");
                $("#url").val("");
            }
        },
    });
});

$("#system-update").on("submit", function (e) {
    e.preventDefault();
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    var formdata = new FormData(this);
    formdata.append("_token", csrfToken);
    $.ajax({
        type: "POST",
        url: $(this).attr("action"),
        data: formdata,
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (response) {
            if (response.error == true) {
                if (response.message instanceof Array) {
                    $.each(response.message, function (key, value) {
                        iziToast.error({
                            message: value,
                            position: "topRight",
                        });
                        return false;
                    });
                } else {
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                }
                return false;
            }
            iziToast.success({
                message: response.message,
                position: "topRight",
            });
        },
    });
});

$(document).on("click", ".edit_order_tracking", function (e, rows) {
    e.preventDefault();

    var order_item_id = $(this).data("order_item_id");
    var parcel_id = $(this).data("id");
    var order_id = $(this).data("order_id");

    if ($('input[type=radio][name="seller_id"]:checked').val() != undefined) {
        var seller_id = $('input[type=radio][name="seller_id"]:checked').val();
    } else {
        var seller_id = $(this).data("seller_id");
    }
    var order_item_id = $(this).data("order_item_id");
    var courier_agency = $(this).data("courier_agency");
    var tracking_id = $(this).data("tracking_id");
    var url = $(this).data("url");

    $("#order_item_id").val(order_item_id);
    $('input[name="order_id"]').val(order_id);
    $('input[name="parcel_id"]').val(parcel_id);
    $('input[name="order_item_id"]').val(order_item_id);
    $('input[type=hidden][name="seller_id"]').val(seller_id);
    $("#order_id").val(order_id);
    $("#order_item_id").val(order_item_id);
    $("#courier_agency").val(courier_agency);
    $("#tracking_id").val(tracking_id);
    $("#url").val(url);
    $("#admin_order_tracking_table").bootstrapTable("refresh");
    $("#order_item_table").bootstrapTable("refresh");
    $(".modal").modal("hide");
});

$("#seller_return_request_table").on(
    "click-cell.bs.table",
    function (field, value, row, $el) {

        $('input[name="return_request_id"]').val($el.id);
        $("#user_id").val($el.user_id);
        $("#order_item_id").val($el.order_item_id);
        $("#update_remarks").html($el.remarks);
        $("#deliver_by").val($el.delivery_boy_id);
        if ($el.status_digit == 0) {
            $(".pending").prop("checked", true);
            $("#return_request_delivery_by").addClass("d-none");
            $("#return_pickedup_label").addClass("disabled");
            $("#returned_label").addClass("disabled");
            $("#pending_label").removeClass("disabled");
        } else if ($el.status_digit == 1) {
            $(".approved").prop("checked", true);
            $("#return_request_delivery_by").removeClass("d-none");
            $("#return_pickedup_label").removeClass("disabled");
            $("#returned_label").removeClass("disabled");
            $("#rejected_label").addClass("disabled");
            $("#pending_label").addClass("disabled");
        } else if ($el.status_digit == 2) {
            $(".rejected").prop("checked", true);
            $("#return_request_delivery_by").addClass("d-none");
            $("#return_pickedup_label").addClass("disabled");
            $("#pending_label").addClass("disabled");
            $("#approved_label").addClass("disabled");
            $("#returned_label").addClass("disabled");
            $("#rejected_label").removeClass("disabled");
            // $("#pending_label").removeClass("disabled");
        } else if ($el.status_digit == 3) {
            $(".returned").prop("checked", true);
            $("#return_request_delivery_by").addClass("d-none");
            $("#rejected_label").addClass("disabled");
            $("#pending_label").addClass("disabled");
            $("#return_pickedup_label").addClass("disabled");
            $("#approved_label").addClass("disabled");
            $("#returned_label").removeClass("disabled");
        } else if ($el.status_digit == 8) {
            $(".return_pickedup").prop("checked", true);
            $("#return_request_delivery_by").addClass("d-none");
            $("#return_pickedup_label").removeClass("disabled");
            $("#returned_label").removeClass("disabled");
            $("#rejected_label").addClass("disabled");
            $("#pending_label").addClass("disabled");
        }
    }
);

$("input[type=radio][name=status]").on("change", function (e) {
    var status = $('input[type=radio][name="status"]:checked').val();
    if (status == 0) {
        $("#return_request_delivery_by").addClass("d-none");
    } else if (status == 1) {
        $("#return_request_delivery_by").removeClass("d-none");
    } else if (status == 2) {
        $("#return_request_delivery_by").addClass("d-none");
    } else if (status == 3) {
        $("#return_request_delivery_by").addClass("d-none");
    } else if (status == 8) {
        $("#return_request_delivery_by").addClass("d-none");
    }
});

$("#admin_payment_request_table").on(
    "click-cell.bs.table",
    function (field, value, row, $el) {
        $('input[name="payment_request_id"]').val($el.id);
        $("#update_remarks").html($el.remarks);

        if ($el.status_digit == 0) {
            $(".pending").prop("checked", true);
        } else if ($el.status_digit == 1) {
            $(".approved").prop("checked", true);
        } else if ($el.status_digit == 2) {
            $(".rejected").prop("rejected", true);
        }
    }
);

$(document).on("click", ".edit_stock", function (e, rows) {
    var vaiant_id = $(this).data("id");

    $.ajax({
        type: "GET",
        url: appUrl + "admin/manage_stock",
        data: {
            edit_id: vaiant_id,
        },
        dataType: "json",

        success: function (result) {
            var attributeValue =
                result.attribute != undefined && result.attribute !== ""
                    ? result.attribute
                    : "";
            var productName =
                result.fetched_data.product[0].name != undefined
                    ? result.fetched_data.product[0].name
                    : "";
            var stockType =
                result.fetched_data.product[0].stock_type != undefined &&
                    result.fetched_data.product[0].stock_type != 1
                    ? result.fetched_data.product[0].name
                    : "";

            var pname =
                attributeValue && stockType
                    ? productName + " - " + attributeValue
                    : productName;
            var stock =
                result.fetched_data.product[0].stock != undefined &&
                    result.fetched_data.product[0].stock !== ""
                    ? result.fetched_data.product[0].stock
                    : result.fetched;
            $('input[name="product_name"]').val(pname);
            $('input[name="current_stock"]').val(stock);
            $('input[name="variant_id"]').val(vaiant_id);
        },
    });
});

// change event for store

// Handle location selection
$("#store-dropdown").on("click", "li", function () {
    var store_id = $(this).data("store-id");
    var store_name = $(this).data("store-name");
    var store_image = $(this).data("store-image");

    token = $('meta[name="csrf-token"]').attr("content");
    $.ajax({
        type: "POST",
        url: "/" + from + "/set_store",
        data: {
            _token: token,
            store_id: store_id,
            store_name: store_name,
            store_image: store_image,
        },
        success: function (data) {
            if (data) {
            } else {
                iziToast.error({
                    message: "Error In Setting Store",
                    position: "topRight",
                });
            }
            location.reload();
        },
    });
});

// fetch stores

$(".search_stores").each(function () {
    $(this).select2({
        ajax: {
            url: appUrl + "admin/store/get_stores_list",
            dataType: "json",
            delay: 250,
            data: function (data) {
                return {
                    search: data.term,
                    limit: 5,
                };
            },
            processResults: function (response) {
                return {
                    results: response.results,
                };
            },
            cache: true,
        },
        escapeMarkup: function (markup) {
            return markup;
        },

        placeholder: $(this).data("placeholder") || "Search for products",
        width: $(this).data("width")
            ? $(this).data("width")
            : $(this).hasClass("w-100")
                ? "100%"
                : "style",
        allowClear: Boolean($(this).data("allow-clear")),
    });
});

function updateColorCode(colorPickerId) {
    // Get the selected color picker and corresponding input field
    var colorPicker = document.getElementById(colorPickerId);
    var colorCodeInput = document.getElementById(colorPickerId + "_code");

    // Update the corresponding input field with the selected color
    if (colorPicker && colorCodeInput) {
        colorCodeInput.value = colorPicker.value;
    }
}

function updateColorPicker(colorPickerId, colorCode) {
    // Update the corresponding color picker with the value from the input field
    var colorPicker = document.getElementById(colorPickerId);
    if (colorPicker) {
        colorPicker.value = colorCode;
    }
}

// category fetch for category sliders

$(".category_sliders_category").select2({
    ajax: {
        url: appUrl + from + "/categories/categories_data",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                term: params.term,
                page: params.page || 1,
                limit: 10,
            };
        },
        processResults: function (data, params) {
            // Adjust page for infinite scroll
            params.page = params.page || 1;

            return {
                results: data.results,
                pagination: {
                    more: params.page * 10 < data.total,
                },
            };
        },
    },
    templateResult: formatCategories,
    placeholder: "Search for categories",
});

function formatCategories(category) {
    // console.log(category);
    if (!category.id) {
        return category.text;
    }
    if (category.loading) return category.text;
    console.log(category.text);
    var image = category.image;
    var $category = $(
        '<div class="row">' +
        '<div class="col-md-1 align-self-center">' +
        '<div class="">' +
        '<img class="img-fluid" src="' +
        image +
        '"></div>' +
        "</div>" +
        '<div class="col-md-4 category-name mt-4">' +
        category.text +
        "</div>" +
        "</div>" +
        "</div>"
    );

    return $category;
}

function formatCategoriesSelection(category) {
    // console.log(category);
    if (category.element.dataset.select2Text === undefined) {
        var image = category.image;
        var $category = $(
            '<div class="row">' +
            '<div class="col-md-1 align-self-center">' +
            '<div class="">' +
            '<img class="img-fluid" src="' +
            image +
            '"></div>' +
            "</div>" +
            '<div class="col-md-4 category-name mt-4">' +
            category.text +
            "</div>" +
            "</div>" +
            "</div>"
        );
    } else {
        $category = category.element.dataset.select2Text;
    }
    return $category;
}

//offer fetch for offer sliders

$(".offer_sliders_offer").select2({
    ajax: {
        url: appUrl + from + "/offers/offers_data",
        dataType: "json",
        delay: 250,
        processResults: function (data) {
            return {
                results: data.results,
            };
        },
    },
    templateResult: formatOffers,
    templateSelection: formatoffersSelection,
    placeholder: "Search for offers",
});

function formatOffers(offer) {
    if (!offer.id) {
        return offer.text;
    }
    if (offer.loading) return offer.text;

    var image = offer.image;

    var $offer = $(
        '<div class="row">' +
        '<div class="col-md-1 align-self-center">' +
        '<div class="">' +
        '<img class="img-fluid" src="' +
        image +
        '"></div>' +
        "</div>" +
        '<div class="align-self-center col-md-10">' +
        '<div class="">' +
        (offer.min_discount != 0 && offer.max_discount != 0
            ? "Min - Max Discount : " +
            offer.min_discount +
            "% - " +
            offer.max_discount +
            "%"
            : "") +
        "</div>" +
        '<small class="">ID - ' +
        offer.id +
        " </small> |" +
        '<small class="">Type - ' +
        offer.text +
        " </small> " +
        "</div>" +
        "</div>"
    );

    return $offer;
}

function formatoffersSelection(offer) {
    if (offer.element.dataset.select2Text === undefined) {
        var $offer = $(
            '<div class="row">' +
            '<div class="col-md-1 align-self-center">' +
            '<div class="">' +
            '<img class="img-fluid" src="' +
            offer.image +
            '"></div>' +
            "</div>" +
            '<div class="align-self-center col-md-10">' +
            '<div class="">' +
            (offer.min_discount != 0 &&
                offer.max_discount != 0 &&
                offer.min_discount != undefined &&
                offer.max_discount != undefined
                ? "Min - Max Discount : " +
                offer.min_discount +
                "% - " +
                offer.max_discount +
                "%"
                : "") +
            "</div>" +
            '<small class="">ID - ' +
            offer.id +
            " </small> |" +
            '<small class="">Type - ' +
            offer.text +
            " </small> " +
            "</div>" +
            "</div>"
        );
    } else {
        $offer = offer.element.dataset.select2Text;
    }
    return $offer;
}

//zones fetch for select zones

$(".search_zone").select2({
    ajax: {
        url: appUrl + from + "/zones/zones_data",
        dataType: "json",
        delay: 250,
        processResults: function (data) {
            return {
                results: data.results,
                seller_id: $('input[name="seller_id"]').val(),
            };
        },
    },
    templateResult: formatZones,
    templateSelection: formatzonesSelection,
    placeholder: "Search for zones",
});

$(".search_all_zone").select2({
    ajax: {
        url: appUrl + from + "/get_zones",
        dataType: "json",
        delay: 250,
        processResults: function (data) {
            return {
                results: data.results,
                seller_id: $('input[name="seller_id"]').val(),
            };
        },
    },
    templateResult: formatZones,
    templateSelection: formatzonesSelection,
    placeholder: "Search for zones",
});

$(".search_seller_zone").select2({
    ajax: {
        url: appUrl + from + "/zones/seller_zones_data",
        dataType: "json",
        delay: 250,
        data: function (params) {
            var data = {
                q: params.term,
                // seller_id: $("#seller_id").val(),
                seller_id: $('input[name="seller_id"]').val(),
            };
            return data;
        },
        processResults: function (data) {
            return {
                results: data.results,
            };
        },
    },
    templateResult: formatZones,
    templateSelection: formatzonesSelection,
    placeholder: "Search for zones",
});

function formatZones(zone) {
    if (!zone.id) {
        return zone.text;
    }
    if (zone.loading) return zone.text;

    var $zone = $(
        '<div class="row">' +
        '<div class="align-self-center col-md-10">' +
        '<small class="">ID - ' +
        zone.id +
        " </small> | " +
        '<small class="">Name - ' +
        zone.text +
        " </small> " +
        '<div class="">' +
        (zone.serviceable_cities
            ? "Serviceable Cities: " + zone.serviceable_cities + " | "
            : "") +
        (zone.serviceable_zipcodes
            ? "Serviceable Zipcodes: " + zone.serviceable_zipcodes
            : "") +
        "</div>" +
        "</div>" +
        "</div>"
    );

    return $zone;
}

function formatzonesSelection(zone) {
    if (zone.element.dataset.select2Text === undefined) {
        var $zone = $(
            '<div class="row">' +
            '<div class="align-self-center col-md-10">' +
            '<small class="">ID - ' +
            zone.id +
            " </small> | " +
            '<small class="">Name - ' +
            zone.text +
            " </small> " +
            '<div class="">' +
            (zone.serviceable_cities
                ? "Serviceable Cities: " + zone.serviceable_cities + " | "
                : "") +
            (zone.serviceable_zipcodes
                ? "Serviceable Zipcodes: " + zone.serviceable_zipcodes
                : "") +
            "</div>" +
            "</div>" +
            "</div>"
        );
    } else {
        $zone = zone.element.dataset.select2Text;
    }
    return $zone;
}

$("#offer_type").on("change", function (e) {
    e.preventDefault();

    if (
        $("#offer_type").val() == "categories" ||
        $("#offer_type").val() == "all_products" ||
        $("#offer_type").val() == "all_combo_products" ||
        $("#offer_type").val() == "brand"
    ) {
        $("#min_max_section").removeClass("d-none");
    } else {
        $("#min_max_section").addClass("d-none");
    }
});

$(function () {
    if (
        $("#offer_type").val() == "categories" ||
        $("#offer_type").val() == "all_products" ||
        $("#offer_type").val() == "all_combo_products" ||
        $("#offer_type").val() == "brand"
    ) {
        $("#min_max_section").removeClass("d-none");
    }
});

// Function to show the image corresponding to the selected style
function show_selected_style(selected_element, selected_image) {
    const selected_style = selected_element.val();

    // Hide all images
    selected_image.hide();

    // Show the image corresponding to the selected style
    $(`.${selected_style}`).show();
}

// Define category styles and category sliders
const category_style = $(".category_style");
const category_style_images = $(".category_style_images img");

const category_slider_style = $(".category_slider_style");
const category_slider_style_images = $(".category_slider_style_images img");

// feature section style

const feature_section_style = $(".feature_section_style");
const feature_section_style_images = $(".feature_section_style_images img");

const feature_section_header_style = $(".feature_section_header_style");

const web_home_page_theme = $(".web_home_page_theme");

const web_product_details_style = $(".web_product_details_style");

const feature_section_header_style_images = $(
    ".feature_section_header_style_images img"
);
const web_home_page_theme_images = $(".web_home_page_theme_images img");
const web_product_details_style_images = $(
    ".web_product_details_style_images img"
);
const product_card_style = $(".product_card_style");
const product_card_style_images = $(".product_card_style_images img");
const categories_style = $(".categories_style");
const categories_style_images = $(".categories_style_images img");
const categories_card_style = $(".categories_card_style");
const categories_card_style_images = $(".categories_card_style_images img");
const brands_style = $(".brands_style");
const brands_style_images = $(".brands_style_images img");
const banner_style = $(".banner_style");
const banner_style_images = $(".banner_style_images img");
const slider_style = $(".slider_style");
const slider_style_images = $(".slider_style_images img");

// Event listeners for category styles and category sliders
category_style.on("change", function () {
    show_selected_style(category_style, category_style_images);
});

category_slider_style.on("change", function () {
    show_selected_style(category_slider_style, category_slider_style_images);
});

feature_section_style.on("change", function () {
    show_selected_style(feature_section_style, feature_section_style_images);
});

feature_section_header_style.on("change", function () {
    show_selected_style(
        feature_section_header_style,
        feature_section_header_style_images
    );
});

web_home_page_theme.on("change", function () {
    show_selected_style(web_home_page_theme, web_home_page_theme_images);
});

web_product_details_style.on("change", function () {
    show_selected_style(
        web_product_details_style,
        web_product_details_style_images
    );
});

product_card_style.on("change", function () {
    show_selected_style(product_card_style, product_card_style_images);
});
categories_style.on("change", function () {
    show_selected_style(categories_style, categories_style_images);
});
categories_card_style.on("change", function () {
    show_selected_style(categories_card_style, categories_card_style_images);
});
brands_style.on("change", function () {
    show_selected_style(brands_style, brands_style_images);
});
banner_style.on("change", function () {
    show_selected_style(banner_style, banner_style_images);
});
slider_style.on("change", function () {
    show_selected_style(slider_style, slider_style_images);
});

// Hide all images on page load (initially)
category_style_images.hide();
category_slider_style_images.hide();
feature_section_style_images.hide();
feature_section_header_style_images.hide();
web_home_page_theme_images.hide();
web_product_details_style_images.hide();
product_card_style_images.hide();
categories_style_images.hide();
categories_card_style_images.hide();
brands_style_images.hide();
banner_style_images.hide();

// Show the selected style image when the page loads
show_selected_style(category_style, category_style_images);
show_selected_style(category_slider_style, category_slider_style_images);
show_selected_style(feature_section_style, feature_section_style_images);
show_selected_style(product_card_style, product_card_style_images);
show_selected_style(categories_style, categories_style_images);
show_selected_style(categories_card_style, categories_card_style_images);
show_selected_style(brands_style, brands_style_images);
show_selected_style(banner_style, banner_style_images);
show_selected_style(slider_style, slider_style_images);
show_selected_style(
    feature_section_header_style,
    feature_section_header_style_images
);
show_selected_style(web_home_page_theme, web_home_page_theme_images);
show_selected_style(
    web_product_details_style,
    web_product_details_style_images
);

// set default store

$(document).on("click", ".set_default_store", function () {
    var id = $(this).data("id");
    var status = $(this).data("store-status");
    var url = $(this).data("url");

    $.ajax({
        method: "GET",
        url: url,
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        success: function (response) {
            if (response.error_message) {
                iziToast.error({
                    title: "Error",
                    message: response.error_message,
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            } else {
                iziToast.success({
                    title: "Success",
                    message: "Set as Default Store Successfully",
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            }
        },
        fail: function (response) {
            iziToast.error({
                title: "Error",
                message: "Something Went Wrong!!",
                position: "topRight",
            });
        },
    });
});

// product type in combo products

$(document).on("change", 'input[name="product_type_menu"]', function (e, data) {
    var value = $('input[name="product_type_menu"]:checked').val();
    if (value == "digital_product") {
        var html = '<option value="digital_product">Digital Product</option>';
        $("#product-type").html(html);

        $(".product_quantity_and_others").addClass("d-none");
        $(".delivery_and_shipping_settings").addClass("d-none");
    } else {
        var html =
            ' <option value=" ">Select Type</option>' +
            '<option value="simple_product">Simple Product</option>' +
            '<option value="variable_product">Variable Product</option>';
        $("#product-type").html(html);
        $(".product_quantity_and_others").removeClass("d-none");
        $(".delivery_and_shipping_settings").removeClass("d-none");
        $("#digital_product_setting").hide(200);
    }
});
$(document).on(
    "change",
    'input[name="product_type_in_combo"]',
    function (e, data) {
        var value = $('input[name="product_type_in_combo"]:checked').val();

        if (value == "digital_product") {
            var html =
                '<option value="digital_product">Digital Product</option>';
            $("#product-type").html(html);

            $(".digital_product_in_combo").removeClass("d-none");

            $(".physical_product_in_combo").addClass("d-none");

            $(".combo_product_quantity_and_others").addClass("d-none");
            $(".combo_delivery_and_shipping_setting").addClass("d-none");
        } else {
            var html =
                ' <option value=" ">Select Type</option>' +
                '<option value="simple_product">Simple Product</option>' +
                '<option value="variable_product">Variable Product</option>';
            $(".physical_product_in_combo").removeClass("d-none");
            $(".digital_product_in_combo").addClass("d-none");
            $(".combo_product_quantity_and_others").removeClass("d-none");
            $(".combo_delivery_and_shipping_setting").removeClass("d-none");
        }
    }
);

// auto filled value of selected number of product in combo

function update_selected_products_count(select_box, output_field) {
    var selected_options = select_box.val();
    output_field.val(selected_options ? selected_options.length : 0);
}

$(".search_admin_product, .search_admin_digital_product").on(
    "change",
    function () {
        update_selected_products_count(
            $(this),
            $(".selected_products_in_combo")
        );
    }
);
$(".search_seller_product, .search_seller_digital_product").on(
    "change",
    function () {
        update_selected_products_count(
            $(this),
            $(".selected_products_in_combo")
        );
    }
);

var d_boy_cash = 0;
$("#delivery_boys_details").on("check.bs.table", function (e, row) {
    d_boy_cash = row.cash_received;
    $("#details").val(
        "Id: " +
        row.id +
        " | Name:" +
        row.username +
        " | Mobile: " +
        row.mobile +
        " | Cash: " +
        row.cash_received
    );
    $("#delivery_boy_id").val(row.id);
});

function validate_amount() {
    var cash = $(".delivery_boy_cash_recived").val();
    var amount = $("#amount").val();
    var details_val = $("#details").val();
    if (details_val == "") {
        iziToast.error({
            message:
                "<span>you have to select delivery boy to collect cash.</span> ",
        });
        $("#amount").val("");
    } else {
        if (parseInt(cash) > 0) {
            if (parseInt(amount) > parseInt(cash)) {
                iziToast.error({
                    message:
                        "<span>You Can not enter amount greater than cash</span> ",
                });
                $("#amount").val("");
            }
            if (parseInt(amount) <= 0) {
                iziToast.error({
                    message: "<span>Amount must be greater than zero</span> ",
                });
                $("#amount").val("");
            }
        } else {
            iziToast.error({
                message: "<span>Cash must be greater than zero</span> ",
            });
            $("#amount").val("");
        }
    }
}

$("#admin_delivery_boys_table").on(
    "click-cell.bs.table",
    function (field, value, row, $el) {
        $("#name").val($el.username);
        $("#mobile").val($el.mobile);
        $("#balance").val($el.balance);
        $("#delivery_boy_id").val($el.id);
        $("#email").val($el.email);
    }
);

$(document).on("click", ".edit_delivery_boy", function (e, rows) {
    var id = $(this).data("id");

    $.ajax({
        type: "GET",
        url: appUrl + "admin/delivery_boys",
        data: {
            edit_id: id,
        },
        dataType: "json",

        success: function (result) {
            var name =
                result.userData.username != undefined &&
                    result.userData.username !== ""
                    ? result.userData.username
                    : "";
            var mobile =
                result.userData.mobile != undefined &&
                    result.userData.mobile !== ""
                    ? result.userData.mobile
                    : "";
            var email =
                result.userData.email != undefined &&
                    result.userData.email !== ""
                    ? result.userData.email
                    : "";
            var address =
                result.userData.address != undefined &&
                    result.userData.address !== ""
                    ? result.userData.address
                    : "";
            var bonus_type =
                result.userData.bonus_type != undefined &&
                    result.userData.bonus_type !== ""
                    ? result.userData.bonus_type
                    : "";
            var bonus =
                result.userData.bonus != undefined &&
                    result.userData.bonus !== ""
                    ? result.userData.bonus
                    : 0;

            var front_licence_image =
                result.userData.front_licence_image.indexOf("https:") === -1
                    ? result.userData.front_licence_image !== ""
                        ? appUrl +
                        "storage/delivery_boys" +
                        result.userData.front_licence_image
                        : appUrl + "assets/img/no-image.jpg"
                    : result.userData.front_licence_image;

            var back_licence_image =
                result.userData.back_licence_image.indexOf("https:") === -1
                    ? result.userData.front_licence_image !== ""
                        ? appUrl +
                        "storage/delivery_boys" +
                        result.userData.back_licence_image
                        : appUrl + "assets/img/no-image.jpg"
                    : result.userData.back_licence_image;

            $('input[name="name"]').val(name);
            $('input[name="mobile"]').val(mobile);
            $('input[name="email"]').val(email);
            $('textarea[name="address"]').val(address);
            $(".bonus_type").val(bonus_type);
            if (
                bonus_type == "fixed_amount_per_order_item" &&
                bonus_type != " "
            ) {
                $(".edit_fixed_amount_per_order_item").removeClass("d-none");
                $('input[name="bonus_amount"]').val(bonus);
            } else {
                $(".edit_fixed_amount_per_order_item").addClass("d-none");
            }
            if (
                bonus_type == "percentage_per_order_item" &&
                bonus_type != " "
            ) {
                $(".edit_percentage_per_order_item").removeClass("d-none");
                $('input[name="bonus_percentage"]').val(bonus);
            } else {
                $(".edit_percentage_per_order_item").addClass("d-none");
            }

            $(".edit_front_licence_image").attr("src", front_licence_image);
            $(".edit_back_licence_image").attr("src", back_licence_image);

            $(".edit_serviceable_zones").empty();
            $.each(result.zones_name, function (e, i) {
                "";
                $(".edit_serviceable_zones").append(
                    $("<option>", {
                        value: i["id"],
                        text: i["data"],
                        selected: true,
                    })
                );
            });

            $(".edit_serviceable_cities").empty();

            $.each(result.cities_name, function (e, i) {
                $(".edit_serviceable_cities").append(
                    $("<option>", {
                        value: i["id"],
                        text: i["name"],
                        selected: true, // Set selected attribute for the desired options
                    })
                );
            });
            $(".submit_form").attr(
                "action",
                "/admin/delivery_boys/update/" + result.userData.id
            );
        },
    });
});

$(window).on("load", function () {
    var store_ids = $('input[name="edit_store_ids[]"]').val();
    if (store_ids != null && store_ids != undefined) {
        store_ids = store_ids.split(",");
        var store_id = $('input[name="edit_store_id"]').val();
        var store_name = $('input[name="edit_store_name"]').val();
        var store_url = $('input[name="edit_store_url"]').val();
        var store_description = $('input[name="edit_store_description"]').val();
        var store_logo = $('input[name="edit_store_logo"]').val();
        var store_thumbnail = $('input[name="edit_store_thumbnail"]').val();
        var store_detail = [];
        store_detail = JSON.parse($('input[name="edit_store_detail[]"]').val());

        var html = "";
        for (var i = 0; i < store_ids.length; i++) {
            if (store_ids[i] != store_id) {
                var storeNamne =
                    store_detail[i]["id"] == store_ids[i]
                        ? store_detail[i]["name"]
                        : "";

                if (
                    JSON.parse(store_logo) != "" &&
                    JSON.parse(store_logo) != undefined
                ) {
                    var imageUrl =
                        appUrl +
                        "storage/" +
                        JSON.parse(store_logo)[store_ids[i]]["store_logo"];
                } else {
                    var imageUrl = "";
                }
                if (
                    JSON.parse(store_thumbnail) != "" &&
                    JSON.parse(store_thumbnail) != undefined
                ) {
                    var thumbnailUrl =
                        appUrl +
                        "storage/" +
                        JSON.parse(store_thumbnail)[store_ids[i]][
                        "store_thumbnail"
                        ];
                } else {
                    var thumbnailUrl = "";
                }
                imageUrl = html =
                    '<div class="divider_title">' +
                    storeNamne +
                    " Store Info" +
                    "</div><hr>" +
                    '<div class="row">' +
                    '<div class="mb-3 col-md-6">' +
                    '<label class="form-label" for="store_name">Name <span class="text-danger text-sm">*</span></label>' +
                    '<div class="input-group input-group-merge">' +
                    '<input type="text" name="store_name[]" class="form-control" placeholder="starbucks" value="' +
                    JSON.parse(store_name)[store_ids[i]]["store_name"] +
                    '" />' +
                    "</div>" +
                    "</div>" +
                    '<div class="mb-3 col-md-6">' +
                    '<label class="form-label" for="store_url">Store URL <span class="text-danger text-sm">*</span></label>' +
                    '<div class="input-group input-group-merge">' +
                    '<input type="text" name="store_url[]" class="form-control" placeholder="starbucks" value="' +
                    JSON.parse(store_url)[store_ids[i]]["store_url"] +
                    '" />' +
                    "</div>" +
                    "</div>" +
                    "</div >" +
                    '<div class="row">' +
                    '<div class="form-group col-md-6">' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="basic-default-phone">Logo <span class="text-danger text-sm">*</span></label>' +
                    '<input type="file" accept="image/*" id="basic-default-phone" name="store_logo[]" class="form-control " placeholder="658 799 8941">' +
                    '<img src="' +
                    imageUrl +
                    '" alt="user-avatar" class="d-block rounded mt-2" height="100" width="100" id="uploadedAvatar" />' +
                    "</div>" +
                    "</div>" +
                    '<div class="form-group col-md-6">' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="basic-default-phone">Store Thumbnail <span class="text-danger text-sm">*</span></label>' +
                    '<input type="file" accept="image/*" id="basic-default-phone" name="store_thumbnail[]" class="form-control phone-mask">' +
                    '<img src="' +
                    thumbnailUrl +
                    '" alt="user-avatar" class="d-block rounded mt-2" height="100" width="100" id="uploadedAvatar" />' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="row">' +
                    '<div class="form-group col-md-12">' +
                    '<div class="mb-3">' +
                    '<label class="form-label" for="basic-default-company">Description <span class="text-danger text-sm">*</span></label>' +
                    '<textarea id="basic-default-message" value="" name="description[]" class="form-control" placeholder="Write some description here">' +
                    JSON.parse(store_description)[store_ids[i]][
                    "store_description"
                    ] +
                    "</textarea>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div >";

                $("#edit_store_details").append(html);
            }
        }
    }
});

$(document).on("click", ".delete-img", function () {
    var isJson = false;
    var id = $(this).data("id");
    var path = $(this).data("path");
    var field = $(this).data("field");
    var img_name = $(this).data("img");
    var table_name = $(this).data("table");
    var t = this;
    var isjson = $(this).data("isjson");
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + from + "/products/delete_image",
                    data: {
                        id: id,
                        path: path,
                        field: field,
                        img_name: img_name,
                        table_name: table_name,
                        isjson: isjson,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                    success: function (result) {
                        token = $('meta[name="csrf-token"]').attr("content");
                        if (result[0]["is_deleted"] == true) {
                            $(t).closest("div").remove();
                            Swal.fire("Success", "Media Deleted !", "success");
                        } else {
                            Swal.fire(
                                "Oops...",
                                "Something went wrong !",
                                "error"
                            );
                        }
                    },
                });
            });
        },
        allowOutsideClick: false,
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Cancelled!", "Your data is  safe.", "error");
        }
    });
});

$(document).on("click", ".update-seller-commission", function () {
    Swal.fire({
        title: "Are You Sure !",
        text: "You won't be able to revert this!",
        type: "info",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, settle commission!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: appUrl + "admin/cronjob/settleSellerCommission",
                    type: "GET",
                    data: {
                        is_date: true,
                    },
                    dataType: "json",
                })
                    .done(function (response, textStatus) {
                        if (response.error == false) {
                            Swal.fire("Done!", response.message, "success");
                            $("table").bootstrapTable("refresh");
                        } else {
                            if (response.message) {
                                Swal.fire(
                                    "Oops...",
                                    response.message,
                                    "warning"
                                );
                            } else if (response.error_message) {
                                Swal.fire(
                                    "Oops...",
                                    response.error_message,
                                    "warning"
                                );
                            }
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});

$("#customers").on("check.bs.table", function (e, row) {
    $("#customer_dtls").val(row.name + " | " + row.email);
    $("#user_id").val(row.id);
});

$(function () {
    // Initialize Select2
    $("#customerSelect").select2({
        ajax: {
            url: "/customers/list",
            dataType: "json",
            data: {
                status: 1,
            },
            delay: 250,
            processResults: function (data) {
                return {
                    results: data.rows.map(function (row) {
                        return {
                            id: row.id,
                            text: row.name + " | " + row.email,
                        };
                    }),
                };
            },
            cache: true,
        },
        placeholder: "Select a customer",
        minimumInputLength: 1,
        dropdownParent: $(".customer_wallet_transaction_parent"),
    });

    // Handle selection change
    $("#customerSelect").on("select2:select", function (e) {
        var selectedUser = e.params.data;
        $("#user_id").val(selectedUser.id);
    });
});

$(function () {
    // Initialize Select2
    $("#delivery_boy_select").select2({
        ajax: {
            url: "/delivery_boys/list",
            dataType: "json",
            delay: 250,
            processResults: function (data) {
                return {
                    results: data.rows.map(function (row) {
                        return {
                            id: row.id,
                            cash_received: row.cash_received,
                            text:
                                row.username +
                                " | " +
                                row.email +
                                " | " +
                                row.mobile +
                                " | " +
                                row.cash_received,
                        };
                    }),
                };
            },
            cache: true,
        },
        placeholder: "Select a delivery boy",
        minimumInputLength: 1,
        dropdownParent: $(".delivery_boy_wallet_transaction_parent"),
    });

    // Handle selection change
    $("#delivery_boy_select").on("select2:select", function (e) {
        var selectedUser = e.params.data;

        $("#delivery_boy_id").val(selectedUser.id);
        $(".delivery_boy_cash_recived").val(selectedUser.cash_received);
    });
});

$(document).on("click", ".view_ticket", function (e, row) {
    e.preventDefault();
    scrolled = 0;
    $(".ticket_msg").data("max-loaded", false);
    ticket_id = $(this).data("id");
    var username = $(this).data("username");
    var date_created = $(this).data("date_created");
    var subject = $(this).data("subject");
    var status = $(this).data("status");
    var ticket_type = $(this).data("ticket_type");
    $('input[name="ticket_id"]').val(ticket_id);
    $("#user_name").html(username);
    $("#date_created").html(date_created);
    $("#subject").html(subject);
    $(".change_ticket_status").data("ticket_id", ticket_id);

    if (status == 1) {
        $('#status').html('<label class="badge bg-warning ml-2">PENDING</label>');
    } else if (status == 2) {
        $('#status').html('<label class="badge  bg-danger ml-2">OPENED</label>');
    } else if (status == 3) {
        $('#status').html('<label class="badge bg-success ml-2">RESOLVED</label>');
    } else if (status == 4) {
        $('#status').html('<label class="badge bg-dark ml-2">CLOSED</label>');
    } else if (status == 5) {
        $('#status').html('<label class="badge bg-primary ml-2">REOPENED</label>');
    }
    $("#ticket_type").html(ticket_type);
    $(".ticket_msg").html("");
    $(".ticket_msg").data("limit", 5);
    $(".ticket_msg").data("offset", 0);
    load_messages($(".ticket_msg"), ticket_id);
});

function load_messages(element, ticket_id) {
    var limit = element.data("limit");
    var offset = element.data("offset");

    element.data("offset", limit + offset);
    var max_loaded = element.data("max-loaded");
    if (max_loaded == false) {
        var loader =
            '<div class="loader text-center"><img src="' +
            appUrl +
            'assets/img/pre-loader.gif" alt="Loading. please wait.. ." title="Loading. please wait.. ."></div>';
        $.ajax({
            type: "get",
            data:
                "ticket_id=" +
                ticket_id +
                "&limit=" +
                limit +
                "&offset=" +
                offset,
            url: appUrl + "admin/tickets/get_ticket_messages",
            beforeSend: function () {
                $(".ticket_msg").prepend(loader);
            },
            dataType: "json",
            cache: false,
            contentType: false,
            processData: false,
            success: function (result) {
                if (result.error == false) {
                    if (result.error == false && result.data.length > 0) {
                        var messages_html = "";
                        var is_left = "";

                        var i = 1;
                        result.data.reverse().forEach((messages) => {
                            var atch_html = "";
                            is_left =
                                messages.user_type == "user" ? "left justify-content-start align-items-start" : "right justify-content-end align-items-end";

                            if (messages.attachments.length > 0) {
                                messages.attachments.forEach((atch) => {
                                    atch_html +=
                                        "<div class='image-upload-section d-flex " +
                                        (is_left === "left" ? "justify-content-start" : "justify-content-end") +
                                        "'>" + // Align based on `is_left`
                                        "<div class='col-md-12 col-sm-12 shadow mb-4 rounded text-center grow image'>" +
                                        "<a href='" + atch.media + "' target='_blank'>" +
                                        "<img src='" +
                                        atch.media +
                                        "' alt='Attachment Image' class='img-fluid rounded' style='max-width: 100%; height: 100px; object-fit: contain;' />" +
                                        "</a>" +
                                        "</div>" +
                                        "</div>";
                                    i++;
                                });
                            }

                            messages_html +=
                                "<div class='d-flex " + (messages.attachments.length > 0 ? '' : 'direct-chat-msg') + " flex-column " + is_left + "'>" +
                                "<div class='direct-chat-infos clearfix text-black-50'>" +
                                "<span class='direct-chat-name float-" + is_left + "' id='name'>" + " " + messages.name + " " + "</span>" +
                                "<span class='direct-chat-timestamp float-" + is_left + "' id='last_updated'>" + messages.updated_at + " " + "</span>" +
                                "</div>" +
                                "<div class='direct-chat-text float-" + is_left + "' id='message'>" + messages.message +
                                "</br>" +
                                atch_html +
                                "</div>" +
                                "</div>";
                        });
                        $(".ticket_msg").prepend(messages_html);
                        $(".ticket_msg").find(".loader").remove();
                        $(element).animate({
                            scrollTop: $(element).offset().top,
                        });
                    }
                } else {
                    element.data("offset", offset);
                    element.data("max-loaded", true);
                    $(".ticket_msg").find(".loader").remove();
                    $(".ticket_msg").prepend(
                        '<div class="text-center"> <p>You have reached the top most message!</p></div>'
                    );
                }
                $("#element").scrollTop(20); // Scroll alittle way down, to allow user to scroll more
                $(element).animate({
                    scrollTop: $(element).offset().top,
                });
                return false;
            },
        });
    }
}

$("#ticket_send_msg_form").on("submit", function (e) {
    e.preventDefault();
    var formdata = new FormData(this);
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    formdata.append("_token", csrfToken);

    $.ajax({
        type: "POST",
        url: $(this).attr("action"),
        data: formdata,
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");
            $("#submit_btn").html("Send").attr("disabled", false);
            if (result.error == false) {
                $(".product-image-container").remove();
                if (result.data.id > 0) {
                    var message = result.data;
                    var is_left =
                        message.user_type == "user" ? "left" : "right";
                    var message_html = "";
                    var atch_html = "";
                    var i = 1;
                    if (message.attachments.length > 0) {
                        message.attachments.forEach((atch) => {
                            atch_html +=
                                "<div class='container-fluid image-upload-section ms-1'>" +
                                "<a class='btn btn-danger btn-xs me-1 mb-1' href='" +
                                atch.media +
                                "'  target='_blank' alt='Attachment Not Found'>Attachment " +
                                i +
                                "</a>" +
                                "<div class='col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image d-none'></div>" +
                                "</div>";
                            i++;
                        });
                    }

                    message_html +=
                        "<div class='direct-chat-msg " +
                        is_left +
                        "'>" +
                        "<div class='direct-chat-infos clearfix'>" +
                        "<span class='direct-chat-name float-" +
                        is_left +
                        "' id='name'>" +
                        " " +
                        message.name +
                        " " +
                        "</span>" +
                        "<span class='direct-chat-timestamp float-" +
                        is_left +
                        "' id='last_updated'>" +
                        message.updated_at +
                        " " +
                        "</span>" +
                        "</div>" +
                        "<div class='direct-chat-text float-" +
                        is_left +
                        "'' id='message'>" +
                        message.message +
                        "</br>" +
                        atch_html +
                        "</div>" +
                        "</div>";

                    $(".ticket_msg").append(message_html);
                    $("#message_input").val("");

                    $("#element").scrollTop($("#element")[0].scrollHeight);
                    $('input[name="attachments[]"]').val("");
                } else {
                    // Handle the case when result.data.id is not greater than 0 (e.g., invalid data)
                    iziToast.error({
                        message:
                            '<span style="text-transform:capitalize">' +
                            result.data.message +
                            "</span>",
                    });
                }
            } else {
                // Handle the error case
                $("#element").data("max-loaded", true);
                iziToast.error({
                    message:
                        '<span style="text-transform:capitalize">' +
                        result.error_message +
                        "</span> ",
                });
                return false;
            }
            iziToast.success({
                message:
                    '<span style="text-transform:capitalize">' +
                    result.message +
                    "</span> ",
            });
        },
    });
});

$(function () {
    if ($("#element").length) {
        $("#element").scrollTop($("#element")[0].scrollHeight);
        $("#element").scroll(function () {
            if ($("#element").scrollTop() == 0) {
                console.log(ticket_id);

                load_messages($(".ticket_msg"), ticket_id);
            }
        });

        $("#element").bind("mousewheel", function (e) {
            if (e.originalEvent.wheelDelta / 120 > 0) {
                if ($(".ticket_msg")[0].scrollHeight < 370 && scrolled == 0) {
                    console.log(ticket_id);

                    load_messages($(".ticket_msg"), ticket_id);
                    scrolled = 1;
                }
            }
        });
    }
});

$(document).on("change", ".change_ticket_status", function () {
    var status = $(this).val();
    if (status != "") {
        if (
            confirm(
                "Are you sure you want to mark the ticket as " +
                $(".change_ticket_status option:selected").text() +
                "? "
            )
        ) {
            var id = $(this).data("ticket_id");
            var dataString = {
                ticket_id: id,
                status: status,
                _token: $('meta[name="csrf-token"]').attr("content"),
            };
            $.ajax({
                type: "post",
                url: appUrl + "admin/tickets/editTicketStatus",
                data: dataString,
                dataType: "json",
                success: function (result) {
                    token = $('meta[name="csrf-token"]').attr("content");
                    if (result.error == false) {
                        $("#ticket_table").bootstrapTable("refresh");
                        if (status == 1) {
                            $("#status").html(
                                '<label class="badge bg-secondary ml-2">PENDING</label>'
                            );
                        } else if (status == 2) {
                            $("#status").html(
                                '<label class="badge bg-info ml-2">OPENED</label>'
                            );
                        } else if (status == 3) {
                            $("#status").html(
                                '<label class="badge bg-success ml-2">RESOLVED</label>'
                            );
                        } else if (status == 4) {
                            $("#status").html(
                                '<label class="badge bg-danger ml-2">CLOSED</label>'
                            );
                        } else if (status == 5) {
                            $("#status").html(
                                '<label class="badge bg-warning ml-2">REOPENED</label>'
                            );
                        }
                        iziToast.success({
                            message:
                                '<span style="text-transform:capitalize">' +
                                result.message +
                                "</span> ",
                        });
                        $("#ticket_modal").modal("hide");
                        $("#admin_ticket_table").bootstrapTable("refresh");
                    } else {
                        iziToast.error({
                            message: "<span>" + result.message + "</span> ",
                        });
                    }
                },
            });
        }
    }
});

// First register any plugins
FilePond.registerPlugin(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateSize,
    FilePondPluginFileValidateType
);

// Turn input element into a pond

$(".filepond").filepond({
    credits: null,
    allowFileSizeValidation: "true",
    maxFileSize: "25MB",
    labelMaxFileSizeExceeded: "File is too large",
    labelMaxFileSize: "Maximum file size is {filesize}",
    allowFileTypeValidation: true,

    labelFileTypeNotAllowed: "File of invalid type",
    fileValidateTypeLabelExpectedTypes:
        "Expects {allButLastType} or {lastType}",
    storeAsFile: true,
    allowPdfPreview: true,
    pdfPreviewHeight: 320,
    pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
    allowVideoPreview: true,
    allowAudioPreview: true,
    onprocessfile: function (error, file) {
        if (!error) {
            // Clear the image view area
            const pond = FilePond.create(
                document.querySelector(".filepond-input")
            );
            pond.removeFiles();
            $(".filepond--root .filepond--image-preview").html("");
        }
    },
});

// ======================================= Rating Code ========================================

$(".rateYo").each(function (e) {
    var ChngRatevaluesEn = {
        1: "bad",
        2: "poor",
        3: "ok",
        4: "good",
        5: "super",
    };
    var ChngRatevaluesAr = {
        1: "bad-Ar",
        2: "poor-Ar",
        3: "ok-Ar",
        4: "good-Ar",
        5: "super-Ar",
    };
    var language = "english";
    var rating = $(this).attr("data-rating");
    $(this).rateYo({
        onSet: function (rating) {
            if (language === "arabic") {
                $(this).next().val(ChngRatevaluesAr[rating]);
            } else {
                $(this).next().val(ChngRatevaluesEn[rating]);
            }
            ratingFunc(rating, $(this).next().next().val());
        },
        rating: rating,
        starWidth: "20px",
        numStars: 5,
        fullStar: true,
        normalFill: "#A0A0A0",
        spacing: "5px",
        precision: 2,
    });
});

function ratingFunc(rating, bookid, lang) {
    debugger;
    if (lang != null) {
        language = lang;
    }
}

// ================================= seller statistics chart ======================================
if (from == "seller") {
    $(function () {
        function getTopSellingProducts(category_id) {
            $.ajax({
                type: "get",
                url: appUrl + "seller/topSellingProducts",
                data: { category_id: category_id },
                dataType: "json",
                success: function (result) {
                    token = $('meta[name="csrf-token"]').attr("content");
                    var productHtml = "";
                    $.each(result.data, function (index, product) {
                        // Create the product HTML

                        var imageUrl =
                            product.product_image.indexOf("https:") === -1
                                ? product.product_image !== ""
                                    ? appUrl +
                                    "storage/" +
                                    product.product_image
                                    : appUrl + "assets/img/no-image.jpg"
                                : product.product_image;

                        productHtml += `
                        <div class="top-selling-product-list d-flex align-items-center">
                            <p class="body-default m-0">${index + 1}.</p>
                            <div >
                                <img src="${imageUrl}" alt="${product.name
                            }" class="product-img-box">
                            </div>
                            <div>
                                <p class="lead mb-2">${product.name}</p>
                                <div class="d-flex total-product-sale">
                                    <i class='bx bx-badge-check body-default me-1'></i>
                                    <p class="body-default m-0">Sold: ${product.total_sold
                            }</p>
                                </div>
                            </div>
                        </div>
                    `;

                        // Append the product HTML to the container
                    });
                    $(".top-selling-products").html(productHtml);
                },
            });
        }

        function getMostPopularProducts(category_id) {
            $.ajax({
                type: "get",
                url: appUrl + "seller/mostPopularProduct",
                data: { category_id: category_id },
                dataType: "json",
                success: function (result) {
                    token = $('meta[name="csrf-token"]').attr("content");

                    var productHtml = "";
                    $.each(result.data, function (index, product) {
                        // Create the product HTML

                        var imageUrl =
                            product.product_image.indexOf("https:") === -1
                                ? product.product_image !== ""
                                    ? appUrl +
                                    "storage/" +
                                    product.product_image
                                    : appUrl + "assets/img/no-image.jpg"
                                : product.product_image;

                        productHtml += `
                        <div class="most-popular-product-list d-flex align-items-center">
                            <p class="body-default m-0">${index + 1}.</p>
                            <div >
                                <img src="${imageUrl}" alt="${product.name
                            }" class="product-img-box">
                            </div>
                            <div>
                                <p class="lead mb-2">${product.name}</p>
                                <div class="d-flex total-product-sale">
                                    <i class='bx bxs-star body-default me-1'></i>
                                    <p class="body-default me-1 product-rating">${product.average_rating
                            }</p>
                                    <p class="body-default m-0 total-reviews">(${product.total_reviews
                            } Reviews)</p>
                                </div>
                            </div>
                        </div>
                    `;
                    });
                    $(".most-popular-products").html(productHtml);
                },
            });
        }

        // Attach change event handler to the select element
        $("#top_selling_product_filter").on("change", function () {
            // Get the selected filter value
            var selectedCategoryId = $(this).val();
            // Call the function with the selected filter

            getTopSellingProducts(selectedCategoryId);
        });
        $("#most_popular_product_filter").on("change", function () {
            // Get the selected filter value
            var selectedCategoryId = $(this).val();
            // Call the function with the selected filter
            getMostPopularProducts(selectedCategoryId);
        });

        $(function () {
            getMostPopularProducts();
            getTopSellingProducts();
        });
    });
}

/* ---------------------------------------------------------------------------------------------------------
                                        daynamic filter using canvas
--------------------------------------------------------------------------------------------------------- */

var tableName = "";
$(function () {
    $(document).on("click", "#tableFilter", function () {
        var dateFilter = $(this).attr("dateFilter");
        var orderStatusFilter = $(this).attr("orderStatusFilter");
        var paymentMethodFilter = $(this).attr("paymentMethodFilter");
        var orderTypeFilter = $(this).attr("orderTypeFilter");
        var categoryFilter = $(this).attr("categoryFilter");
        var productStatusFilter = $(this).attr("productStatusFilter");
        var productTypeFilter = $(this).attr("productTypeFilter");
        var brandFilter = $(this).attr("brandFilter");
        var cashCollectionTypeFilter = $(this).attr("cashCollectionTypeFilter");
        var deliveryBoyFilter = $(this).attr("deliveryBoyFilter");
        var paymentRequestStatusFilter = $(this).attr(
            "paymentRequestStatusFilter"
        );
        var sellerFilter = $(this).attr("sellerFilter");
        var StatusFilter = $(this).attr("StatusFilter");
        var blogCategoryFilter = $(this).attr("blogCategoryFilter");

        if (dateFilter == "true") {
            $(".dateRangeFilter").removeClass("d-none");
        } else {
            $(".dateRangeFilter").addClass("d-none");
        }
        if (orderStatusFilter == "true") {
            $(".orderStatusFilter").removeClass("d-none");
        } else {
            $(".orderStatusFilter").addClass("d-none");
        }
        if (paymentMethodFilter == "true") {
            $(".paymentMethodFilter").removeClass("d-none");
        } else {
            $(".paymentMethodFilter").addClass("d-none");
        }
        if (orderTypeFilter == "true") {
            $(".orderTypeFilter").removeClass("d-none");
        } else {
            $(".orderTypeFilter").addClass("d-none");
        }
        if (categoryFilter == "true") {
            load_seller_category();
            $(".categoryFilter").removeClass("d-none");
        } else {
            $(".categoryFilter").addClass("d-none");
        }
        if (productStatusFilter == "true") {
            $(".productStatusFilter").removeClass("d-none");
        } else {
            $(".productStatusFilter").addClass("d-none");
        }
        if (productTypeFilter == "true") {
            $(".productTypeFilter").removeClass("d-none");
        } else {
            $(".productTypeFilter").addClass("d-none");
        }
        if (brandFilter == "true") {
            $(".brandFilter").removeClass("d-none");
        } else {
            $(".brandFilter").addClass("d-none");
        }
        if (paymentRequestStatusFilter == "true") {
            $(".paymentRequestStatusFilter").removeClass("d-none");
        } else {
            $(".paymentRequestStatusFilter").addClass("d-none");
        }
        if (sellerFilter == "true") {
            $(".sellerFilter").removeClass("d-none");
        } else {
            $(".sellerFilter").addClass("d-none");
        }
        if (StatusFilter == "true") {
            $(".StatusFilter").removeClass("d-none");
        } else {
            $(".StatusFilter").addClass("d-none");
        }
        if (blogCategoryFilter == "true") {
            load_blog_category();
            $(".blogCategoryFilter").removeClass("d-none");
        } else {
            $(".blogCategoryFilter").addClass("d-none");
        }
        if (cashCollectionTypeFilter == "true") {
            $(".cashCollectionTypeFilter").removeClass("d-none");
        } else {
            $(".cashCollectionTypeFilter").addClass("d-none");
        }
        if (deliveryBoyFilter == "true") {
            load_delievry_boys();
            $(".deliveryBoyFilter").removeClass("d-none");
        } else {
            $(".deliveryBoyFilter").addClass("d-none");
        }

        $("#filtersOffcanvas").offcanvas("toggle");
        tableName = $(this).data("table");

        setupColumnCheckboxes(tableName);
    });
});

$(document).on("click", "#tableRefresh", function (e) {
    var table_name = $(this).data("table");
    $("#" + table_name).bootstrapTable("refresh");
});

// when change pagination

$(document).on("page-change.bs.table", function (e, pageNumber, pageSize) {
    const table = $(e.target); // Get the table element using the event target
    const tableName = table.attr("id");
    const dataUrl = $("#" + tableName).attr("data-url");
    const data_query_params = $("#" + tableName).attr("data-query-params");

    // Calculate limit and offset
    const pagination_limit = pageSize;
    const pagination_offset = (pageNumber - 1) * pageSize;

    if (tableName !== "admin_seller_wallet_table") {
        if (dataUrl) {
            try {
                // Create a URL object from the dataUrl
                const urlWithSearchParams = new URL(
                    dataUrl,
                    window.location.origin
                );
                const existingParams = urlWithSearchParams.searchParams;

                // Date range picker logic
                var drp = $("#datepicker").data("daterangepicker");

                if (drp) {
                    var startDate = drp.startDate.format("YYYY-MM-DD");
                    var endDate = drp.endDate.format("YYYY-MM-DD");
                }

                // Add custom parameters
                const paramsToSet = [
                    { key: "order_status", selector: "#order_status" },
                    { key: "search", selector: ".searchInput" },
                    { key: "payment_method", selector: "#payment_method" },
                    { key: "order_type", selector: "#order_type" },
                    { key: "category_id", selector: "#category_id" },
                    { key: "status_filter", selector: "#status_filter" },
                    { key: "seller_id", selector: "#filterSellerId" },
                    { key: "status", selector: "#statusFilter" },
                    {
                        key: "product_type_filter",
                        selector: "#product_type_filter",
                    },
                    { key: "admin_brand_list", selector: "#admin_brand_list" },
                    {
                        key: "payment_request_status",
                        selector: "#payment_request_status_filter",
                    },
                    { key: "blog_category_id", selector: "#blog_category_id" },
                    {
                        key: "filter_status",
                        selector: "#cash_collection_status",
                    },
                    { key: "delivery_boy", selector: "#delivery_boy" },
                ];

                // Set parameters based on the input fields
                paramsToSet.forEach((param) => {
                    const value = $(param.selector).val();
                    if (value) {
                        existingParams.set(param.key, value);
                    }
                });

                // Add limit and offset parameters
                existingParams.set("pagination_limit", pagination_limit);
                existingParams.set("pagination_offset", pagination_offset);

                // Refresh the table with the modified URL
                $(table).bootstrapTable("refresh", {
                    url: urlWithSearchParams.toString(), // Convert URL object back to string
                    query: Object.fromEntries(existingParams), // Convert URLSearchParams to a plain object for query
                });
            } catch (error) {
                console.error("Invalid URL:", error); // Log any errors encountered
            }
        } else {
            console.error("data-url is not defined or invalid.");
        }
    }
});

function getTableColumnNames(tableName, propertyName) {
    const columns = $("#" + tableName).bootstrapTable("getOptions").columns[0];

    if (propertyName == "columnTitle") {
        return columns.map((column) => column["passed"].title);
    }
    if (propertyName == "columnField") {
        return columns.map((column) => column.field);
    }
}

function setupColumnCheckboxes(tableName) {
    const columnNames = getTableColumnNames(tableName, "columnField");
    const columnTitles = getTableColumnNames(tableName, "columnTitle");
    const offcanvasBody = document.getElementById("columnFilterOffcanvasBody");
    offcanvasBody.innerHTML = "";
    // Create a container for all rows with padding
    const containerDiv = document.createElement("div");
    containerDiv.classList.add("container-fluid");
    offcanvasBody.appendChild(containerDiv);
    // Create a row container
    rowContainer = document.createElement("div");
    rowContainer.classList.add("row");
    for (let i = 0; i < columnNames.length; i++) {
        const columnName = columnNames[i];
        const columnTitle = columnTitles[i];
        // Create a form-check element
        // console.log(columnName);
        if (columnName == "delete-checkbox") {
            continue;
        }
        const thElements = $("#" + tableName).find(
            "th[data-field][data-disabled='1']"
        );
        const disabledColumnNames = [];

        thElements.each(function () {
            const columnName = $(this).attr("data-field");
            disabledColumnNames.push(columnName);
        });

        // Iterate over each column name fetched from disabledColumnNames array
        disabledColumnNames.forEach(function (columnName) {
            const checkboxElement = $("#" + columnName);
            // Use .prop() method to set the disabled property to true
            checkboxElement.prop("disabled", true);
        });

        const checkbox = document.createElement("div");
        checkbox.classList.add("form-check", "col-6"); // Use col-6 to have 2 elements in 1 row
        checkbox.innerHTML = `
            <input class="form-check-input" type="checkbox" id="${columnName}">
            <label class="form-check-label" for="${columnName}">${columnTitle}</label>
        `;

        // Append the form-check to the row container
        rowContainer.appendChild(checkbox);
        // If two elements have been added to the row, create a new row
        if ((i + 1) % 2 === 0 || i === columnNames.length - 1) {
            containerDiv.appendChild(rowContainer);
            // Create a new row container
            rowContainer = document.createElement("div");
            rowContainer.classList.add("row");
        }
    }
    for (let i = 0; i < columnNames.length; i++) {
        const columnName = columnNames[i];
        // Check the checkboxes based on the columns already shown in the table
        const visibleColumns = $("#" + tableName)
            .bootstrapTable("getVisibleColumns")
            .map((column) => column.field);
        if (visibleColumns.includes(columnName)) {
            const checkboxElement = document.querySelector(
                `.form-check-input[id="${columnName}"]`
            );
            const idCheckbox = document.querySelector("#id.form-check-input");
            if (checkboxElement) {
                checkboxElement.checked = true;
            }
            if (checkboxElement == idCheckbox) {
                checkboxElement.disabled = true;
            }
        }
    }
}

// Function to apply dynamic column filters
function applyDynamicColumnFilters(tableName) {
    const checkedCheckboxes = document.querySelectorAll(
        "#filtersOffcanvas input:checked"
    );
    const checkedColumnNames = Array.from(checkedCheckboxes).map(
        (checkbox) => checkbox.id
    );

    for (const columnName of getTableColumnNames(tableName, "columnField")) {
        const isColumnVisible = checkedColumnNames.includes(columnName);
        $("#" + tableName).bootstrapTable(
            isColumnVisible ? "showColumn" : "hideColumn",
            columnName
        );
    }

    // Close the offcanvas

    $("#filtersOffcanvas").offcanvas("hide");
}

function uncheckAllCheckboxes() {
    const checkboxes = document.querySelectorAll(
        '#columnFilterOffcanvasBody input[type="checkbox"]'
    );

    const thElements = $("#" + tableName).find(
        "th[data-field][data-disabled='1']"
    );
    const disabledColumnNames = [];

    thElements.each(function () {
        const columnName = $(this).attr("data-field");
        disabledColumnNames.push(columnName);
    });

    checkboxes.forEach((checkbox) => {
        const idCheckbox = document.querySelector("#id.form-check-input");
        if (
            checkbox !== idCheckbox &&
            !disabledColumnNames.includes(checkbox.id)
        ) {
            // Uncheck the checkbox if it's not the ID checkbox or disabled
            checkbox.checked = false;
        } else {
            // Disable the checkbox if it's the ID checkbox or disabled
            checkbox.disabled = true;
        }
    });

    // Reset other filter dropdown values to blank
    $("#status_filter").val("");
    $("#statusFilter").val("");
    $("#payment_method").val("");
    $("#order_type").val("");
    $("#order_status").val("");
    $("#filterSellerId").val("");
    $("#category_id").val("");
    $("#admin_brand_list").val("").trigger("change");
    $("#product_type_filter").val("");
    $("#blog_category_id").val("");
    $("#cash_collection_status").val("");
    $("#delivery_boy").val("");
    $("#payment_request_status_filter").val("");
}
var userSelectedDates = false;
$(document).on("click", "#tableFilterBtn", function (e) {
    applyDynamicColumnFilters(tableName);
    var OrderStatus = $("#order_status").val();
    var paymentMethod = $("#payment_method").val();
    var orderType = $("#order_type").val();
    var categoryId = $("#category_id").val();
    var productStatus = $("#status_filter").val();
    var productType = $("#product_type_filter").val();
    var brand_id = $("#admin_brand_list").val();
    var payment_request_status = $("#payment_request_status_filter").val();
    var seller_id = $("#filterSellerId").val();
    var status = $("#statusFilter").val();
    var blogCategoryId = $("#blog_category_id").val();
    var cashCollectionType = $("#cash_collection_status").val();
    var deliveryBoyFilter = $("#delivery_boy").val();

    var drp = $("#datepicker").data("daterangepicker");

    if (userSelectedDates) {
        var startDate = drp.startDate.format("YYYY-MM-DD");
        var endDate = drp.endDate.format("YYYY-MM-DD");
        // Perform the filtering using startDate and endDate
    }

    // Use the selected value in the Bootstrap Table refresh
    const dataUrl = $("#" + tableName).attr("data-url");
    $("#" + tableName).bootstrapTable("refresh", {
        url: dataUrl,
        query: {
            order_status: OrderStatus,
            payment_method: paymentMethod,
            order_type: orderType,
            start_date: startDate,
            end_date: endDate,
            category_id: categoryId,
            productStatus: productStatus,
            product_type: productType,
            brand_id: brand_id,
            payment_request_status: payment_request_status,
            seller_id: seller_id,
            status: status,
            blogCategoryId: blogCategoryId,
            cashCollectionType: cashCollectionType,
            deliveryBoyFilter: deliveryBoyFilter,
        },
    });
});

$(document).on("click", ".reset_filter_button", function (e) {
    uncheckAllCheckboxes();
});

$(".searchInput").on("input", function () {
    var searchTable = $(this).data("table");
    const searchText = $(this).val().toLowerCase();
    const dataUrl = $("#" + searchTable).attr("data-url");
    // Create a URL object from the dataUrl
    const urlWithSearchParams = new URL(dataUrl);
    // Get existing query parameters
    const existingParams = urlWithSearchParams.searchParams.toString();
    // Set the search parameter
    urlWithSearchParams.searchParams.set("search", searchText);
    // Add back existing query parameters
    if (existingParams) {
        urlWithSearchParams.search = existingParams;
    }
    // Refresh the table with the modified URL
    $("#" + searchTable).bootstrapTable("refresh", {
        url: urlWithSearchParams.toString(),
    });
});

function exportTableData(ExportTable, exportType) {
    const exportOptions = {
        fileName: ExportTable + "-list",
        ignoreColumn: ["operate"],
    };
    $("#" + ExportTable).tableExport({
        type: exportType,
        escape: "false", // Add this line to prevent HTML entities encoding
        ...exportOptions,
    });
}

/* -----------------------------------------------------------------------------------------------------
                                        offcanvas table filters
----------------------------------------------------------------------------------------------------- */

$(function () {
    userSelectedDates = false;

    function cb(start, end) {
        if (!start || !end || start.isSame(end, "day")) {
            $("#datepicker span").html("Select Date Range");
        } else {
            $("#datepicker span").html(
                start.format("MMMM D, YYYY") +
                " - " +
                end.format("MMMM D, YYYY")
            );
            userSelectedDates = true; // Mark that the user has selected dates
        }
    }

    $("#datepicker span").html("Select Date Range");

    $("#datepicker").daterangepicker(
        {
            autoUpdateInput: false,
            locale: {
                cancelLabel: "Clear",
            },
            ranges: {
                Today: [moment(), moment()],
                Yesterday: [
                    moment().subtract(1, "days"),
                    moment().subtract(1, "days"),
                ],
                "Last 7 Days": [moment().subtract(6, "days"), moment()],
                "Last 30 Days": [moment().subtract(29, "days"), moment()],
                "This Month": [
                    moment().startOf("month"),
                    moment().endOf("month"),
                ],
                "Last Month": [
                    moment().subtract(1, "month").startOf("month"),
                    moment().subtract(1, "month").endOf("month"),
                ],
            },
        },
        cb
    );

    // Handle the apply event to update the display
    $("#datepicker").on("apply.daterangepicker", function (ev, picker) {
        cb(picker.startDate, picker.endDate);
    });

    // Handle the cancel event to reset the display
    $("#datepicker").on("cancel.daterangepicker", function (ev, picker) {
        $("#datepicker span").html("Select Date Range");
        userSelectedDates = false; // Reset the flag as no dates are selected
    });

    // Manually trigger the callback function to set initial state
    cb(null, null);
});

// Use event delegation to handle click events on dynamic elements
$(document).on("click", ".changeLang", function (e) {
    e.preventDefault(); // Prevent the default behavior of the anchor tag

    var url = appUrl + from + "/settings/languages/change";
    var selectedLang = $(this).data("lang-code");

    iziToast.success({
        message: "Language Set Successfully",
    });

    window.location.href = url + "?lang=" + selectedLang;
});

$(function () {
    var systemLang = $("#current-lang").val();
    $("#language-settings").val(systemLang);
    $("#language-settings").on("change", function (e) {
        e.preventDefault();
    });

    $(`#lang-${systemLang}`).addClass("active");

    const languageLinks = document.querySelectorAll(".languages a");

    languageLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            // Remove the "active" class from all links
            languageLinks.forEach((link) => link.classList.remove("active"));
            // Add the "active" class to the clicked link
            link.classList.add("active");
            // Perform any other actions needed when a language is selected
        });
    });
});

$("#edit_transaction_form").on("submit", function (e) {
    e.preventDefault();
    var formdata = new FormData(this);
    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    formdata.append("_token", csrfToken);
    $.ajax({
        type: "POST",
        url: $(this).attr("action"),
        data: formdata,
        beforeSend: function () {
            $("#submit_btn").html("Please Wait..").attr("disabled", true);
        },
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            $("#submit_btn").html("Update Transaction").attr("disabled", false);
            if (result.error == false) {
                $("table").bootstrapTable("refresh");
                iziToast.success({
                    message:
                        '<span style="text-transform:capitalize">' +
                        result.message +
                        "</span> ",
                });
                $("#transaction_modal").modal("hide");
            } else {
                if (result.error_message) {
                    iziToast.error({
                        message: result.error_message,
                    });
                } else if (result.message) {
                    iziToast.error({
                        message: result.message,
                    });
                }
            }
        },
        error: function (xhr, status, error) {
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                // Display each error message in a separate toast
                $.each(errors, function (field, errorMessages) {
                    if (Array.isArray(errorMessages)) {
                        $.each(errorMessages, function (index, errorMessage) {
                            iziToast.error({
                                title: "Error",
                                message: errorMessage,
                                position: "topRight",
                            });
                        });
                    } else {
                        iziToast.error({
                            title: "Error",
                            message: errorMessages,
                            position: "topRight",
                        });
                    }
                });
                $("#submit_btn")
                    .html("Update Transaction")
                    .attr("disabled", false);
            } else {
                $("#submit_btn")
                    .html("Update Transaction")
                    .attr("disabled", false);
                iziToast.error({
                    title: "Error",
                    message: xhr.responseJSON.message,
                    position: "topRight",
                });
            }
        },
    });
});

$(document).on("click", ".edit_transaction", function (e, row) {
    e.preventDefault();

    var id = $(this).data("id");
    var txn_id = $(this).data("txn_id");
    var status = $(this).data("status");
    var message = $(this).data("message");

    $("#id").val(id);
    $("#txn_id").val(txn_id);
    $("#t_status").val(status);
    $("#transaction_message").val(message);

    $('#t_status option[value="' + status + '"]').prop("selected", true);
});

$("#media-type").on("change", function () {
    var type = $(this).val();

    $.ajax({
        method: "GET",
        url: appUrl + from + "/media",
        data: {
            type: type,
            limit: 20,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        success: function (response) {
            // Replace the content of the .media-card div with the updated data
            $(".media-card-container").html(
                $(response).find(".media-card-container").html()
            );
        },
        error: function (xhr, status, error) {
            iziToast.error({
                title: "Error",
                message: "Failed to fetch media data. Please try again later.",
                position: "topRight",
            });
        },
    });
});

$("#search_products").on("keyup", function (e) {
    e.preventDefault();
    var search = $(this).val();

    $.ajax({
        method: "GET",
        url: appUrl + from + "/media",
        data: {
            search: search,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        success: function (response) {
            // Replace the content of the .media-card div with the updated data
            $(".media-card-container").html(
                $(response).find(".media-card-container").html()
            );
        },
        error: function (xhr, status, error) {
            iziToast.error({
                title: "Error",
                message: "Failed to fetch media data. Please try again later.",
                position: "topRight",
            });
        },
    });
});
function salesReport(index, row) {
    var html = [];
    var indexs = 0;

    $.each(row, function (key, value) {
        var columns = $("th:eq(" + (indexs + 1) + ")").data("field");
        if (columns != undefined && columns !== "state") {
            html.push("<p><b>" + columns + " :</b> " + row[columns] + "</p>");
            indexs++;
        }
    });

    return html;
}
$(function () {
    $(".change_variant_status").on("change", function (e) {
        var id = $(this).data("id");
        var status = $(this).data("status");
        var product_id = $(this).data("product-id");

        $.ajax({
            method: "GET",
            url: appUrl + from + "/product/change_variant_status",
            data: {
                _token: $('meta[name="csrf-token"]').attr("content"),
                id: id,
                status: status,
                product_id: product_id,
            },
            success: function (response) {
                if (response.error === true && response.error_message) {
                    iziToast.error({
                        title: "Error",
                        message: response.error_message,
                        position: "topRight",
                    });
                } else {
                    iziToast.success({
                        title: "Success",
                        message: response.message,
                        position: "topRight",
                    });
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                }
            },
            error: function (xhr, status, error) {
                iziToast.error({
                    title: "Error",
                    message: "Failed to update. Please try again later.",
                    position: "topRight",
                });
            },
        });
    });
});

$(function () {
    $(".delete_variant").on("click", function (e) {
        var id = $(this).data("id");
        var status = $(this).data("status");
        var product_id = $(this).data("product-id");

        $.ajax({
            method: "GET",
            url: appUrl + from + "/product/delete_variant",
            data: {
                _token: $('meta[name="csrf-token"]').attr("content"),
                id: id,
                status: status,
                product_id: product_id,
            },
            success: function (response) {
                if (response.error === true && response.error_message) {
                    iziToast.error({
                        title: "Error",
                        message: response.error_message,
                        position: "topRight",
                    });
                } else {
                    iziToast.success({
                        title: "Success",
                        message: response.message,
                        position: "topRight",
                    });
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                }
            },
            error: function (xhr, status, error) {
                iziToast.error({
                    title: "Error",
                    message: "Failed to update. Please try again later.",
                    position: "topRight",
                });
            },
        });
    });
});
$(function () {
    $(document).on("click", ".camera_icon_div", function (e) {
        $("#store_logo_file_upload").trigger("click");
    });
});

$(function () {
    $(document).on("click", ".change_banner_button", function (e) {
        $("#store_thumbnail_file_upload").trigger("click");
    });
});

$("#update_receipt_status").on("change", function (e) {
    e.preventDefault();
    var order_id = $(this).data("id");
    var user_id = $(this).data("user_id");
    var status = $(this).val();
    $.ajax({
        type: "POST",
        data: {
            order_id: order_id,
            status: status,
            user_id: user_id,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        url: appUrl + "admin/orders/update_receipt_status",
        dataType: "json",
        success: function (result) {
            csrfName = result.csrfName;
            csrfHash = result.csrfHash;
            if (result["error"] == false) {
                iziToast.success({
                    message: result["message"],
                });
                setTimeout(function () {
                    // Redirect to the appropriate URL
                    window.location.reload();
                }, 3000);
            } else {
                iziToast.error({
                    message: result["message"],
                });
            }
        },
    });
});

//settle promocode discount

$(document).on("click", ".add_promo_code_discount", function () {
    Swal.fire({
        title: "Are You Sure !",
        text: "You won't be able to revert this!",
        type: "info",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, settle Discount!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: appUrl + "admin/cronjob/settleCashbackDiscount",
                    type: "GET",
                    data: {
                        is_date: true,
                    },
                    dataType: "json",
                })
                    .done(function (response, textStatus) {
                        if (response.error == false) {
                            Swal.fire("Done!", response.message, "success");
                            $("table").bootstrapTable("refresh");
                        } else {
                            if (response.message) {
                                Swal.fire(
                                    "Oops...",
                                    response.message,
                                    "warning"
                                );
                            } else if (response.error_message) {
                                Swal.fire(
                                    "Oops...",
                                    response.error_message,
                                    "warning"
                                );
                            }
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        Swal.fire("Oops...", "Something went wrong !", "error");
                    });
            });
        },
        allowOutsideClick: false,
    });
});
$(document).on("change", ".set_default_storage_type", function () {
    var url = $(this).data("url");
    var id = $(this).data("id");
    $.ajax({
        method: "GET",
        url: url,
        data: {
            _token: $('meta[name="csrf-token"]').attr("content"),
            id: id,
        },
        success: function (response) {
            if (response.error) {
                iziToast.error({
                    title: "Error",
                    message: response.error,
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            } else {
                iziToast.success({
                    title: "Success",
                    message: "Storage type set Successfully",
                    position: "topRight",
                });
                $(".table").bootstrapTable("refresh");
            }
        },
        fail: function (response) {
            iziToast.error({
                title: "Error",
                message: "Something Went Wrong!!",
                position: "topRight",
            });
        },
    });
});

$(document).on("click", ".delete-onboard-media", function () {
    var path = $(this).data("path");
    var field = $(this).data("field");
    var img_name = $(this).data("img");
    var table_name = $(this).data("table");
    var t = this;
    var isjson = $(this).data("isjson");
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + "admin/settings/removeSettingMedia",
                    data: {
                        path: path,
                        field: field,
                        img_name: img_name,
                        table_name: table_name,
                        isjson: isjson,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                    success: function (result) {
                        token = $('meta[name="csrf-token"]').attr("content");
                        if (result[0]["is_deleted"] == true) {
                            $(t).closest("div").remove();
                            Swal.fire("Success", "Media Deleted !", "success");
                        } else {
                            Swal.fire(
                                "Oops...",
                                "Something went wrong !",
                                "error"
                            );
                        }
                    },
                });
            });
        },
        allowOutsideClick: false,
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Cancelled!", "Your data is  safe.", "error");
        }
    });
});

//category order

document.addEventListener("DOMContentLoaded", function () {
    var rowSize = 100; // => container height / number of items
    var container = document.querySelector(".category-order-container");
    var listItems = Array.from(document.querySelectorAll(".list-item")); // Array of elements
    if (listItems.length <= 1) {
        $("#save_category_order").addClass("d-none");
    } else {
        $("#save_category_order").removeClass("d-none"); // Optional: show the button if there are more than 1 items
    }
    var sortables = listItems.map(Sortable); // Array of sortables
    var total = sortables.length;

    TweenLite.to(container, 0.5, {
        autoAlpha: 1,
    });

    function changeIndex(item, to) {
        // Change position in array
        arrayMove(sortables, item.index, to);

        // Change element's position in DOM. Not always necessary. Just showing how.
        if (to === total - 1) {
            container.appendChild(item.element);
        } else {
            var i = item.index > to ? to : to + 1;
            container.insertBefore(item.element, container.children[i]);
        }

        // Set index for each sortable
        sortables.forEach((sortable, index) => sortable.setIndex(index));
    }

    function Sortable(element, index) {
        var content = element.querySelector(".item-content");
        var order = element.querySelector(".order");

        var animation = TweenLite.to(content, 0.3, {
            boxShadow: "rgba(0,0,0,0.2) 0px 16px 32px 0px",
            force3D: true,
            scale: 1.1,
            paused: true,
        });

        var dragger = new Draggable(element, {
            onDragStart: downAction,
            onRelease: upAction,
            onDrag: dragAction,
            cursor: "inherit",
            type: "y",
        });

        // Public properties and methods
        var sortable = {
            dragger: dragger,
            element: element,
            index: index,
            setIndex: setIndex,
        };

        TweenLite.set(element, {
            y: index * rowSize,
        });

        function setIndex(index) {
            sortable.index = index;
            order.textContent = index + 1;

            // Don't layout if you're dragging
            if (!dragger.isDragging) layout();
        }

        function downAction() {
            animation.play();
            this.update();
        }

        function dragAction() {
            // Calculate the current index based on element's position
            var index = clamp(Math.round(this.y / rowSize), 0, total - 1);

            if (index !== sortable.index) {
                changeIndex(sortable, index);
            }
        }

        function upAction() {
            animation.reverse();
            layout();
        }

        function layout() {
            TweenLite.to(element, 0.3, {
                y: sortable.index * rowSize,
            });
        }

        return sortable;
    }

    // Changes an elements's position in array
    function arrayMove(array, from, to) {
        array.splice(to, 0, array.splice(from, 1)[0]);
    }

    // Clamps a value to a min/max
    function clamp(value, a, b) {
        return value < a ? a : value > b ? b : value;
    }

    $(document).on("click", "#save_category_order", function () {
        var ids = sortables.map(function (obj) {
            // Extract the id from the element property
            return obj["element"]["id"];
        });

        $.ajax({
            data: { category_id: ids },
            type: "GET",
            url: appUrl + "admin/categories/update_category_order",
            dataType: "json",
            success: function (response) {
                if (response.error == false) {
                    iziToast.success({
                        message: response.message,
                    });
                } else {
                    iziToast.error({
                        message: response.message,
                    });
                }
            },
        });
    });
});

// feature section order

document.addEventListener("DOMContentLoaded", function () {
    var rowSize = 100; // => container height / number of items
    var container = document.querySelector(".section-order-container");
    var listItems = Array.from(document.querySelectorAll(".section-list-item")); // Array of elements
    var sortables = listItems.map(Sortable); // Array of sortables

    if (listItems.length <= 1) {
        $("#save_section_order").addClass("d-none");
    } else {
        $("#save_section_order").removeClass("d-none"); // Optional: show the button if there are more than 1 items
    }

    var total = sortables.length;

    TweenLite.to(container, 0.5, {
        autoAlpha: 1,
    });

    function changeIndex(item, to) {
        // Change position in array
        arrayMove(sortables, item.index, to);

        // Change element's position in DOM. Not always necessary. Just showing how.
        if (to === total - 1) {
            container.appendChild(item.element);
        } else {
            var i = item.index > to ? to : to + 1;
            container.insertBefore(item.element, container.children[i]);
        }

        // Set index for each sortable
        sortables.forEach((sortable, index) => sortable.setIndex(index));
    }

    function Sortable(element, index) {
        var content = element.querySelector(".section-item-content");
        var order = element.querySelector(".section-order");

        var animation = TweenLite.to(content, 0.3, {
            boxShadow: "rgba(0,0,0,0.2) 0px 16px 32px 0px",
            force3D: true,
            scale: 1.1,
            paused: true,
        });

        var dragger = new Draggable(element, {
            onDragStart: downAction,
            onRelease: upAction,
            onDrag: dragAction,
            cursor: "inherit",
            type: "y",
        });

        // Public properties and methods
        var sortable = {
            dragger: dragger,
            element: element,
            index: index,
            setIndex: setIndex,
        };

        TweenLite.set(element, {
            y: index * rowSize,
        });

        function setIndex(index) {
            sortable.index = index;
            order.textContent = index + 1;

            // Don't layout if you're dragging
            if (!dragger.isDragging) layout();
        }

        function downAction() {
            animation.play();
            this.update();
        }

        function dragAction() {
            // Calculate the current index based on element's position
            var index = clamp(Math.round(this.y / rowSize), 0, total - 1);

            if (index !== sortable.index) {
                changeIndex(sortable, index);
            }
        }

        function upAction() {
            animation.reverse();
            layout();
        }

        function layout() {
            TweenLite.to(element, 0.3, {
                y: sortable.index * rowSize,
            });
        }

        return sortable;
    }

    // Changes an elements's position in array
    function arrayMove(array, from, to) {
        array.splice(to, 0, array.splice(from, 1)[0]);
    }

    // Clamps a value to a min/max
    function clamp(value, a, b) {
        return value < a ? a : value > b ? b : value;
    }

    $(document).on("click", "#save_section_order", function () {
        var ids = sortables.map(function (obj) {
            // Extract the id from the element property
            return obj["element"]["id"];
        });

        $.ajax({
            data: { section_id: ids },
            type: "GET",
            url: appUrl + "admin/feature_section/update_section_order",
            dataType: "json",
            success: function (response) {
                if (response.error == false) {
                    iziToast.success({
                        message: response.message,
                    });
                } else {
                    iziToast.error({
                        message: response.message,
                    });
                }
            },
        });
    });
});

// boostrap table loading icon

function loadingTemplate() {
    return '<i class="bx bx-loader-alt bx-spin bx-rotate-180" ></i>';
}

function validateNumberInput(input) {
    // Remove any non-numeric characters from the input value
    input.value = input.value.replace(/\D/g, "");
}

// code for search meny ib side bar

$(function () {
    $(".menuSearch").on("input", function () {
        let searchValue = $(this).val().toLowerCase();
        $(".navbar-nav li").each(function () {
            let $currentItem = $(this);
            let text = $currentItem.text().toLowerCase();
            if (
                text.includes(searchValue) ||
                $currentItem.find("*").filter(function () {
                    return $(this).text().toLowerCase().includes(searchValue);
                }).length > 0
            ) {
                $currentItem.show();
                $currentItem.parents(".sidebar-title").show();
            } else {
                $currentItem.hide();
            }
        });
    });
});

$(function () {
    function checkContentOverflow() {
        var windowHeight = $(window).height();
        var footerHeight = $(".main-footer").outerHeight();
        var headerHeight = $(".header").outerHeight();
        var contentHeight =
            $("#page-content").outerHeight() - headerHeight - footerHeight;

        if (contentHeight > windowHeight) {
            $(".main-footer").css({
                bottom: "0",
                left: "0",
                right: "0",
                padding: "20px",
                color: "#919BAE",
                display: "flex",
                "background-color": "#ffffff",
                "flex-wrap": "nowrap",
                "justify-content": "flex-start",

                "-webkit-box-shadow": "0 2px 3px rgba(0, 0, 0, .04)",
                "box-shadow": "0 2px 3px rgba(0, 0, 0, .04)",
            });
        } else {
            $(".main-footer").css({
                bottom: "0",

                left: "250px",
                right: "0",
                padding: "20px",
                color: "#919BAE",
                display: "flex",
                "background-color": "#ffffff",
                "flex-wrap": "nowrap",
                "justify-content": "flex-start",
                "-webkit-box-shadow": "0 2px 3px rgba(0, 0, 0, .04)",
                "box-shadow": "0 2px 3px rgba(0, 0, 0, .04)",
            });
        }
    }

    // Call the function when the document is ready and when the window is resized
    $(window).on("load resize", function () {
        checkContentOverflow();
    });
});

// authentication setting change event

$("input[type=radio][name=authentication_method]").change(function () {
    var firebase_radio_button = $(
        'input[type=radio][id="firebase_radio_button"]:checked'
    ).val();
    var sms_radio_button = $(
        'input[type=radio][id="sms_radio_button"]:checked'
    ).val();
    if (firebase_radio_button == "firebase") {
        $(".firebase_config").removeClass("d-none");
        $(".sms_gateway").addClass("d-none");
    } else if (sms_radio_button == "sms") {
        $(".sms_gateway").removeClass("d-none");
        $(".firebase_config").addClass("d-none");
    }
});
// sms gateway js

var sms_data = $("#sms_gateway_data").val() ? $("#sms_gateway_data").val() : [];

if (sms_data.length != 0) {
    var sms_data = JSON.parse(sms_data);
}

$(document).on("click", "#add_sms_header", function (e) {
    e.preventDefault();
    load_sms_header_section(cat_html, false);
});

// function load_sms_header_section(
//     cat_html,
//     is_edit = false,
//     key_headers = [],
//     value_headers = []
// ) {
//     var key_headers = sms_data.header_key;
//     var value_headers = sms_data.header_value;
//     if (is_edit == true) {
//         var html = "";

//         if (Array.isArray(key_headers)) {
//             for (var i = 0; i < key_headers.length; i++) {
//                 html += '<div class="form-group row">';
//                 html += '<div class="col-sm-5 mt-4">';
//                 html +=
//                     '<label for="header_key" class="form-label"> Key </label>';
//                 html +=
//                     '<input type="text" class="form-control" placeholder="Enter Key" name="header_key[]" value="' +
//                     key_headers[i] +
//                     '" id="header_key">';
//                 html += "</div>";
//                 html += '<div class="col-sm-5 mt-4">';
//                 html +=
//                     '<label for="header_value" class="form-label"> Value </label>';
//                 html +=
//                     '<input type="text" class="form-control" placeholder="Enter Value" name="header_value[]" value="' +
//                     value_headers[i] +
//                     '" id="header_value">';
//                 html += "</div>";
//                 html += '<div class="col-sm-2 mt-8">';
//                 html +=
//                     '<button type="button" class="btn btn-tool remove_keyvalue_section"> <i class="text-danger bx bx-trash fa-2x"></i> </button>';
//                 html += "</div>";
//                 html += "</div>";
//             }
//         }
//     } else {
//         var html =
//             '<div class="form-group row">' +
//             '<div class="col-sm-5 mt-4">' +
//             '<label for="header_key" class="form-label"> Key </label>' +
//             '<input type="text" class="form-control"  placeholder="Enter Key" name="header_key[]"  value="" id="header_key">' +
//             "</div>" +
//             '<div class="col-sm-5 mt-4">' +
//             '<label for="header_value" class="form-label"> Value </label>' +
//             '<input type="text" class="form-control"  placeholder="Enter value" name="header_value[]" id="header_value"  value="">' +
//             "</div>" +
//             '<div class="col-sm-2 mt-8"> ' +
//             '<button type="button" class="btn btn-tool remove_keyvalue_header_section" > <i class="text-danger bx bx-trash fa-2x"></i> </button>' +
//             "</div>" +
//             "</div>" +
//             "</div>";
//     }
//     $("#formdata_header_section").append(html);
// }

function load_sms_header_section(
    cat_html,
    is_edit = false,
    key_headers = [],
    value_headers = []
) {
    // Ensure sms_data is defined and has header_key and header_value properties
    var key_headers =
        sms_data && Array.isArray(sms_data.header_key)
            ? sms_data.header_key
            : [];
    var value_headers =
        sms_data && Array.isArray(sms_data.header_value)
            ? sms_data.header_value
            : [];

    var html = "";

    if (is_edit === true) {
        if (Array.isArray(key_headers)) {
            for (var i = 0; i < key_headers.length; i++) {
                html += '<div class="form-group row">';
                html += '<div class="col-sm-5 mt-4">';
                html +=
                    '<label for="header_key" class="form-label"> Key </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Key" name="header_key[]" value="' +
                    key_headers[i] +
                    '" id="header_key">';
                html += "</div>";
                html += '<div class="col-sm-5 mt-4">';
                html +=
                    '<label for="header_value" class="form-label"> Value </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Value" name="header_value[]" value="' +
                    value_headers[i] +
                    '" id="header_value">';
                html += "</div>";
                html += '<div class="col-sm-2 mt-8">';
                html +=
                    '<button type="button" class="btn btn-tool remove_keyvalue_section"> <i class="text-danger bx bx-trash fa-2x"></i> </button>';
                html += "</div>";
                html += "</div>";
            }
        }
    } else {
        html =
            '<div class="form-group row">' +
            '<div class="col-sm-5 mt-4">' +
            '<label for="header_key" class="form-label"> Key </label>' +
            '<input type="text" class="form-control"  placeholder="Enter Key" name="header_key[]"  value="" id="header_key">' +
            "</div>" +
            '<div class="col-sm-5 mt-4">' +
            '<label for="header_value" class="form-label"> Value </label>' +
            '<input type="text" class="form-control"  placeholder="Enter value" name="header_value[]" id="header_value"  value="">' +
            "</div>" +
            '<div class="col-sm-2 mt-8"> ' +
            '<button type="button" class="btn btn-tool remove_keyvalue_header_section" > <i class="text-danger bx bx-trash fa-2x"></i> </button>' +
            "</div>" +
            "</div>" +
            "</div>";
    }

    // Append generated HTML to the DOM
    $("#formdata_header_section").append(html);
}

// paramas data
$(document).on("click", "#add_sms_params", function (e) {
    e.preventDefault();
    load_sms_params_section(cat_html, false);
});

function load_sms_params_section(
    cat_html,
    is_edit = false,
    key_params = [],
    value_params = []
) {
    var key_params = sms_data.params_key;
    var value_params = sms_data.params_value;
    var key = $().val();
    if (is_edit == true) {
        var html = "";

        if (Array.isArray(key_params)) {
            for (var i = 0; i < key_params.length; i++) {
                html += '<div class="form-group row">';
                html += '<div class="col-sm-5">';
                html +=
                    '<label for="params_key" class="form-label"> Key </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Key" name="params_key[]" value="' +
                    key_params[i] +
                    '" id="params_key">';
                html += "</div>";
                html += '<div class="col-sm-5">';
                html +=
                    '<label for="params_value" class="form-label"> Value </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Value" name="params_value[]" value="' +
                    value_params[i] +
                    '" id="params_value">';
                html += "</div>";
                html += '<div class="col-sm-2 mt-5">';
                html +=
                    '<button type="button" class="btn btn-tool remove_keyvalue_section"> <i class="text-danger bx bx-trash fa-2x"></i> </button>';
                html += "</div>";
                html += "</div>";
            }
        }
    } else {
        var html =
            '<div class="form-group row">' +
            '<div class="col-sm-5">' +
            '<label for="params_key" class="form-label"> Key </label>' +
            '<input type="text" class="form-control"  placeholder="Enter Key" name="params_key[]"  value="" id="params_key">' +
            "</div>" +
            '<div class="col-sm-5">' +
            '<label for="params_value" class="form-label"> Value </label>' +
            '<input type="text" class="form-control"  placeholder="Enter value" name="params_value[]" id="params_value"  value="">' +
            "</div>" +
            '<div class="col-sm-2 mt-5"> ' +
            '<button type="button" class="btn btn-tool remove_keyvalue_paramas_section" > <i class="text-danger bx bx-trash fa-2x"></i> </button>' +
            "</div>" +
            "</div>" +
            "</div>";
    }
    $("#formdata_params_section").append(html);
}

$(function () {
    $(document).on("click", "#product-body-tab", function (event) {
        event.preventDefault();
        $("#product-text").addClass("show");
        $("#product-text").addClass("active");
        $("#product-formdata").addClass("show");
    });
    $(document).on("click", "#product-header-tab", function (event) {
        event.preventDefault();
        if ($("#product-formdata").hasClass("show")) {
            $("#product-formdata").removeClass("active");
            $("#product-formdata").removeClass("show");
        }
        if ($("#product-text").hasClass("show")) {
            $("#product-text").removeClass("active");
            $("#product-text").removeClass("show");
        }
    });
    $(document).on("click", "#product-params-tab", function (event) {
        event.preventDefault();
        if ($("#product-formdata").hasClass("show")) {
            $("#product-formdata").removeClass("active");
            $("#product-formdata").removeClass("show");
        }
        if ($("#product-text").hasClass("show")) {
            $("#product-text").removeClass("active");
            $("#product-text").removeClass("show");
        }
    });
});

function createHeader() {
    const username = document.getElementById("converterInputAccountSID").value;
    const password = document.getElementById("converterInputAuthToken").value;

    if (username && password) {
        const stringToEncode = `${username}:${password}`;
        document.getElementById(
            "basicToken"
        ).innerText = `Authorization: Basic ${btoa(stringToEncode)}`;
    }
}
$(document).on("click", ".remove_keyvalue_header_section", function () {
    $(this).closest(".row").remove();
});
$(document).on("click", ".remove_keyvalue_paramas_section", function () {
    $(this).closest(".row").remove();
});

$(function () {
    load_sms_header_section(
        cat_html,
        true,
        sms_data.header_key,
        sms_data.header_value
    );
    load_sms_body_section(
        cat_html,
        true,
        sms_data.body_key,
        sms_data.body_value
    );
    load_sms_params_section(
        cat_html,
        true,
        sms_data.params_key,
        sms_data.params_value
    );
});

// sms gateway form submission

$(function () {
    $(document).on("click", "#sms_gateway_submit", function (event) {
        event.preventDefault();
        const form = $("#smsgateway_setting_form");
        var formData = $(form).serialize();
        $.ajax({
            type: "POST",
            url: "/admin/settings/store_sms_data",
            data: formData,
            success: function (response) {
                if (response.error == false) {
                    iziToast.success({
                        message: response.message,
                    });
                } else {
                    iziToast.error({
                        message: response.message,
                    });
                }
            },
        });

        return;
    });
});

$(document).on("click", "#add_sms_body", function (e) {
    e.preventDefault();
    load_sms_body_section(cat_html, false);
});
function load_sms_body_section(
    cat_html,
    is_edit = false,
    body_keys = [],
    body_values = []
) {
    var body_keys = sms_data.body_key;
    var body_values = sms_data.body_value;

    if (is_edit == true) {
        var html = ""; // Initialize the HTML

        if (Array.isArray(body_keys)) {
            for (var i = 0; i < body_keys.length; i++) {
                html += '<div class="form-group row key-value-pair">';
                html += '<div class="col-sm-5">';
                html +=
                    '<label for="body_key" class="form-label"> Key </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Key" name="body_key[]" value="' +
                    body_keys[i] +
                    '" id="body_key">';
                html += "</div>";
                html += '<div class="col-sm-5">';
                html +=
                    '<label for="body_value" class="form-label"> Value </label>';
                html +=
                    '<input type="text" class="form-control" placeholder="Enter Value" name="body_value[]" value="' +
                    body_values[i] +
                    '" id="body_value">';
                html += "</div>";
                html += '<div class="col-sm-2 mt-5 ">';
                html +=
                    '<button type="button" class="btn btn-tool remove_keyvalue_section"> <i class="text-danger  bx bx-trash fa-2x "></i> </button>';
                html += "</div>";
                html += "</div>";
            }
        }
    } else {
        var html =
            '<div class="form-group row key-value-pair">' +
            '<div class="col-sm-5">' +
            '<label for="body_key" class="form-label"> Key </label>' +
            '<input type="text" class="form-control"  placeholder="Enter Key" name="body_key[]"  value="" id="body_key">' +
            "</div>" +
            '<div class="col-sm-5">' +
            '<label for="body_value" class="form-label"> Value </label>' +
            '<input type="text" class="form-control"  placeholder="Enter Key" name="body_value[]"  value="" id="body_value">' +
            "</div>" +
            '<div class="col-sm-2 mt-5"> ' +
            '<button type="button" class="btn btn-tool remove_keyvalue_section" > <i class="text-danger  bx bx-trash fa-2x "></i> </button>' +
            "</div>" +
            "</div>" +
            "</div>";
    }
    var test = $("#formdata_section").append(html);
}

$(document).on("click", ".remove_keyvalue_section", function () {
    $(this).closest(".row").remove();
});

// hide and show seller password

$(function () {
    // Function to toggle password visibility
    $(".toggle_password").click(function () {
        var input = $(".show_seller_password");
        var icon = $(this).find("i");
        var type = input.attr("type") === "password" ? "text" : "password";
        input.attr("type", type);
        icon.toggleClass("bx-show bx-low-vision");
    });

    // Function to toggle confirm password visibility
    $(".toggle_confirm_password").click(function () {
        var input = $('input[name="confirm_password"]');
        var icon = $(this).find("i");
        var type = input.attr("type") === "password" ? "text" : "password";
        input.attr("type", type);
        icon.toggleClass("bx-show bx-low-vision");
    });
});

// reset select2 selected option
$(function () {
    $(document).on("click", ".offer_slider_reset_button", function () {
        $(".offer_slider_title").val("");
        $(".offer_sliders_offer").val("").trigger("change");
    });
});

$(function () {
    $(".toggle_profile_password").click(function () {
        $(this).toggleClass("show");
        var input = $(this).prev(".show_profile_password");
        if (input.attr("type") === "password") {
            input.attr("type", "text");
        } else {
            input.attr("type", "password");
        }
    });
});

$(function () {
    $(document).on("click", ".view_more_btn", function (e) {
        e.preventDefault();

        $(".remaining-stores").removeClass("d-none");
        $("#store-dropdown").addClass("show");
        return false;
    });
});
$(".toggle-seller-profile-password").click(function () {
    $(this).toggleClass("show-password");

    var input = $(this).siblings("input");
    if (input.attr("type") === "password") {
        input.attr("type", "text");
        $(this).find("i").removeClass("bx-hide").addClass("bx-show");
    } else {
        input.attr("type", "password");
        $(this).find("i").removeClass("bx-show").addClass("bx-hide");
    }
});

$(function () {
    var $delete_button = $(".delete_selected_data");
    var table_id = $delete_button.data("table-id");

    // Initially hide the delete button
    $delete_button.hide();

    // Function to toggle the delete button
    function toggle_delete_button() {
        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        // Show or hide the delete button based on selection
        if (selected_ids.length > 0) {
            $delete_button.show();
        } else {
            $delete_button.hide();
        }
    }

    // Bind event to selection change
    $("#" + table_id).on(
        "check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table",
        toggle_delete_button
    );

    $(".delete_selected_data").on("click", function () {
        var delete_url = $(this).data("delete-url");

        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        if (selected_ids.length === 0) {
            iziToast.error({
                message: "Please select at least one data to delete.",
            });
            return;
        }

        if (confirm("Are you sure you want to delete the selected data?")) {
            $.ajax({
                url: delete_url,
                type: "DELETE",
                data: {
                    ids: selected_ids,
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    // console.log(response);

                    // Check if the response contains an error flag
                    if (response.error == true) {
                        // console.log("here");
                        // Manually trigger the error handler
                        iziToast.error({
                            title: "Error",
                            message:
                                response.error_message || "An error occurred.",
                            position: "topRight",
                        });
                    } else {
                        iziToast.success({
                            message:
                                response.message ||
                                "Data deleted successfully.",
                        });

                        $("#" + table_id).bootstrapTable("refresh", {
                            silent: true,
                        });

                        // Clear the selection
                        $("#" + table_id).bootstrapTable("uncheckAll");

                        // Hide the delete button after successful deletion
                        toggle_delete_button();
                    }
                },
                error: function (xhr) {
                    iziToast.error({
                        message:
                            xhr.responseJSON.error ||
                            "Error occurred while deleting data.",
                    });
                    $("#" + table_id).bootstrapTable("refresh", {
                        silent: true,
                    });
                },
            });
        }
    });
});

$(function () {
    var $update_button = $(".bulk_update_deliverability_data");
    var table_id = $update_button.data("table-id");

    // Initially hide the update button
    $update_button.hide();

    // Function to toggle the update button
    function toggle_update_button() {
        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        // Show or hide the update button based on selection
        if (selected_ids.length > 0) {
            $update_button.show();
        } else {
            $update_button.hide();
        }
    }

    // Bind event to selection change
    $("#" + table_id).on(
        "check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table",
        toggle_update_button
    );

    // Open Modal and Pass IDs
    $(".bulk_update_deliverability_data").on("click", function () {
        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        if (selected_ids.length === 0) {
            iziToast.error({
                message: "Please select at least one product.",
            });
            return;
        }

        // Pass IDs to hidden input field in modal
        $("#product_id").val(selected_ids.join(","));

        // Open the modal
        $("#deliverabilityModal").modal("show");
    });

    $("#deliverabilityForm").on("submit", function (e) {
        e.preventDefault();

        var formData = {
            product_id: $("#product_id").val(),
            deliverable_type: $("#deliverable_type").val(),
            deliverable_zones: $("#deliverable_zones").val(),
            _token: $('meta[name="csrf-token"]').attr("content"),
        };

        $.ajax({
            url: "{{ route('seller.deliverability.bulk.update') }}",
            type: "POST",
            data: formData,
            success: function (response) {
                iziToast.success({
                    message: response.message,
                });

                $("#" + table_id).bootstrapTable("refresh", { silent: true });

                $("#deliverabilityModal").modal("hide");

                $("#" + table_id).bootstrapTable("uncheckAll");
                toggle_update_button();
            },
            error: function (xhr) {
                iziToast.error({
                    message:
                        xhr.responseJSON.error ||
                        "Error occurred while updating deliverability.",
                });
            },
        });
    });
});
$(function () {
    var $update_button = $(".bulk_update_combo_deliverability_data");
    var table_id = $update_button.data("table-id");

    // Initially hide the update button
    $update_button.hide();

    // Function to toggle the update button
    function toggle_update_button() {
        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        // Show or hide the update button based on selection
        if (selected_ids.length > 0) {
            $update_button.show();
        } else {
            $update_button.hide();
        }
    }

    // Bind event to selection change
    $("#" + table_id).on(
        "check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table",
        toggle_update_button
    );

    // Open Modal and Pass IDs
    $(".bulk_update_combo_deliverability_data").on("click", function () {
        var selected_ids = $("#" + table_id)
            .bootstrapTable("getSelections")
            .map(function (row) {
                return row.id;
            });

        if (selected_ids.length === 0) {
            iziToast.error({
                message: "Please select at least one product.",
            });
            return;
        }

        // Pass IDs to hidden input field in modal
        $("#product_id").val(selected_ids.join(","));

        // Open the modal
        $("#deliverabilityModal").modal("show");
    });

    $("#combodeliverabilityForm").on("submit", function (e) {
        e.preventDefault();

        var formData = {
            product_id: $("#product_id").val(),
            deliverable_type: $("#deliverable_type").val(),
            deliverable_zones: $("#deliverable_zones").val(),
            _token: $('meta[name="csrf-token"]').attr("content"),
        };

        $.ajax({
            url: "{{ route('seller.combo.deliverability.bulk.update') }}",
            type: "POST",
            data: formData,
            success: function (response) {
                iziToast.success({
                    message: response.message,
                });

                $("#" + table_id).bootstrapTable("refresh", { silent: true });

                $("#deliverabilityModal").modal("hide");

                $("#" + table_id).bootstrapTable("uncheckAll");
                toggle_update_button();
            },
            error: function (xhr) {
                iziToast.error({
                    message:
                        xhr.responseJSON.error ||
                        "Error occurred while updating deliverability.",
                });
            },
        });
    });
});

$(function () {
    $(".repeater").repeater({
        // (Optional)
        // start with an empty list of repeaters. Set your first (and only)
        // "data-repeater-item" with style="display:none;" and pass the
        // following configuration flag
        initEmpty: false,
        // (Optional)
        // "defaultValues" sets the values of added items.  The keys of
        // defaultValues refer to the value of the input's name attribute.
        // If a default value is not specified for an input, then it will
        // have its value cleared.
        defaultValues: {
            "text-input": "",
        },
        // (Optional)
        // "show" is called just after an item is added.  The item is hidden
        // at this point.  If a show callback is not given the item will
        // have $(this).show() called on it.
        show: function () {
            $(this).slideDown();
        },
        // (Optional)
        // "hide" is called when a user clicks on a data-repeater-delete
        // element.  The item is still visible.  "hide" is passed a function
        // as its first argument which will properly remove the item.
        // "hide" allows for a confirmation step, to send a delete request
        // to the server, etc.  If a hide callback is not given the item
        // will be deleted.
        hide: function (deleteElement) {
            if (confirm("Are you sure you want to delete this element?")) {
                $(this).slideUp(deleteElement);
            }
        },
        // (Optional)
        // You can use this if you need to manually re-index the list
        // for example if you are using a drag and drop library to reorder
        // list items.

        // (Optional)
        // Removes the delete button from the first list item,
        // defaults to false.
        isFirstItemUndeletable: true,
    });
});

// $(function () {
//     // Function to initialize select2

//     function initializeSelect2() {
//         $(".zipcode_list").select2({
//             ajax: {
//                 url: appUrl + from + "/area/get_zipcodes",
//                 type: "GET",
//                 dataType: "json",
//                 delay: 250,
//                 data: function (params) {
//                     return {
//                         search: params.term,
//                     };
//                 },
//                 processResults: function (response) {
//                     return {
//                         results: response.map(function (item) {
//                             return {
//                                 id: item.id,
//                                 text: item.text || item.zipcode,
//                             };
//                         }),
//                     };
//                 },
//                 cache: true,
//             },
//             placeholder: "Search for zipcodes",
//         });

//         // Ensure pre-selected zipcode is displayed correctly
//         let selectedZipcode = $(".zipcode_list").find("option:selected").val();
//         if (selectedZipcode) {
//             let selectedText = $(".zipcode_list")
//                 .find("option:selected")
//                 .text();

//             let newOption = new Option(
//                 selectedText,
//                 selectedZipcode,
//                 true,
//                 true
//             );
//             $(".zipcode_list").append(newOption).trigger("change");
//         }
//     }

//     initializeSelect2();
// });

$(function () {
    function initializeSelect2() {
        $(".zipcode_list").select2({
            ajax: {
                url: appUrl + from + "/area/get_zipcodes",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                    };
                },
                processResults: function (response) {
                    return {
                        results: response.map(function (item) {
                            return {
                                id: item.id,
                                text: item.text || item.zipcode,
                            };
                        }),
                    };
                },
                cache: true,
            },
            placeholder: "Search for zipcodes",
        });

        // Set selected option from data-* attributes
        const selectedId = $(".zipcode_list").data("selected-id");
        const selectedText = $(".zipcode_list").data("selected-text");

        if (selectedId && selectedText) {
            const newOption = new Option(selectedText, selectedId, true, true);
            $(".zipcode_list").append(newOption).trigger("change");
        }
    }

    initializeSelect2();
});

// Initialize zone zipcode Select2

$(function () {
    function initializeZoneZipcodeSelect2(element) {
        element.select2({
            ajax: {
                url: appUrl + from + "/area/get_zipcodes",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                    };
                },
                processResults: function (response) {
                    return {
                        results: response,
                    };
                },
                cache: true,
            },
            placeholder: "Search for zipcodes",
            allowClear: true,
        });
    }

    // Initialize all existing `.zone_zipcode_list` selects
    $(".zone_zipcode_list").each(function () {
        initializeZoneZipcodeSelect2($(this));
    });

    // Handle dynamically added elements
    $(".repeater").on("click", "[data-repeater-create]", function () {
        setTimeout(function () {
            $(".repeater .zone_zipcode_list")
                .last()
                .each(function () {
                    initializeZoneZipcodeSelect2($(this));
                });
        }, 100);
    });
});

// Initialize city  Select2

$(function () {
    function initializeZoneCitySelect2(element) {
        element.select2({
            ajax: {
                url: appUrl + from + "/area/get_cities",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                    };
                },
                processResults: function (response) {
                    return {
                        results: response,
                    };
                },
                cache: true,
            },
            placeholder: "Search for cities",
            allowClear: true,
        });
    }

    // Initialize all existing `.zone_city_list` selects
    $(".zone_city_list").each(function () {
        initializeZoneCitySelect2($(this));
    });

    // Handle dynamically added elements
    $(".repeater").on("click", "[data-repeater-create]", function () {
        setTimeout(function () {
            $(".repeater .zone_city_list")
                .last()
                .each(function () {
                    initializeZoneCitySelect2($(this));
                });
        }, 100);
    });
});

$(function () {
    // Function to initialize select2 for .city_list elements
    function initializeCitySelect2() {
        $(".city_list").select2({
            ajax: {
                url: appUrl + from + "/area/get_cities",
                type: "GET",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                    };
                },
                processResults: function (response) {
                    return {
                        results: response.map(function (item) {
                            return {
                                id: item.id,
                                text: item.text || item.name,
                            };
                        }),
                    };
                },
                cache: true,
            },
            dropdownParent: $(".city_list_parent").last(),
            placeholder: "Search for cities",
        });
        // Ensure pre-selected zipcode is displayed correctly
        let selectedZipcode = $(".city_list").find("option:selected").val();
        if (selectedZipcode) {
            let selectedText = $(".city_list").find("option:selected").text();

            let newOption = new Option(
                selectedText,
                selectedZipcode,
                true,
                true
            );
            $(".city_list").append(newOption).trigger("change");
        }
    }

    initializeCitySelect2();

    $(".repeater").on("click", "[data-repeater-create]", function () {
        setTimeout(function () {
            initializeCitySelect2();
        }, 100);
    });
});

// create parcel modal

function parcelModal(seller_id = null) {
    if (from == "admin") {
    }

    let productVariantIds = [];
    let productName = [];
    let orderItemId = [];
    $(".product_variant_id").each(function () {
        productVariantIds.push($(this).val());
    });
    $(".product_name").each(function () {
        productName.push($(this).val());
    });
    $(".order_item_id").each(function () {
        orderItemId.push($(this).val());
    });

    var modalBody = document.getElementById("product_details");
    modalBody.innerHTML = "";
    for (var i = 0; i < productVariantIds.length; i++) {
        const data = JSON.parse(
            $("#product_variant_id_" + productVariantIds[i]).html()
        );

        const quantity = parseInt(data.quantity);
        const unit_price = parseInt(data.unit_price);
        const delivered_quantity = parseInt(data.delivered_quantity);
        if (
            delivered_quantity != quantity &&
            data.active_status != "cancelled" &&
            data.active_status != "delivered"
        ) {
            $("#empty_box_body").addClass("d-none");
            $("#modal-body").removeClass("d-none");
            let row =
                "<tr>" +
                "<th scope='row'>" +
                (i + 1) +
                "</th>" +
                "<td>" +
                productName[i] +
                "</td>" +
                "<td>" +
                productVariantIds[i] +
                "</td>" +
                "<td>" +
                quantity +
                "</td>" +
                "<td>" +
                unit_price +
                "</td>" +
                `<td><label for="checkbox-${productVariantIds[i]}"><input type="checkbox" data-item-id="${orderItemId[i]}" name="checkbox-${productVariantIds[i]}" id="checkbox-${productVariantIds[i]}" class="form-check-input product-to-ship"></label></td>`;
            ("</tr>");

            modalBody.innerHTML += row;
        }
    }
    if (modalBody.innerHTML == "") {
        $("#empty_box_body").removeClass("d-none");
        $("#modal-body").addClass("d-none");

        let empty_box_body = document.getElementById("empty_box_body");
        empty_box_body.innerHTML = "";
        let row = "<h5 class='text-center'>Items Are Already Shipped.</h5>";
        empty_box_body.innerHTML += row;
    }
}

// ship parcel

$(document).on("click", "#ship_parcel_btn", function (e) {
    e.preventDefault();
    let product_to_ship = $(".product-to-ship:checked");
    let parcel_title = $("#parcel_title").val();
    let order_id = $("#order_id").val();
    let parcel_order_type = $("#parcel_order_type").val();

    let selected_items = [];
    product_to_ship.each(function () {
        selected_items.push($(this).data("item-id"));
    });
    $.ajax({
        type: "POST",
        url: appUrl + from + "/orders/create_parcel",
        data: {
            parcel_title,
            selected_items,
            order_id,
            parcel_order_type,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },
        beforeSend: function () {
            $("#ship_parcel_btn").html("Please wait");
            $("#ship_parcel_btn").attr("disabled", true);
        },
        success: function (response) {
            if (response.error == false) {
                iziToast.success({
                    message: response.message,
                });
                response.data.map((val) => {
                    $("#product_variant_id_" + val.product_variant_id).html(
                        JSON.stringify(val)
                    );
                });
                $("#parcel_table").bootstrapTable("refresh");
                $("#seller_parcel_table").bootstrapTable("refresh");
                $("#create_parcel_modal").modal("hide");
            } else {
                iziToast.error({
                    message: response.message,
                });
            }
        },
        error: function (xhr) {
            var errors = xhr.responseJSON.errors;
            for (var key in errors) {
                if (errors.hasOwnProperty(key)) {
                    var errorMessages = errors[key];
                    iziToast.error({
                        title: "Error",
                        message: errorMessages,
                        position: "topRight",
                    });
                }
            }
            $("#ship_parcel_btn").html("Ship").attr("disabled", false);
        },
    });
});
// view parcel items
$(document).on("show.bs.modal", "#view_parcel_items_modal", function (event) {
    let triggerElement = $(event.relatedTarget);
    current_selected_image = triggerElement;
    let parcel_items = $(current_selected_image).data("items");
    let modalBody = document.getElementById("parcel_product_details");
    modalBody.innerHTML = "";
    let count = 1;
    parcel_items.forEach((item) => {
        let status = "";
        if (item.item_status === "awaiting") {
            status =
                '<label class="badge bg-secondary">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (item.item_status === "received") {
            status =
                '<label class="badge bg-primary">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (item.item_status === "processed") {
            status =
                '<label class="badge bg-info">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (item.item_status === "shipped") {
            status =
                '<label class="badge bg-warning">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (item.item_status === "delivered") {
            status =
                '<label class="badge bg-success">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (
            item.item_status === "returned" ||
            item.item_status === "cancelled"
        ) {
            status =
                '<label class="badge bg-danger">' +
                capitalizeFirstLetter(item.item_status) +
                "</label>";
        } else if (item.item_status === "return_request_decline") {
            status =
                '<label class="badge bg-danger">' +
                formatStatus(item.item_status) +
                "</label>";
        } else if (item.item_status === "return_request_approved") {
            status =
                '<label class="badge bg-success">' +
                formatStatus(item.item_status) +
                "</label>";
        } else if (item.item_status === "return_request_pending") {
            status =
                '<label class="badge bg-secondary">' +
                formatStatus(item.item_status) +
                "</label>";
        }
        var row =
            "<tr>" +
            "<th scope='row'>" +
            count +
            "</th>" +
            "<td>" +
            item.product_name +
            "</td>" +
            `<td><a href='${item.image}' class="order-image-box">
                <img src='${item.image}' alt="${item.product_name}" class="image-box"></a></td>` +
            "<td>" +
            item.quantity +
            "</td>" +
            "<td>" +
            status +
            "</td>" +
            "</tr>";

        modalBody.innerHTML += row;
        count++;
    });
});

// parcel update status modal

$(document).on("hide.bs.modal", "#parcel_status_modal", function () {
    $("#parcel-items-container").empty();
    $("#tracking_box").empty();
    $("#tracking_box_old").empty();
    $(".shiprocket_order_box").removeClass("d-none");
    $(".manage_shiprocket_box").addClass("d-none");
});
$(document).on("show.bs.modal", "#parcel_status_modal", function (event) {
    let triggerElement = $(event.relatedTarget);
    current_selected_image = triggerElement;

    let parcel_items = $(current_selected_image).data("items");
    let order_tracking = $("#order_tracking").val();
    if (order_tracking != undefined) {
        order_tracking = JSON.parse(order_tracking);
    }

    $("#parcel_data").val(JSON.stringify(parcel_items));
    const container = document.getElementById("parcel-items-container");
    const tracking_box = document.getElementById("tracking_box");
    const tracking_box_old = document.getElementById("tracking_box_old");
    if (order_tracking != undefined) {
        order_tracking.forEach((tracking) => {
            if (tracking.parcel_id == parcel_items[0].parcel_id) {
                if (tracking.is_canceled == 0) {
                    $(".shiprocket_order_box").addClass("d-none");
                    $(".manage_shiprocket_box").removeClass("d-none");
                    let div = document.createElement("div");
                    div.innerHTML = `
                            <div class="accordion  mb-3" id="shiprocketOrderAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingOne">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                            Shiprocket Order Details
                                        </button>
                                    </h2>
                                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#shiprocketOrderAccordion">
                                        <div class="accordion-body">
                                            <p><strong>Shiprocket Order ID:</strong> <span class="text-dark">${tracking.shiprocket_order_id}</span></p>
                                            <p><strong>Shiprocket Tracking ID:</strong> <span class="text-dark">${tracking.tracking_id}</span></p>
                                            <p><strong>Shiprocket Tracking URL:</strong> <a href="${tracking.url}" target="_blank" class="text-primary">${tracking.url}</a></p>
                                        </div>
                                        <input type="hidden" name="shiprocket_tracking_id" id="shiprocket_tracking_id" value="${tracking.tracking_id}">
                                        <input type="hidden" name="shiprocket_order_id" id="shiprocket_order_id" value="${tracking.shiprocket_order_id}">
                                    </div>
                                </div>
                            </div>
                        `;

                    tracking_box.appendChild(div);
                } else {
                    let div = document.createElement("div");

                    div.innerHTML = `
                        <hr><h5>Cancelled Shiprocket Order Details</h5>
                        <p class="mb-0 text-bold"><span class="text-black-50">Shiprocket Order Id:</span> ${tracking.shiprocket_order_id}</p>
                        <p class="m-0 text-bold"><span class="text-black-50">Shiprocket Tracking Id:</span> ${tracking.tracking_id}</p>
                        <p class="m-0 text-bold"><span class="text-black-50">Shiprocket Tracking url:</span> <a href="${tracking.url}" target="_blank" class="text-primary">${tracking.url}</a></p><hr>
                        `;
                    tracking_box_old.appendChild(div);
                }
            }
        });
    }
    const card = document.createElement("div");
    card.className = "card p-3 border";
    let count = 1;
    card.innerHTML = `
    <table class="table">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Name</th>
                <th scope="col">Image</th>
                <th scope="col">Quantity</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
`;
    const tbody = card.querySelector("tbody");

    parcel_items.forEach((element) => {
        let status = "";
        if (element.item_status === "awaiting") {
            status =
                '<label class="badge bg-secondary">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (element.item_status === "received") {
            status =
                '<label class="badge bg-primary">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (element.item_status === "processed") {
            status =
                '<label class="badge bg-info">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (element.item_status === "shipped") {
            status =
                '<label class="badge bg-warning">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (element.item_status === "delivered") {
            status =
                '<label class="badge bg-success">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (
            element.item_status === "returned" ||
            element.item_status === "cancelled"
        ) {
            status =
                '<label class="badge bg-danger">' +
                capitalizeFirstLetter(element.item_status) +
                "</label>";
        } else if (element.item_status === "return_request_decline") {
            status =
                '<label class="badge bg-danger">' +
                formatStatus(element.item_status) +
                "</label>";
        } else if (element.item_status === "return_request_approved") {
            status =
                '<label class="badge bg-success">' +
                formatStatus(element.item_status) +
                "</label>";
        } else if (element.item_status === "return_request_pending") {
            status =
                '<label class="badge bg-secondary">' +
                formatStatus(element.item_status) +
                "</label>";
        }

        $("#parcel_id").val(element.parcel_id);
        $("#deliver_by").val(element.delivery_boy_id);
        $(".parcel_status").val(element.active_status);
        tbody.innerHTML += `
        <tr>
            <td>${count++}</td>
            <td>${element.product_name}</td>
            <td><a href='${element.image
            }' class="image-box-100" data-toggle='lightbox' data-gallery='order-images'> <img src='${element.image
            }' alt="${element.product_name}"></a></td>
            <td>${element.quantity}</td>
            <td>${status}</td>
        </tr>
    `;
    });
    container.appendChild(card);
});

// Utility functions
function capitalizeFirstLetter(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatStatus(status) {
    return capitalizeFirstLetter(status.replace(/_/g, " "));
}
//  update parcel order status

$(document).on("click", ".parcel_order_status_update", function (e) {
    let parcel_id = $("#parcel_id").val();
    let status = $(".parcel_status").val();
    if (status == "" || status == null) {
        iziToast.error({
            message: "Please Select Status",
        });
        return false;
    }
    let deliver_by = $("#deliver_by").val();
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + from + "/orders/update_order_status",
                    data: {
                        parcel_id,
                        status,
                        deliver_by,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },

                    dataType: "json",
                    success: function (result) {
                        if (result["error"] == false) {
                            $("#parcel_table").bootstrapTable("refresh");
                            $("#seller_parcel_table").bootstrapTable("refresh");
                            iziToast.success({
                                message: result["message"],
                            });
                            result.data.forEach((element) => {
                                $(".status-" + element["order_item_id"])
                                    .addClass("badge-info")
                                    .html(element["status"]);
                            });
                        } else {
                            iziToast.error({
                                message: result["message"],
                            });
                        }
                        swal.close();
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
});

// update digital order status
$(document).on("click", ".digital_order_status_update", function (e) {
    let status = $(".digital_order_status").val();
    const order_item_ids = $(".selected_order_item_ids:checked")
        .map(function () {
            return $(this).val();
        })
        .get();
    let order_id = $("#order_id").val();
    if (status == "" || status == null) {
        iziToast.error({
            message: "Please Select Status",
        });
        return false;
    }
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: appUrl + from + "/orders/update_order_status",
                    data: {
                        order_id,
                        order_item_ids,
                        status,
                        type: "digital",
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },

                    dataType: "json",
                    success: function (result) {
                        if (result["error"] == false) {
                            iziToast.success({
                                message: result["message"],
                            });
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                            result.data.forEach((element) => {
                                $(".status-" + element["order_item_id"])
                                    .addClass("badge-info")
                                    .html(element["status"]);
                            });
                        } else {
                            iziToast.error({
                                message: result["message"],
                            });
                        }
                        swal.close();
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
});
// delete parcel

function delete_parcel(id) {
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "post",
                    url: appUrl + from + "/orders/delete_parcel",
                    data: {
                        id,
                    },
                    dataType: "json",
                    success: function (response) {
                        if (response.error == true) {
                            Swal.fire("error", response.message, "error");
                        } else {
                            response.data.map((val) => {
                                $(
                                    "#product_variant_id_" +
                                    val.product_variant_id
                                ).html(JSON.stringify(val));
                            });
                            iziToast.success({
                                message: response.message,
                            });
                            Swal.fire("Success", "Parcel Deleted !", "success");
                        }
                        $("#parcel_table").bootstrapTable("refresh");
                        $("#seller_parcel_table").bootstrapTable("refresh");
                    },
                    error: function (xhr) {
                        var errors = xhr.responseJSON.errors;
                        for (var key in errors) {
                            if (errors.hasOwnProperty(key)) {
                                var errorMessages = errors[key];
                                iziToast.error({
                                    title: "Error",
                                    message: errorMessages,
                                    position: "topRight",
                                });
                            }
                        }
                        $("#ship_parcel_btn")
                            .html("Ship")
                            .attr("disabled", false);
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
}
$(document).on("click", ".refresh_shiprocket_status", function (e) {
    let tracking_id = $("#shiprocket_tracking_id").val();
    if (tracking_id == undefined || tracking_id == "" || tracking_id == null) {
        iziToast.error({
            message: "Tracking Id is Required",
        });
        return false;
    }
    $.ajax({
        type: "POST",
        url: appUrl + from + "/orders/update_shiprocket_order_status",
        data: { tracking_id },
        dataType: "json",
        success: function (response) {
            if (response.error == false) {
                $("#parcel_table").bootstrapTable("refresh");
                iziToast.success({
                    message: response.message,
                });
                response.data.forEach((element) => {
                    $(".status-" + element["order_item_id"])
                        .addClass("badge-info")
                        .html(element["status"]);
                });
                $("#parcel_status_modal").modal("hide");

                return;
            }
            iziToast.error({
                message: response.message,
            });
            return false;
        },
    });
});
$(document).on("change", ".parcel_status", function (e) {
    let status = $(this).val();
    if (status == "delivered") {
        return $(".otp-field").removeClass("d-none");
    }
    $(".otp-field").addClass("d-none");
});
$(document).on("click", ".update_status_delivery_boy", function (e) {
    let parcel_id = $(this).data("id");
    let otp_system = $(this).data("otp-system");

    let status = $(".parcel_status").val();
    let post_otp = $("#otp").val();

    if (status == "" || status == undefined) {
        return iziToast.error({
            message: "Please Fill Status",
        });
    }
    if (
        otp_system == 1 &&
        status == "delivered" &&
        post_otp == "" &&
        post_otp == undefined
    ) {
        return iziToast.error({
            message: "Please Enter Otp",
        });
    }
    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url:
                        appUrl + "delivery_boy/orders/update_order_item_status",
                    data: {
                        id: parcel_id,
                        status: status,
                        otp: post_otp,
                    },
                    dataType: "json",
                    success: function (result) {
                        if (result["error"] == false) {
                            iziToast.success({
                                message: result["message"],
                            });
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        } else {
                            iziToast.error({
                                message: result["message"],
                            });
                        }
                        swal.close();
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
});
$(document).on("click", ".update_return_status_delivery_boy", function (e) {
    let order_item_id = $(this).data("id");

    let status = $(".order_item_status").val();

    Swal.fire({
        title: "Are You Sure?",
        text: "You won't be able to revert this!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        showLoaderOnConfirm: true,
        preConfirm: function () {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url:
                        appUrl +
                        "delivery_boy/orders/update_return_order_item_status",
                    data: {
                        order_item_id: order_item_id,
                        status: status,
                        _token: $('meta[name="csrf-token"]').attr("content"),
                    },
                    dataType: "json",
                    success: function (result) {
                        if (result["error"] == false) {
                            iziToast.success({
                                message: result["message"],
                            });
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        } else {
                            iziToast.error({
                                message: result["message"],
                            });
                        }
                        swal.close();
                    },
                });
            });
        },
        allowOutsideClick: false,
    });
});
// Open Modal and Fill Data

$(document).on("click", ".edit-deliverability", function () {
    let productId = $(this).data("id");
    let deliverableType = $(this).data("type");
    let selectedZones = $(this).data("zones");

    $("#product_id").val(productId);
    $("#deliverable_type").val(deliverableType);
    toggleDeliverableZones(deliverableType); // <- This ensures correct state on modal open

    // Fetch zones...
    $.ajax({
        url: appUrl + from + "/zones/seller_zones_data",
        dataType: "json",
        delay: 250,
        data: {
            seller_id: $("#seller_id").val(),
        },
        success: function (response) {
            let allZones = response.results || [];

            let preSelectedZones = selectedZones.map((zone) => ({
                id: zone.id,
                text: zone.name,
                serviceable_cities: zone.serviceable_cities || "",
                serviceable_zipcodes: zone.serviceable_zipcodes || "",
            }));

            let zoneOptions = [
                ...preSelectedZones,
                ...allZones.filter(
                    (zone) =>
                        !preSelectedZones.some(
                            (selected) => selected.id == zone.id
                        )
                ),
            ];

            $("#deliverable_zones").empty().select2({
                data: zoneOptions,
                templateResult: formatZones,
                templateSelection: formatzonesSelection,
                allowClear: true,
            });

            $("#deliverable_zones")
                .val(preSelectedZones.map((zone) => zone.id))
                .trigger("change");
        },
    });

    $("#deliverabilityModal").modal("show");
});

// Submit Form via AJAX
$("#deliverabilityForm").submit(function (e) {
    e.preventDefault();

    let formData = $(this).serialize();

    $.ajax({
        url: appUrl + from + "/update_product_deliverability",
        type: "POST",
        data: formData,
        success: function (response) {
            // console.log(response);
            $("#deliverabilityModal").modal("hide");
            $("#seller_deliverability_table").bootstrapTable("refresh");
            if (response.error == false) {
                iziToast.success({
                    message: response.message,
                });
            } else {
                iziToast.error({
                    message: "Something went wrong! Try again.",
                });
            }
        },
        error: function (error) {
            iziToast.error({
                message: "Something went wrong! Try again.",
            });
        },
    });
});
$("#combodeliverabilityForm").submit(function (e) {
    e.preventDefault();

    let formData = $(this).serialize();

    $.ajax({
        url: appUrl + from + "/update_combo_product_deliverability",
        type: "POST",
        data: formData,
        success: function (response) {
            // console.log(response);
            $("#deliverabilityModal").modal("hide");
            $("#seller_combo_deliverability_table").bootstrapTable("refresh");
            if (response.error == false) {
                iziToast.success({
                    message: response.message,
                });
            } else {
                iziToast.error({
                    message: "Something went wrong! Try again.",
                });
            }
        },
        error: function (error) {
            iziToast.error({
                message: "Something went wrong! Try again.",
            });
        },
    });
});

$(function () {
    $(".products_display_style_for_web").on("change", function () {
        var selectedStyle = $(this).val();
        var iframe = document.getElementById(
            "products_display_style_for_web_iframe"
        ).contentWindow;

        // Send selected style to the iframe
        iframe.postMessage(selectedStyle, "*");
    });

    // Trigger change event on page load to set initial view
    $(".products_display_style_for_web").trigger("change");
});
$(function () {
    $(".categories_display_style_for_web").on("change", function () {
        var selectedStyle = $(this).val();
        var iframe = document.getElementById(
            "categories_display_style_for_web_iframe"
        ).contentWindow;

        // Send selected style to the iframe
        iframe.postMessage(selectedStyle, "*");
    });

    // Trigger change event on page load to set initial view
    $(".categories_display_style_for_web").trigger("change");
});
$(function () {
    $(".brands_display_style_for_web").on("change", function () {
        var selectedStyle = $(this).val();
        var iframe = document.getElementById(
            "brands_display_style_for_web_iframe"
        ).contentWindow;

        // Send selected style to the iframe
        iframe.postMessage(selectedStyle, "*");
    });

    // Trigger change event on page load to set initial view
    $(".brands_display_style_for_web").trigger("change");
});
$(function () {
    $(".wishlist_display_style_for_web").on("change", function () {
        var selectedStyle = $(this).val();
        var iframe = document.getElementById(
            "wishlist_display_style_for_web_iframe"
        ).contentWindow;

        // Send selected style to the iframe
        iframe.postMessage(selectedStyle, "*");
    });

    // Trigger change event on page load to set initial view
    $(".wishlist_display_style_for_web").trigger("change");
});

$(function () {
    function updateProductCardIframe(selectedStyle) {
        var iframe = document.getElementById(
            "products_display_style_for_web_iframe"
        );

        if (!iframe) return;

        // Ensure iframe src is updated only when selection changes
        iframe.src = "/admin/web_product_card_style";

        // Wait for iframe to load before sending postMessage
        iframe.onload = function () {
            iframe.contentWindow.postMessage(selectedStyle, "*");
        };
    }

    // Get the initially selected value
    var selectedStyle = $(".products_display_style_for_web").val();

    // Update iframe on page load
    updateProductCardIframe(selectedStyle);
});
$(function () {
    function updateCategoryCardIframe(selectedStyle) {
        var iframe = document.getElementById(
            "categories_display_style_for_web_iframe"
        );

        if (!iframe) return;

        // Ensure iframe src is updated only when selection changes
        iframe.src = "/admin/web_categories_style";

        // Wait for iframe to load before sending postMessage
        iframe.onload = function () {
            iframe.contentWindow.postMessage(selectedStyle, "*");
        };
    }

    // Get the initially selected value
    var selectedStyle = $(".categories_display_style_for_web").val();

    // Update iframe on page load
    updateCategoryCardIframe(selectedStyle);
});
$(function () {
    function updateBrandCardIframe(selectedStyle) {
        var iframe = document.getElementById(
            "brands_display_style_for_web_iframe"
        );

        if (!iframe) return;

        // Ensure iframe src is updated only when selection changes
        iframe.src = "/admin/web_brands_style";

        // Wait for iframe to load before sending postMessage
        iframe.onload = function () {
            iframe.contentWindow.postMessage(selectedStyle, "*");
        };
    }

    // Get the initially selected value
    var selectedStyle = $(".brands_display_style_for_web").val();

    // Update iframe on page load
    updateBrandCardIframe(selectedStyle);
});
$(function () {
    function updateWishlistCardIframe(selectedStyle) {
        var iframe = document.getElementById(
            "wishlist_display_style_for_web_iframe"
        );

        if (!iframe) return;

        // Ensure iframe src is updated only when selection changes
        iframe.src = "/admin/web_wishlist_style";

        // Wait for iframe to load before sending postMessage
        iframe.onload = function () {
            iframe.contentWindow.postMessage(selectedStyle, "*");
        };
    }

    // Get the initially selected value
    var selectedStyle = $(".wishlist_display_style_for_web").val();

    // Update iframe on page load
    updateWishlistCardIframe(selectedStyle);
});
$(document).on("click", ".edit-language", function () {
    var languageId = $(this).data("id");
    var languageName = $(this).data("name");
    var languageCode = $(this).data("code");

    // Set values in modal fields
    $("#language_id").val(languageId);
    $("#language_name").val(languageName);
});

// Handle AJAX form submission
$("#editLanguageForm").on("submit", function (e) {
    e.preventDefault(); // Prevent default form submission

    var languageId = $("#language_id").val();
    var formData = {
        _token: $('meta[name="csrf-token"]').attr("content"),
        language: $("#language_name").val(),
        _method: "PUT",
    };

    $.ajax({
        url: "/languages/update/" + languageId,
        type: "POST",
        data: formData,
        success: function (response) {
            if (response.error == false) {
                iziToast.success({
                    message: response.message,
                });
                $("#editLanguageModal").modal("hide");
                location.reload();
            } else {
                iziToast.error({
                    message: response.message,
                });
            }
        },
        error: function (xhr) {
            iziToast.error({
                message: xhr.responseJSON.message,
            });
        },
    });
});

// Handle delete action
$(document).on("click", ".delete-language", function () {
    var deleteUrl = $(this).data("url");

    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: deleteUrl,
                type: "DELETE",
                data: {
                    _token: $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    if (response.error == false) {
                        iziToast.success({
                            message: response.message,
                        });
                        location.reload();
                    }
                },
                error: function () {
                    Swal.fire("Error!", "Something went wrong!", "error");
                },
            });
        }
    });
});

$(function () {
    function initCategorySelect(storeId) {
        $("#seller_categories").select2({
            ajax: {
                url: appUrl + "seller/categories/get_category_details",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        store_id: storeId,
                        search: params.term,
                        limit: 10,
                    };
                },
                processResults: function (response) {
                    let categories = [];

                    // Process categories
                    response.results.forEach((category) => {
                        if (category.parent_id == 0) {
                            // Parent category should also be selectable
                            let parentCategory = {
                                id: category.id,
                                text: category.text,
                            };

                            // Find children
                            let children = response.results
                                .filter(
                                    (child) => child.parent_id === category.id
                                )
                                .map((child) => ({
                                    id: child.id,
                                    text: `\u00A0\u00A0\u00A0 ${child.text}`,
                                }));

                            if (children.length > 0) {
                                categories.push(parentCategory, ...children);
                            } else {
                                categories.push(parentCategory);
                            }
                        }
                    });

                    return {
                        results:
                            categories.length > 0
                                ? categories
                                : response.results.map((cat) => ({
                                    id: cat.id,
                                    text: cat.text,
                                })),
                    };
                },
                cache: true,
            },
            placeholder:
                $("#seller_categories").data("placeholder") ||
                "Search for categories",
            width: $("#seller_categories").data("width") || "100%",
            allowClear: Boolean($("#seller_categories").data("allow-clear")),
            escapeMarkup: function (markup) {
                return markup;
            },
        });
    }

    // Initialize Select2 with first store's ID
    let defaultStoreId = $(".seller_register_store_id").val();
    initCategorySelect(defaultStoreId);

    // When store changes, reinitialize Select2 with new store ID
    $(".seller_register_store_id").on("change", function () {
        let storeId = $(this).val();
        $("#seller_categories").val(null).trigger("change");
        initCategorySelect(storeId);
    });
});
$("#translation_bulk_upload_form").on("submit", function (e) {
    e.preventDefault();

    var type = $("#type").val();
    var submitButton = $(".submit_button"); // Assuming your submit button has this ID

    if (type != "") {
        var formdata = new FormData(this);
        token = $('meta[name="csrf-token"]').attr("content");

        // Disable button and change text to "Please wait..."
        submitButton.prop("disabled", true).text("Please wait...");

        $.ajax({
            type: "POST",
            data: formdata,
            url: $(this).attr("action"),
            dataType: "json",
            cache: false,
            contentType: false,
            processData: false,

            success: function (result) {
                console.log(result);
                token = $('meta[name="csrf-token"]').attr("content");

                if (result.error == true && result.error_message) {
                    iziToast.show({
                        title: "Error",
                        message: result.error_message,
                        color: "red",
                    });
                } else if (result.error == false) {
                    iziToast.show({
                        title: "Success",
                        message: result.message,
                        color: "green",
                    });
                    setTimeout(function () {
                        location.reload();
                    }, 2000); // Reload after 2 seconds
                } else {
                    iziToast.show({
                        title: "Error",
                        message: result.message,
                        color: "red",
                    });
                }
            },
            complete: function () {
                // Re-enable button and reset text after the request is complete
                submitButton.prop("disabled", false).text("Upload");
            },
        });
    } else {
        iziToast.error({
            message: "Please select type",
        });
    }
});

$(function () {
    const $toggle = $(".custom_prompt_toggle");
    const $custom_prompt = $(".custom_prompt");
    const $promptActions = $("#prompt_actions");

    $toggle.on("change", function () {
        if ($toggle.is(":checked")) {
            $custom_prompt.removeClass("d-none");
            $promptActions.removeClass("d-none");
        } else {
            $custom_prompt.addClass("d-none").val("");
            $promptActions.addClass("d-none");
        }
    });
});
$(function () {
    const $toggle = $(".custom_description_prompt_toggle");
    const $custom_prompt = $(".custom_description_prompt");

    $toggle.on("change", function () {
        if ($toggle.is(":checked")) {
            $custom_prompt.removeClass("d-none");
        } else {
            $custom_prompt.addClass("d-none").val("");
        }
    });
});
$(function () {
    const $toggle = $(".custom_extra_description_prompt_toggle");
    const $custom_prompt = $(".custom_extra_description_prompt");

    $toggle.on("change", function () {
        if ($toggle.is(":checked")) {
            $custom_prompt.removeClass("d-none");
        } else {
            $custom_prompt.addClass("d-none").val("");
        }
    });
});

$(document).on("change", ".custom_translated_prompt_toggle", function () {
    const lang = $(this).data("lang");
    const isChecked = $(this).is(":checked");

    const $promptBox = $("#language_custom_prompt_" + lang);
    const $promptAction = $("#language_prompt_action_" + lang);

    if (isChecked) {
        $promptBox.removeClass("d-none");
        $promptAction.removeClass("d-none");
    } else {
        $promptBox.addClass("d-none").val("");
        $promptAction.addClass("d-none");
    }
});

$(".generate_short_description").on("click", function () {
    const productName = $("#pro_input_text").val().trim();
    const custom_prompt_enabled = $(".custom_prompt_toggle").is(":checked");
    const custom_prompt = $(".custom_prompt").val().trim();

    if (!productName) {
        iziToast.error({
            message: "Please enter a product name first",
        });
        return;
    }
    if (custom_prompt_enabled && !custom_prompt) {
        iziToast.error({
            message: "Please enter a custom prompt description.",
        });
        return;
    }
    const $btn = $(this);
    $btn.text("Generating...").prop("disabled", true);

    $.ajax({
        url: appUrl + from + "/generate_short_description",
        type: "POST",
        contentType: "application/json",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: JSON.stringify({
            product_name: productName,
            prompt: custom_prompt_enabled ? custom_prompt : null,
            old_description: $("#short_description").val().trim(),
        }),
        success: function (data) {
            $("#short_description").val(
                data.description || "AI did not return a description."
            );
        },
        error: function (xhr, status, error) {
            console.error("AI generation error:", error);
            iziToast.error({
                message: xhr.responseJSON.message || "Something went wrong.",
            });
        },
        complete: function () {
            $btn.text("Generate with AI").prop("disabled", false);
        },
    });
});

$(document).on("click", ".generate_translated_short_description", function () {
    const langCode = $(this).data("lang");
    const langName = $(this).data("lang-name");
    const productName = $(`#translated_name_${langCode}`).val().trim();
    const custom_prompt_enabled = $(".custom_translated_prompt_toggle").is(
        ":checked"
    );
    const custom_prompt = $(".custom_translated_prompt").val().trim();

    if (custom_prompt_enabled && !custom_prompt) {
        iziToast.error({
            message: "Please enter a custom prompt description.",
        });
        return;
    }
    const $btn = $(this);
    $btn.text("Generating...").prop("disabled", true);

    $.ajax({
        url: appUrl + from + "/generate_short_description",
        type: "POST",
        contentType: "application/json",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: JSON.stringify({
            product_name: productName,
            language_code: langCode,
            prompt: custom_prompt_enabled ? custom_prompt : null,
            old_description: $(`#translated_short_description_${langCode}`)
                .val()
                .trim(),
        }),
        success: function (data) {
            $(`#translated_short_description_${langCode}`).val(
                data.description || "AI did not return a description."
            );
        },
        error: function (xhr, status, error) {
            console.error("AI generation error:", xhr.responseJSON.message);
            iziToast.error({
                message: xhr.responseJSON.message,
            });
        },
        complete: function () {
            $btn.text("Generate with AI").prop("disabled", false);
        },
    });
});
$(".generate_description").on("click", function () {
    const productName = $("#pro_input_text").val().trim();
    const custom_prompt_enabled = $(".custom_description_prompt_toggle").is(
        ":checked"
    );
    const custom_prompt = $(".custom_description_prompt").val().trim();
    if (custom_prompt_enabled && !custom_prompt) {
        iziToast.error({
            message: "Please enter a custom prompt description.",
        });
        return;
    }
    const editorInstance = tinymce.get($(".addr_editor").attr("id"));
    const editorContent = editorInstance?.getContent({ format: "text" }).trim();

    const $descInput = $(".pro_input_description");
    const inputContent = $descInput.length > 0 ? $descInput.val().trim() : "";

    const existing_description = editorContent || inputContent;

    const $btn = $(this);
    $btn.text("Generating...").prop("disabled", true);

    $.ajax({
        url: appUrl + from + "/generate_description",
        type: "POST",
        contentType: "application/json",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: JSON.stringify({
            product_name: productName,
            prompt: custom_prompt_enabled ? custom_prompt : null,
            existing_description: custom_prompt_enabled
                ? existing_description
                : null, // only send if enhancing
        }),
        success: function (data) {
            const description =
                data.description || "AI did not return a description.";

            const editor = tinymce.get($(".addr_editor").attr("id"));

            if (editor) {
                editor.setContent(description);
            } else {
                $(".pro_input_description").val(description);
            }
        },

        error: function (xhr, status, error) {
            console.error("AI generation error:", error);
            iziToast.error({
                message: xhr.responseJSON?.message || "Something went wrong.",
            });
        },
        complete: function () {
            $btn.text("Generate with AI").prop("disabled", false);
        },
    });
});

$(".generate_extra_description").on("click", function () {
    const productName = $("#pro_input_text").val().trim();

    const $container = $(this).closest(".col-md-6");

    const custom_prompt_enabled = $container
        .find(".custom_extra_description_prompt_toggle")
        .is(":checked");

    const custom_prompt = $container
        .find(".custom_extra_description_prompt")
        .val()
        .trim();

    if (custom_prompt_enabled && !custom_prompt) {
        iziToast.error({
            message: "Please enter a custom prompt description.",
        });
        return;
    }

    const editorId = $container.find(".addr_editor").attr("id");
    const editorInstance = tinymce.get(editorId);
    const editorContent = editorInstance?.getContent({ format: "text" }).trim();

    const $descInput = $container.find(".extra_input_description");
    const inputContent = $descInput.length > 0 ? $descInput.val().trim() : "";

    const existing_description = editorContent || inputContent;

    const $btn = $(this);
    $btn.text("Generating...").prop("disabled", true);

    $.ajax({
        url: appUrl + from + "/generate_description",
        type: "POST",
        contentType: "application/json",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: JSON.stringify({
            product_name: productName,
            prompt: custom_prompt_enabled ? custom_prompt : null,
            existing_description: custom_prompt_enabled
                ? existing_description
                : null,
        }),
        success: function (data) {
            const description =
                data.description || "AI did not return a description.";

            const editor = tinymce.get(editorId);

            if (editor) {
                editor.setContent(description);
            } else {
                $container.find(".extra_input_description").val(description);
            }
        },
        error: function (xhr, status, error) {
            console.error("AI generation error:", error);
            iziToast.error({
                message: xhr.responseJSON?.message || "Something went wrong.",
            });
        },
        complete: function () {
            $btn.text("Generate with AI").prop("disabled", false);
        },
    });
});

$(document).on("click", ".get_prompt_suggestions", function () {
    const productName = $("#pro_input_text").val().trim();

    if (productName.length < 3) {
        iziToast.error({
            message: "Please enter Product Name.",
        });
        return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true).html(
        '<span class="spinner-border spinner-border-sm"></span> Getting...'
    );

    $.ajax({
        url: appUrl + from + "/get_suggested_prompts",
        method: "GET",
        data: { product_name: productName },
        success: function (data) {
            const suggestionsBox = $("#prompt_suggestions");
            suggestionsBox.empty();

            if (data.prompts && data.prompts.length > 0) {
                data.prompts.forEach((prompt, index) => {
                    const isFirstSuggestion = index == 0; // Check if this is the first suggestion

                    suggestionsBox.append(
                        `<button type="button" class="list-group-item list-group-item-action prompt-item ${isFirstSuggestion ? "d-none" : ""
                        }">${prompt}</button>`
                    );
                });

                suggestionsBox.removeClass("d-none");
            } else {
                iziToast.error({ message: "No prompt suggestions found." });
                suggestionsBox.addClass("d-none");
            }
        },
        complete: function () {
            $btn.prop("disabled", false).text("Get Prompt Suggestions");
        },
    });
});

// Hide suggestions when clicking outside the suggestions box or the button
$(document).on("click", function (e) {
    const suggestionsBox = $("#prompt_suggestions");
    const btn = $(".get_prompt_suggestions");

    // Check if the clicked target is outside the suggestions box or button
    if (
        !suggestionsBox.is(e.target) &&
        !btn.is(e.target) &&
        suggestionsBox.has(e.target).length === 0
    ) {
        suggestionsBox.addClass("d-none");
    }
});
$(document).on("click", ".prompt-item", function () {
    const selectedPrompt = $(this).text().trim();
    $(".custom_prompt").val(selectedPrompt);
    $("#prompt_suggestions").addClass("d-none");
});
// language wise prompt suggession

$(document).on("click", ".get_language_prompt_suggestions", function () {
    const $btn = $(this);
    const $container = $btn.closest(".col-md-6");
    const productName = $(".translated-name-input").val().trim();
    $btn.prop("disabled", true).html(
        '<span class="spinner-border spinner-border-sm"></span> Getting...'
    );

    $.ajax({
        url: appUrl + from + "/get_suggested_prompts",
        method: "GET",
        data: { product_name: productName },
        success: function (data) {
            const suggestionsBox = $container.find(
                ".language_prompt_suggestions"
            );
            suggestionsBox.empty();

            if (data.prompts && data.prompts.length > 0) {
                data.prompts.forEach((prompt, index) => {
                    const isFirstSuggestion = index == 0;
                    suggestionsBox.append(
                        `<button type="button" class="list-group-item list-group-item-action language-prompt-item ${isFirstSuggestion ? "d-none" : ""
                        }">${prompt}</button>`
                    );
                });

                suggestionsBox.removeClass("d-none");
            } else {
                iziToast.info({ message: "No prompt suggestions found." });
                suggestionsBox.addClass("d-none");
            }
        },
        complete: function () {
            $btn.prop("disabled", false).text("Get Prompt Suggestions");
        },
    });
});

$(document).on("click", ".language-prompt-item", function () {
    const selectedPrompt = $(this).text().trim();
    const lang = $(this)
        .closest(".col-md-6")
        .find(".custom_translated_prompt_toggle")
        .data("lang");

    $("#language_custom_prompt_" + lang).val(selectedPrompt);
    $(".language_prompt_suggestions").addClass("d-none");
});
$(document).on("click", function (e) {
    const $target = $(e.target);

    // If the click is NOT inside the suggestions box or the "Get Prompt Suggestions" button
    if (
        !$target.closest(".language_prompt_suggestions").length &&
        !$target.closest(".get_language_prompt_suggestions").length
    ) {
        $(".language_prompt_suggestions").addClass("d-none");
    }
});
$(document).ready(function () {
    $('select[name="type"]').on('change', function () {
        var value = $(this).val();
        if (['radio', 'dropdown', 'checkbox'].includes(value)) {
            $('.customOptionInput').removeClass('d-none');
        } else {
            $('.customOptionInput').addClass('d-none');
        }
    });
});

$(document).ready(function () {
    $('.custom_field_repeater').each(function () {
        const $repeater = $(this);
        const $addButton = $repeater.find('.repeater-add-btn');

        $repeater.repeater({
            initEmpty: false, // show one item on load
            show: function () {
                $(this).slideDown();
                toggleAddButton();
            },
            hide: function (deleteElement) {
                const isRequired = $(this).closest('.custom_field_repeater').data('required');

                if (isRequired) {
                    return; // prevent deletion
                }

                $(this).slideUp(function () {
                    $(this).remove();
                    toggleAddButton();
                });
            }
        });

        // Manage the Add button
        function toggleAddButton() {
            const itemCount = $repeater.find('[data-repeater-item]').length;
            // $addButton.prop('disabled', itemCount >= 1);
            if (itemCount >= 1) {
                $addButton.hide();
            } else {
                $addButton.show();
            }
        }

        // Add button only works when no item exists
        $addButton.on('click', function () {
            const itemCount = $repeater.find('[data-repeater-item]').length;
            if (itemCount === 0) {
                $repeater.find('[data-repeater-create]').click();
            }
        });

        // Initial check
        toggleAddButton();
    });
});
$('.update_city').select2({
    placeholder: 'Search for a city',
    ajax: {
        url: appUrl + from + "/area/get_cities",
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term
            };
        },
        processResults: function (data) {
            return {
                results: data
            };
        },
        cache: true
    },
    dropdownParent: $(".update_city_parent"),
});


$(document).ready(function () {
    function toggleFieldVisibility() {
        const type = $('.custom_field_type').val();

        // Hide all conditional fields first
        // Always hide these initially
        $('input[name="field_length"]').closest('.mb-3').hide();
        $('input[name="min"]').closest('.mb-3').hide();
        $('input[name="max"]').closest('.mb-3').hide();
        $('.customOptionInput').addClass('d-none');

        // Show based on selected type
        if (type === 'text' || type === 'textarea') {
            $('input[name="field_length"]').closest('.mb-3').show();
        } else if (type === 'number') {
            $('input[name="min"]').closest('.mb-3').show();
            $('input[name="max"]').closest('.mb-3').show();
        } else if (['dropdown', 'radio', 'checkbox'].includes(type)) {
            $('.customOptionInput').removeClass('d-none');
        }
    }

    // Initial run
    toggleFieldVisibility();

    // Bind to change event
    $('select[name="type"]').on('change', toggleFieldVisibility);
});