"use strict";
$.ajaxSetup({
    headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
    },
});
var is_logged = "";
var appUrl = document.getElementById("app_url").dataset.appUrl;
if (appUrl.charAt(appUrl.length - 1) !== "/") {
    appUrl += "/";
}
$(".loading-state").addClass("d-none");
document.addEventListener("livewire:initialized", () => {
    Livewire.on("validationErrorshow", (message) => {
        let messages = message[0].data;
        $.each(messages, function (key, value) {
            iziToast.error({
                message: value[0],
                position: "topRight",
            });
            return false;
        });
    });
});
// Livewire.on("validationErrorshow", (message) => {
//     let messages = message[0].data;
//     let firstError = Object.values(messages)[0][0]; // Get the first error message

//     iziToast.error({
//         message: firstError,
//         position: "topRight",
//     });
// });
document.addEventListener("livewire:initialized", () => {
    Livewire.on("validationSuccessShow", (message) => {
        let messages = message[0].data;
        $.each(messages, function (key, value) {
            iziToast.success({
                message: value[0],
                position: "topRight",
            });
            return false;
        });
    });
});
document.addEventListener("livewire:initialized", () => {
    Livewire.on("showError", (message) => {
        iziToast.error({
            title: "Error",
            message: message,
            position: "topRight",
        });
        $(".kv-ltr-theme-svg-star").rating();
    });
});
document.addEventListener("livewire:initialized", () => {
    Livewire.on("showSuccess", (message) => {
        iziToast.success({
            title: "Success",
            message: message,
            position: "topRight",
        });
        $(".kv-ltr-theme-svg-star").rating();
    });
});

// Livewire.on("showSuccess", (message) => {
//     iziToast.destroy();
//     iziToast.success({
//         title: "Success",
//         message: message,
//         position: "topRight",
//     });
//     $(".kv-ltr-theme-svg-star").rating();
// });

function product_zoom() {
    $(".zoompro").elevateZoom({
        gallery: "gallery",
        cursor: "pointer",
        galleryActiveClass: "active",
        imageCrossfade: true,
        borderSize: 2,
        loadingIcon: "https://www.elevateweb.co.uk/spinner.gif",
    });
    $(".zoompro_style_2").elevateZoom({
        gallery: "gallery",
        cursor: "pointer",
        zoomType: "inner",
        galleryActiveClass: "active",
        imageCrossfade: true,
        borderSize: 2,
        loadingIcon: "https://www.elevateweb.co.uk/spinner.gif",
    });
}

function FirebaseAuth() {
    $.ajax({
        type: "get",
        url: appUrl + "settings/get-firebase-credentials",
        dataType: "json",
        success: function (response) {
            const firebaseConfig = {
                apiKey: response.apiKey,
                authDomain: response.authDomain,
                projectId: response.projectId,
                databaseURL: response.databaseURL,
                storageBucket: response.storageBucket,
                messagingSenderId: response.messagingSenderId,
                appId: response.appId,
                measurementId: response.measurementId,
            };
            if (firebase.apps.length == 0) {
                firebase.initializeApp(firebaseConfig);
            }
            let coderesult = "";
            $("#send_otp").on("click", function () {
                let code = $("#number").intlTelInput(
                    "getSelectedCountryData"
                ).dialCode;
                let number = $("#number").val();
                let type = $("#type").val();
                if (number == "") {
                    iziToast.error({
                        message: "Please Enter Mobile Number",
                        position: "topRight",
                    });
                    return;
                }
                $("#send_otp").attr("disabled", true).html("Please Wait...");
                const call_api = () => {
                    return new Promise((resolve, reject) => {
                        if (type == "password-recovery") {
                            $.ajax({
                                type: "POST",
                                url: appUrl + "password-recovery/check-number",
                                data: {
                                    mobile: number,
                                },
                                success: function (response) {
                                    if (response.error == true) {
                                        $("#send_otp")
                                            .attr("disabled", false)
                                            .html("Send OTP");
                                        iziToast.error({
                                            message: response.message,
                                            position: "topRight",
                                        });
                                        reject(0);
                                        return;
                                    }
                                    resolve();
                                },
                            });
                        } else {
                            $.ajax({
                                type: "POST",
                                url: appUrl + "register/check-number",
                                data: {
                                    mobile: number,
                                },
                                success: function (response) {
                                    if (
                                        response.allow_modification_error !=
                                        undefined &&
                                        response.allow_modification_error ==
                                        true
                                    ) {
                                        $("#send_otp")
                                            .attr("disabled", false)
                                            .html("Send OTP");
                                        iziToast.error({
                                            message: response.message,
                                            position: "topRight",
                                        });
                                        return;
                                    }
                                    if (response.error == true) {
                                        $("#send_otp")
                                            .attr("disabled", false)
                                            .html("Send OTP");
                                        $.each(
                                            response.message,
                                            function (key, value) {
                                                iziToast.error({
                                                    message: value[0],
                                                    position: "topRight",
                                                });
                                            }
                                        );
                                        reject(0);
                                        return;
                                    }
                                    resolve();
                                },
                            });
                        }
                    });
                };
                call_api()
                    .then(() => {
                        let phoneNumber = "+" + code + number;
                        firebase
                            .auth()
                            .signInWithPhoneNumber(
                                phoneNumber,
                                window.recaptchaVerifier
                            )
                            .then(function (confirmationResult) {
                                window.confirmationResult = confirmationResult;
                                coderesult = confirmationResult;
                                $(".send-otp-box").addClass("d-none");
                                $(".verify-otp-box").removeClass("d-none");
                                iziToast.success({
                                    message: "OTP Sent Successfully",
                                    position: "topRight",
                                });
                            })
                            .catch(function (error) {
                                "#send_otp"
                                    .attr("disabled", false)
                                    .html("Send OTP");
                                iziToast.error({
                                    message: error.message,
                                    position: "topRight",
                                });
                                return;
                            });
                    })
                    .catch((err) => { });
            });
            $("#verify_otp").on("click", function () {
                let code = $("#verificationCode").val();
                if (code == "") {
                    iziToast.error({
                        message: "Please Enter Verification Code",
                        position: "topRight",
                    });
                    return;
                }
                $("#verify_otp").attr("disabled", true).html("Please Wait...");
                coderesult
                    .confirm(code)
                    .then(function (result) {
                        let type = $("#type").val();
                        $(".verify-otp-box").addClass("d-none");
                        if (type == "password-recovery") {
                            $(".reset-password-form").removeClass("d-none");
                        } else {
                            $(".register-form").removeClass("d-none");
                        }
                        iziToast.success({
                            message: "Mobile Number Verified",
                            position: "topRight",
                        });

                        if (type == "password-recovery") {
                            $(".reset-password-form").on(
                                "submit",
                                function (e) {
                                    e.preventDefault();
                                    let number = $("#number").val();
                                    let password = $("#password").val();
                                    let password_confirmation = $(
                                        "#password_confirmation"
                                    ).val();
                                    if (password == "") {
                                        iziToast.error({
                                            message: "Please Enter Password",
                                            position: "topRight",
                                        });
                                        return;
                                    }
                                    if (password != password_confirmation) {
                                        iziToast.error({
                                            message:
                                                "Password and Conform Password Doesn't match",
                                            position: "topRight",
                                        });
                                        return;
                                    }
                                    $("#changePassword")
                                        .attr("disabled", true)
                                        .val("Please Wait...");
                                    $.ajax({
                                        type: "POST",
                                        url:
                                            appUrl +
                                            "password-recovery/set-new-password",
                                        data: {
                                            mobile: number,
                                            new_password: password,
                                            verify_password:
                                                password_confirmation,
                                        },
                                        success: function (response) {
                                            if (response.error == true) {
                                                $("#changePassword")
                                                    .attr("disabled", false)
                                                    .val("Change Password");
                                                $.each(
                                                    response.message,
                                                    function (key, value) {
                                                        iziToast.error({
                                                            message: value[0],
                                                            position:
                                                                "topRight",
                                                        });
                                                        return false;
                                                    }
                                                );
                                                return false;
                                            }
                                            iziToast.success({
                                                message: response.message,
                                                position: "topRight",
                                            });
                                            Livewire.navigate("/login");
                                            return;
                                        },
                                    });
                                }
                            );
                        } else {
                            $(".register-form").on("submit", function (e) {
                                e.preventDefault();
                                let username = $("#username").val();
                                let number = $("#number").val();
                                let email = $("#email").val();
                                let password = $("#password").val();
                                let password_confirmation = $(
                                    "#password_confirmation"
                                ).val();

                                if (username == "") {
                                    iziToast.error({
                                        message: "Please Enter Username",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (email == "") {
                                    iziToast.error({
                                        message: "Please Enter Email",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (password == "") {
                                    iziToast.error({
                                        message: "Please Enter Password",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (password != password_confirmation) {
                                    iziToast.error({
                                        message:
                                            "Password and Conform Password Doesn't match",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                $("#register-form-submit")
                                    .attr("disabled", true)
                                    .val("Please Wait...");

                                $.ajax({
                                    type: "POST",
                                    url: appUrl + "register/submit",
                                    data: {
                                        username,
                                        mobile: number,
                                        email,
                                        password,
                                        password_confirmation,
                                    },
                                    success: function (response) {
                                        if (response.error == true) {
                                            $("#register-form-submit")
                                                .attr("disabled", false)
                                                .val("Register");
                                            $.each(
                                                response.message,
                                                function (key, value) {
                                                    iziToast.error({
                                                        message: value[0],
                                                        position: "topRight",
                                                    });
                                                    return false;
                                                }
                                            );
                                            return false;
                                        }
                                        iziToast.success({
                                            message: response.message,
                                            position: "topRight",
                                        });
                                        Livewire.navigate("/");
                                        return;
                                    },
                                });
                            });
                        }
                    })
                    .catch(function (error) {
                        $("#verify_otp")
                            .attr("disabled", false)
                            .html("Verify Code");
                        iziToast.success({
                            message: error.message,
                            position: "topRight",
                        });
                    });
            });
            window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier(
                "recaptcha-container"
            );
            recaptchaVerifier.render();
        },
    });
}

function CustomSmsAuth() {
    $("#send_otp").on("click", function () {
        let code = $("#number").intlTelInput("getSelectedCountryData").dialCode;
        let number = $("#number").val();
        let type = $("#type").val();
        if (number == "") {
            iziToast.error({
                message: "Please Enter Mobile Number",
                position: "topRight",
            });
            return;
        }
        $("#send_otp").attr("disabled", true).html("Please Wait...");
        const call_api = () => {
            return new Promise((resolve, reject) => {
                if (type == "password-recovery") {
                    $.ajax({
                        type: "POST",
                        url: appUrl + "password-recovery/check-number",
                        data: {
                            mobile: number,
                        },
                        success: function (response) {
                            if (response.error == true) {
                                "#send_otp"
                                    .attr("disabled", false)
                                    .html("Send OTP");
                                iziToast.error({
                                    message: response.message,
                                    position: "topRight",
                                });
                                reject(0);
                                return;
                            }
                            resolve();
                        },
                    });
                } else {
                    $.ajax({
                        type: "POST",
                        url: appUrl + "register/check-number",
                        data: {
                            mobile: number,
                        },
                        success: function (response) {
                            if (response.error == true) {
                                "#send_otp"
                                    .attr("disabled", false)
                                    .html("Send OTP");
                                if (Array.isArray(response.message)) {
                                    $.each(
                                        response.message,
                                        function (key, value) {
                                            iziToast.error({
                                                message: value[0],
                                                position: "topRight",
                                            });
                                        }
                                    );
                                } else {
                                    iziToast.error({
                                        message: response.message,
                                        position: "topRight",
                                    });
                                }
                                reject(0);
                                return;
                            }
                            resolve();
                        },
                    });
                }
            });
        };
        call_api()
            .then(() => {
                $.ajax({
                    type: "POST",
                    url: appUrl + "auth/send_otp",
                    data: {
                        code,
                        mobile: number,
                    },
                    dataType: "json",
                    success: function (response) {
                        if (response.error == false) {
                            $(".send-otp-box").addClass("d-none");
                            $(".verify-otp-box").removeClass("d-none");
                            iziToast.success({
                                message: response.message,
                                position: "topRight",
                            });
                            return;
                        }
                        "#send_otp".attr("disabled", false).html("Send OTP");
                        iziToast.error({
                            message: response.message,
                            position: "topRight",
                        });
                        return;
                    },
                });
            })
            .catch((err) => { });
        $("#verify_otp").on("click", function () {
            let code = $("#verificationCode").val();
            let number = $("#number").val();
            if (code == "") {
                iziToast.error({
                    message: "Please Enter Verification Code",
                    position: "topRight",
                });
                return;
            }
            $("#verify_otp").attr("disabled", true).html("Please Wait...");
            $.ajax({
                type: "post",
                url: appUrl + "auth/verify_otp",
                data: {
                    mobile: number,
                    verification_code: code,
                },
                dataType: "json",
                success: function (response) {
                    if (response.error == false) {
                        let type = $("#type").val();
                        $(".verify-otp-box").addClass("d-none");
                        if (type == "password-recovery") {
                            $(".reset-password-form").removeClass("d-none");
                        } else {
                            $(".register-form").removeClass("d-none");
                        }
                        iziToast.success({
                            message: "Mobile Number Verified",
                            position: "topRight",
                        });
                        if (type == "password-recovery") {
                            $(".reset-password-form").on(
                                "submit",
                                function (e) {
                                    e.preventDefault();
                                    let number = $("#number").val();
                                    let password = $("#password").val();
                                    let password_confirmation = $(
                                        "#password_confirmation"
                                    ).val();
                                    if (password == "") {
                                        iziToast.error({
                                            message: "Please Enter Password",
                                            position: "topRight",
                                        });
                                        return;
                                    }
                                    if (password != password_confirmation) {
                                        iziToast.error({
                                            message:
                                                "Password and Conform Password Doesn't match",
                                            position: "topRight",
                                        });
                                        return;
                                    }
                                    $("#changePassword")
                                        .attr("disabled", true)
                                        .val("Please Wait...");
                                    $.ajax({
                                        type: "POST",
                                        url:
                                            appUrl +
                                            "password-recovery/set-new-password",
                                        data: {
                                            mobile: number,
                                            new_password: password,
                                            verify_password:
                                                password_confirmation,
                                        },
                                        success: function (response) {
                                            if (response.error == true) {
                                                $("#changePassword")
                                                    .attr("disabled", false)
                                                    .val("Change Password");
                                                $.each(
                                                    response.message,
                                                    function (key, value) {
                                                        iziToast.error({
                                                            message: value[0],
                                                            position:
                                                                "topRight",
                                                        });
                                                        return false;
                                                    }
                                                );
                                                return false;
                                            }
                                            iziToast.success({
                                                message: response.message,
                                                position: "topRight",
                                            });
                                            Livewire.navigate("/login");
                                            return;
                                        },
                                    });
                                }
                            );
                            return;
                        } else {
                            $(".register-form").on("submit", function (e) {
                                e.preventDefault();
                                let username = $("#username").val();
                                let number = $("#number").val();
                                let email = $("#email").val();
                                let password = $("#password").val();
                                let password_confirmation = $(
                                    "#password_confirmation"
                                ).val();

                                if (username == "") {
                                    iziToast.error({
                                        message: "Please Enter Username",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (email == "") {
                                    iziToast.error({
                                        message: "Please Enter Email",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (password == "") {
                                    iziToast.error({
                                        message: "Please Enter Password",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                if (password != password_confirmation) {
                                    iziToast.error({
                                        message:
                                            "Password and Conform Password Doesn't match",
                                        position: "topRight",
                                    });
                                    return;
                                }
                                $("#register-form-submit")
                                    .attr("disabled", true)
                                    .val("Please Wait...");
                                $.ajax({
                                    type: "POST",
                                    url: appUrl + "register/submit",
                                    data: {
                                        username,
                                        mobile: number,
                                        email,
                                        password,
                                        password_confirmation,
                                    },
                                    success: function (response) {
                                        if (response.error == true) {
                                            $("#register-form-submit")
                                                .attr("disabled", false)
                                                .val("Register");
                                            $.each(
                                                response.message,
                                                function (key, value) {
                                                    iziToast.error({
                                                        message: value[0],
                                                        position: "topRight",
                                                    });
                                                    return false;
                                                }
                                            );
                                            return false;
                                        }
                                        iziToast.success({
                                            message: response.message,
                                            position: "topRight",
                                        });
                                        Livewire.navigate("/");
                                        return;
                                    },
                                });
                            });
                            return;
                        }
                    }
                    $("#verify_otp")
                        .attr("disabled", false)
                        .html("Verify Code");
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                    return;
                },
            });
        });
    });
}

function bootTab_init() {
    const tabLinks = document.querySelectorAll(".product-tabs .tablink");
    const tabContents = document.querySelectorAll(
        ".tab-container .tab-content"
    );
    tabLinks.forEach(function (tabLink) {
        tabLink.addEventListener("click", function (event) {
            event.preventDefault();
            tabLinks.forEach(function (link) {
                link.parentElement.classList.remove("active");
            });
            this.parentElement.classList.add("active");
            const targetTabId = this.getAttribute("rel");
            const targetTab = document.getElementById(targetTabId);

            tabContents.forEach(function (content) {
                content.style.display = "none";
            });
            if (targetTab !== null && targetTab !== undefined) {
                targetTab.style.display = "block";
            }
        });
    });
}

function display_cart(cart) {
    let display = "";
    let currency_symbol = $("#currency").val();
    let current_store_id = $("#current_store_id").val();

    if (cart !== null && cart.length > 0) {
        display += `<div class="block block-cart"><div class="minicart-content"><ul class="m-0 clearfix">`;

        let total = 0;
        let cart_count = 0;
        cart.forEach((e) => {
            if (e.store_id == current_store_id) {
                total += parseFloat(e.variant_price);
                cart_count++;
                display +=
                    '<li class="item d-flex justify-content-center align-items-center">' +
                    '<a class="product-image rounded-3" wire:navigate href="' +
                    appUrl +
                    "products/" +
                    e.slug +
                    '"><img class="blur-up lazyload" data-src="' +
                    e.image +
                    '"src="' +
                    e.image +
                    '" alt="' +
                    e.name +
                    '" title="' +
                    e.name +
                    '" width="120" height="170" />' +
                    '</a><div class="product-details"><a class="product-title" wire:navigate href="' +
                    appUrl +
                    "products/" +
                    e.slug +
                    '">' +
                    e.name +
                    "</a>" +
                    '<div class="variant-cart my-2">' +
                    (e.product_type === "regular"
                        ? e.brand_name
                        : e.product_type) +
                    "</div>" +
                    '<div class="priceRow"><div class="product-price">' +
                    currency_symbol +
                    '<span class="price price-' +
                    e.product_variant_id +
                    '">' +
                    e.final_price +
                    '</span></div></div></div><div class="qtyDetail text-end cart-qtyDetail">' +
                    '<div class="qtyField"><a class="qtyBtn minus" href="#;"><ion-icon name="remove-outline"></ion-icon></a>' +
                    '<input type="number" name="quantity" data-variant-id="' +
                    e.product_variant_id +
                    '" value="' +
                    e.qty +
                    '" max="' +
                    (e.max == 0 ? "Infinity" : e.max) +
                    '" min="' +
                    e.min +
                    '" step="' +
                    e.step +
                    '" data-variant-price="' +
                    e.variant_price +
                    '" class="qty">' +
                    '<a class="qtyBtn plus" href="#;"><ion-icon name="add-outline"></ion-icon></a></div>' +
                    '<a class="remove_from_cart remove pointer" data-variant-id="' +
                    e.product_variant_id +
                    '"><ion-icon class="icon" data-bs-toggle="tooltip" data-bs-placement="top" name="close-outline"></ion-icon></a></div> </li>';
            }
        });
        $(".cart-count").text(cart_count);
        $(".cart_count").text(cart_count);
        if (cart_count != 0) {
            display += `</ul></div>`;
            display += `<div class="minicart-bottom"><div class="subtotal clearfix my-3"><div class="totalInfo clearfix"><span>Total:</span><span class="item product-price sub-total">${currency_symbol}${total}</span></div></div><div class="agree-check customCheckbox"><input id="prTearm" name="tearm" type="checkbox" value="tearm" required /><label for="prTearm"> I agree with the </label><a wire:navigate href="${appUrl}term-and-conditions" class="ms-1 text-link">Terms &amp; conditions</a></div><div class="minicart-action d-flex mt-3"><button class="cart-checkout proceed-to-checkout btn btn-primary w-50 me-1 disabled">Check Out</button> <button class="cart-checkout cart-btn btn btn-secondary w-50 ms-1 disabled">View Cart</button></div></div></div>`;
        } else {
            display +=
                `<div id="display_cart"><div class="cartEmpty-content mt-4"><ion-icon name="cart-outline" class="icon text-muted fs-1"></ion-icon><p class="my-3">No Products in the Cart</p><a wire:navigate href="` +
                appUrl +
                "products" +
                `" class="btn btn-primary">Continue shopping</a></div></div>`;
        }
    } else {
        display +=
            `<div id="display_cart"><div class="cartEmpty-content mt-4"><ion-icon name="cart-outline" class="icon text-muted fs-1"></ion-icon><p class="my-3">No Products in the Cart</p><a wire:navigate href="` +
            appUrl +
            "products" +
            `" class="btn btn-primary">Continue shopping</a></div></div>`;
    }
    $("#display_cart").html(display);
}

function initListner(event, selector, callback) {
    $(selector).off(event);
    $(selector).on(event, callback);
}
function add_cart() {
    initListner("click", ".add_cart", function (e) {
        console.log("here");
        e.preventDefault();
        let variant_id = $(this).attr("data-product-variant-id");
        let t = $(this);

        if (!variant_id) {
            iziToast.error({
                message: "Please Select Variant",
                position: "topRight",
            });
            return;
        }
        let qty = $(this)
            .closest("#stickycart-form, .product-action")
            .find(".qty")
            .val();
        let product_type = $(this).attr("data-product-type");
        if (product_type == undefined || product_type == null) {
            product_type = "regular";
        }
        let variant_price = $(this).attr("data-variant-price");
        let min = $(this).attr("data-min");
        let name = $(this).attr("data-name");
        let store_id = $(this).attr("data-store-id");
        let slug = $(this).attr("data-slug");
        let brand_name = $(this).attr("data-brand-name");
        let max = $(this).attr("data-max");
        let step = $(this).attr("data-step");
        let image = $(this).attr("data-image");
        if (qty == null && qty == undefined) {
            qty = 1;
        }
        if (is_logged == true) {
            $.ajax({
                type: "POST",
                url: appUrl + "cart/add-to-cart",
                data: {
                    product_variant_id: variant_id,
                    qty: qty,
                    is_saved_for_later: false,
                    product_type: product_type,
                },
                dataType: "json",
                success: function (response) {
                    Livewire.dispatch("refreshComponent");
                    if (response.error != true) {
                        iziToast.success({
                            message: response.message,
                            position: "topRight",
                        });
                        $(".cart-count").text(response.cart_count);
                        if (t.hasClass("buy_now")) {
                            Livewire.navigate(appUrl + "cart");
                        }
                        return false;
                    }
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                },
            });
            return;
        }
        let cart_items = {
            product_variant_id: variant_id,
            qty: qty,
            product_type: product_type,
            variant_price: variant_price,
            final_price: qty * parseFloat(variant_price),
            name: name,
            slug: slug,
            brand_name: brand_name,
            min: min,
            max: max,
            step: step,
            image: image,
            store_id: store_id,
        };

        let cart = localStorage.getItem("cart");
        cart = localStorage.getItem("cart") != null ? JSON.parse(cart) : null;

        if (cart != null && cart != undefined) {
            let existingItemIndex = cart.findIndex(
                (item) =>
                    item.product_variant_id === cart_items.product_variant_id
            );
            if (existingItemIndex !== -1) {
                iziToast.error({
                    message: "Item Already Added in Cart",
                    position: "topRight",
                });
                return false;
            } else {
                cart.push(cart_items);
            }
        } else {
            cart = [cart_items];
        }

        localStorage.setItem("cart", JSON.stringify(cart));
        display_cart(cart);
        iziToast.success({
            message: "Added to Cart Successfully",
            position: "topRight",
        });
        return;
    });
}

function initialize() {
    product_zoom();
    bootTab_init();
    bootstrap_table_initialize();
    add_cart();
}
function bootstrap_table_initialize() {
    $("#user_wallet_transactions").bootstrapTable({
        formatLoadingMessage: function () {
            return '<i class="fa fa-spinner fa-spin fa-fw"></i>';
        },
    });
    $("#wallet_withdrawal_request").bootstrapTable({
        formatLoadingMessage: function () {
            return '<i class="fa fa-spinner fa-spin fa-fw"></i>';
        },
    });
    $("#user_transactions").bootstrapTable({
        formatLoadingMessage: function () {
            return '<i class="fa fa-spinner fa-spin fa-fw"></i>';
        },
    });
    $("#natifications_table").bootstrapTable({
        formatLoadingMessage: function () {
            return '<i class="fa fa-spinner fa-spin fa-fw"></i>';
        },
    });
}
function qnt_incre() {
    $(document).on("click", ".qtyBtn", function () {
        let qtyField = $(this).closest(".qtyField"),
            inputField = qtyField.find(".qty"),
            oldValue = parseInt(inputField.val()),
            maxVal = parseInt(inputField.attr("max")),
            minVal = parseInt(inputField.attr("min")),
            stepSize = parseInt(inputField.attr("step")),
            newVal;
        if ($(this).is(".plus")) {
            newVal = oldValue + stepSize;
            newVal = newVal > maxVal ? maxVal : newVal;
            if (newVal == maxVal) {
                iziToast.error({
                    message:
                        "The Maximum allowable quantity is " +
                        (maxVal == 0 ? 1 : maxVal),
                    position: "topRight",
                });
            }
        } else if ($(this).is(".minus")) {
            newVal = oldValue - stepSize;
            newVal = newVal < minVal ? minVal : newVal;
            if (minVal > 1) {
                if (newVal == minVal) {
                    iziToast.error({
                        message: "The minimum allowable quantity is " + minVal,
                        position: "topRight",
                    });
                }
            }
        }

        inputField.val(newVal).change();
        if (inputField.hasClass("dlt-qty")) {
            $(".dlt-qty").val(newVal);
        }
    });
    //Qty Counter
    $(document).on("change", "input.qty", function (e) {
        e.preventDefault();
        let input = $(this);
        let variant_id = $(this).data("variant-id");
        let variant_price = $(this).data("variant-price");
        let product_type = $(this).data("product-type");
        if (product_type == undefined || product_type == null) {
            product_type = "regular";
        }
        let maxVal = parseInt(input.attr("max"));
        let minVal = parseInt(input.attr("min"));
        let newVal = parseInt(input.val());
        let final_price = input.val() * parseFloat(variant_price);
        let total = 0;
        let currency_symbol = $("#currency").val();
        if (newVal > maxVal) {
            input.val(maxVal);
        } else if (newVal < minVal) {
            input.val(minVal);
        }
        if (is_logged == false) {
            let cart = localStorage.getItem("cart");
            cart =
                localStorage.getItem("cart") != null ? JSON.parse(cart) : null;

            if (cart != null && cart != undefined) {
                let existingItemIndex = cart.findIndex(
                    (item) => item.product_variant_id == variant_id
                );
                if (existingItemIndex !== -1) {
                    cart[existingItemIndex].qty = input.val();
                    cart[existingItemIndex].final_price = final_price;
                    $(".price-" + variant_id).html(final_price);
                }
                localStorage.setItem("cart", JSON.stringify(cart));
                cart.forEach((e) => {
                    total += parseFloat(e.final_price);
                    $(".sub-total").html(currency_symbol + total);
                });
            }
            return;
        }
        let qty = input.val();
        if (variant_id) {
            $.ajax({
                type: "POST",
                url: appUrl + "cart/manage-cart",
                data: {
                    variant_id: variant_id,
                    qty: qty,
                    is_saved_for_later: 0,
                    address_id: 0,
                    product_type,
                },
                dataType: "json",
                success: function (response) {
                    Livewire.dispatch("refreshComponent");
                },
            });
        }
    });
}
qnt_incre();
document.addEventListener("livewire:navigating", () => {
    $(".kv-ltr-theme-svg-star").rating("destroy");
});
document.addEventListener("livewire:navigated", () => {
    $(".loading-state").addClass("d-none");
    let store_slug = $("#store_slug").val();
    let current_store_id = $("#current_store_id").val();
    if (store_slug == null || store_slug == "" || store_slug == undefined) {
        store_slug = $("#default_store_slug").val();
    }
    let custom_url = $("#custom_url").val();
    let current_url = $("#current_url").val();

    let primaryColor = $("#store-primary-color").val();
    let secondaryColor = $("#store-secondary-color").val();
    let linkActiveColor = $("#store-link-active-color").val();
    let linkHoverColor = $("#store-link-hover-color").val();
    const root = document.documentElement;
    // Set the CSS variables
    root.style.setProperty("--primary-color", primaryColor);
    root.style.setProperty("--secondary-color", secondaryColor);
    root.style.setProperty("--link-active-color", linkActiveColor);
    root.style.setProperty("--link-hover-color", linkHoverColor);

    $(".slider-link").on("click", function (e) {
        let link = $(this).data("link");
        let url = setUrlParameter(link, "store", store_slug);
        Livewire.navigate(url);
    });

    var currency_symbol = $("#currency").val();

    $(".changeLang").on("click", function () {
        let lang = $(this).data("lang-code");
        Livewire.dispatch("changeLang", { lang });
    });
    $(".changeCurrency").on("click", function () {
        let currency = $(this).data("currency-code");
        Livewire.dispatch("changeCurrency", { currency });
    });
    if ($("#user_id").val() >= 1) {
        is_logged = true;
    } else {
        is_logged = false;
    }

    $(document).on("change", "#prTearm", function () {
        if (!$(this).prop("checked")) {
            $(".cart-checkout").addClass("disabled");
        } else {
            $(".cart-checkout").removeClass("disabled");
        }
    });

    function proceedToCheckoutHandler() {
        if (is_logged == false) {
            iziToast.destroy();
            iziToast.error({
                message: "Please Login First",
                position: "topRight",
            });
            setTimeout(() => {
                let url = setUrlParameter(
                    appUrl + "/login",
                    "store",
                    store_slug
                );
                Livewire.navigate(url);
            }, 1500);
            return false;
        }
        let url = setUrlParameter(
            appUrl + "cart/checkout",
            "store",
            store_slug
        );
        Livewire.navigate(url);
        return false;
    }

    $(document)
        .off("click", ".proceed-to-checkout")
        .on("click", ".proceed-to-checkout", proceedToCheckoutHandler);

    function cartBtnHandler() {
        if (is_logged == false) {
            iziToast.destroy();
            iziToast.error({
                message: "Please Login First",
                position: "topRight",
            });
            setTimeout(() => {
                let url = setUrlParameter(
                    appUrl + "/login",
                    "store",
                    store_slug
                );
                Livewire.navigate(url);
            }, 1500);
            return false;
        }
        let url = setUrlParameter(appUrl + "cart/", "store", store_slug);
        Livewire.navigate(url);
        return false;
    }

    $(document)
        .off("click", ".cart-btn")
        .on("click", ".cart-btn", cartBtnHandler);
    Shareon.init();

    // function product_image_swap() {
    //     $(".swatchLbl").on("click", function () {
    //         alert('here1324');
    //         $(this).parent().siblings(".active").removeClass("active");
    //         $(this).parent().addClass("active");
    //     });
    // }
    // product_image_swap();

    function product_image_swap() {
        $(".swatchLbl").on("click", function () {
            // Handle the active class toggle
            $(this).parent().siblings(".active").removeClass("active");
            $(this).parent().addClass("active");

            // Get new image from the selected variant
            var newImage = $(this).attr("data-image");
            // console.log(newImage);
            var newZoomImage = $(this).attr("data-zoom-image");

            // Update the main product image
            if (newImage && newZoomImage) {
                $("#zoompro").attr("src", newImage);
                $("#zoompro").attr("data-zoom-image", newZoomImage);
            }
        });
    }

    product_image_swap();

    if (is_logged == false) {
        //display local cart
        let cart = localStorage.getItem("cart");
        cart = localStorage.getItem("cart") != null ? JSON.parse(cart) : [];
        display_cart(cart);

        // remove from local storage cart
        $(document).on("click", ".remove_from_cart", function () {
            let product_variant_id = $(this).data("variant-id");
            let cart = localStorage.getItem("cart");
            cart = localStorage.getItem("cart") != null ? JSON.parse(cart) : [];
            let item_to_delete = cart.findIndex(
                (item) => item.product_variant_id == product_variant_id
            );
            if (item_to_delete !== -1) {
                cart.splice(item_to_delete, 1);
            }
            localStorage.setItem("cart", JSON.stringify(cart));
            let cart_count = cart.length;
            $(".cart-count").text(cart_count);
            $(".cart_count").text(cart_count);
            display_cart(cart);
        });
    }
    //remove from cart
    window.addEventListener("remove_from_cart", (e) => {
        let variant_id = e.detail.data.variant_id;
        let store_id = e.detail.data.store_id;
        let user_id = e.detail.data.user_id;
        let product_type = e.detail.data.product_type;
        let cart_count = e.detail.data.cart_count;
        $.ajax({
            type: "POST",
            url: appUrl + "cart/remove-from-cart",
            data: {
                user_id: user_id,
                product_variant_id: variant_id,
                product_type: product_type,
                store_id: store_id,
            },
            success: function (response) {
                if (response.error == false) {
                    iziToast.success({
                        message: response.message,
                        position: "topRight",
                    });
                    if (response.data.length >= 1) {
                        cart_count = response.data.cart_items.length;
                    } else {
                        cart_count = "0";
                    }
                    $(".cart-count").text(cart_count);
                    let data = {
                        variant_id: variant_id,
                        product_type: product_type,
                    };
                    Livewire.dispatch("refreshComponent");
                    return;
                }
                iziToast.error({
                    message: response.message,
                    position: "topRight",
                });
            },
        });
    });
    // add to favorite

    // $(".add-favorite").on("click", function () {
    //     let product_id = $(this).data("product-id");
    //     let product_type = $(this).data("product-type");
    //     if (product_type == undefined || product_type == null) {
    //         product_type = "regular";
    //     }
    //     let user_id = $("#user_id").val();
    //     let t = $(this);
    //     if (user_id == null || user_id == "") {
    //         iziToast.error({
    //             message: "Please Login First",
    //             position: "topRight",
    //         });
    //         return;
    //     }
    //     $.ajax({
    //         url: appUrl + "product/add-to-favorite",
    //         method: "POST",
    //         data: {
    //             product_id,
    //             user_id,
    //             product_type,
    //         },
    //         dataType: "json",
    //         success: function (response) {
    //             if (response.error == false) {
    //                 if (t.hasClass("card_fav_btn")) {
    //                     t.addClass("remove-favorite")
    //                         .removeClass("add-favorite")
    //                         .children("ion-icon")
    //                         .attr("name", "heart")
    //                         .addClass("text-danger");
    //                 } else {
    //                     $(".add-favorite")
    //                         .removeClass("d-flex")
    //                         .addClass("d-none");
    //                     $(".remove-favorite")
    //                         .removeClass("d-none")
    //                         .addClass("d-flex");
    //                 }
    //                 $(".wishlist-count").text(response["wishlist_count"]);
    //                 iziToast.success({
    //                     message:
    //                         "An item has been successfully added to your wishlist.",
    //                     position: "topRight",
    //                 });
    //             }
    //         },
    //     });
    // });

    $(document).on("click", ".add-favorite", function () {
        let product_id = $(this).data("product-id");
        let product_type = $(this).data("product-type") || "regular";
        let user_id = $("#user_id").val();
        let t = $(this);

        if (!user_id) {
            iziToast.destroy();
            iziToast.error({
                message: "Please Login First",
                position: "topRight",
            });
            return;
        }

        $.ajax({
            url: appUrl + "product/add-to-favorite",
            method: "POST",
            data: { product_id, user_id, product_type },
            dataType: "json",
            success: function (response) {
                if (!response.error) {
                    t.removeClass("add-favorite").addClass("remove-favorite");
                    t.children("i")
                        .removeClass("anm-heart-l")
                        .addClass("anm-heart text-danger");

                    // Update Tooltip
                    t.attr("title", "Remove From Wishlist")
                        .tooltip("dispose")
                        .tooltip();

                    $(".wishlist-count").text(response["wishlist_count"]);

                    iziToast.success({
                        message: "Item added to wishlist successfully.",
                        position: "topRight",
                    });
                }
            },
        });
    });

    // remove from favorite

    $(document).on("click", ".remove-favorite", function (e) {
        e.preventDefault();

        let productId = $(this).attr("data-product-id");
        let productType = $(this).attr("data-product-type") || "regular";
        let user_id = $("#user_id").val();
        let t = $(this);

        Swal.fire({
            title: "Do you Really want to Remove This from Wishlist?",
            showCancelButton: true,
            confirmButtonText: "Yes Remove",
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: appUrl + "product/remove-from-favorite",
                    method: "POST",
                    data: { productId, user_id, productType },
                    dataType: "json",
                    success: function (response) {
                        if (!response.error) {
                            // Remove favorite class and update icon
                            t.removeClass("remove-favorite").addClass(
                                "add-favorite"
                            );
                            t.children("i")
                                .removeClass("anm-heart text-danger")
                                .addClass("anm-heart-l");

                            // **Destroy old tooltip & update title**

                            $(".wishlist-count").text(
                                response["wishlist_count"]
                            );
                            Livewire.dispatch("refreshComponent");

                            t.attr("title", "Add to Wishlist")
                                .tooltip("dispose")
                                .tooltip();

                            iziToast.success({
                                message:
                                    "The item has been removed from your wishlist.",
                                position: "topRight",
                            });
                        }
                    },
                });
            }
        });
    });

    window.addEventListener("quickview", (event) => {
        setTimeout(() => {
            if ($(".kv-ltr-theme-svg-star").length > 0) {
                $(".kv-ltr-theme-svg-star").rating({
                    hoverOnClear: false,
                    theme: "krajee-svg",
                });
            }
            attributes();
            add_cart();
            Shareon.init();
        }, 1000);
    });

    $(document).on("click", ".remove-favorite", function (e) {
        e.preventDefault();

        let productId = $(this).attr("data-product-id");
        let productType = $(this).attr("data-product-type");
        if (productType == undefined || productType == null) {
            productType = "regular";
        }
        let user_id = $("#user_id").val();
        let t = $(this);

        Swal.fire({
            title: "Do you Really want to Remove This from Wishlist?",
            showCancelButton: true,
            confirmButtonText: "Yes Remove",
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: appUrl + "product/remove-from-favorite",
                    method: "POST",
                    data: {
                        productId,
                        user_id,
                        productType,
                    },
                    dataType: "json",
                    success: function (response) {
                        if (response.error == false) {
                            if (t.hasClass("card_fav_btn")) {
                                t.addClass("add-favorite")
                                    .removeClass("remove-favorite")
                                    .children("i") // Target <i> instead of ion-icon
                                    .removeClass("anm-heart") // Remove filled heart
                                    .addClass("anm-heart-l") // Add outline heart
                                    .removeClass("text-danger");
                            } else {
                                $(".rem-fav-btn")
                                    .removeClass("d-flex")
                                    .addClass("d-none");
                                $(".add-favorite")
                                    .removeClass("d-none")
                                    .addClass("d-flex");
                            }

                            $(".wishlist-count").text(
                                response["wishlist_count"]
                            );
                            Livewire.dispatch("refreshComponent");

                            iziToast.success({
                                message:
                                    "The item has been removed from your wishlist.",
                                position: "topRight",
                            });
                        }
                    },
                });
            }
        });
    });

    // add to compare
    $(".add-compare").on("click", function (e) {
        let product_id = $(this).attr("data-product-id");
        let product_type = $(this).attr("data-product-type");
        if (product_type == undefined || product_type == null) {
            product_type = "regular";
        }
        e.preventDefault();
        let compare_item = {
            product_id: product_id.trim(),
            product_type: product_type.trim(),
        };
        let compare = localStorage.getItem("compare");

        compare = compare !== null ? JSON.parse(compare) : null;
        if (compare !== null && compare !== undefined) {
            if (compare.find((item) => item.product_id === product_id)) {
                iziToast.destroy();
                iziToast.error({
                    message:
                        "This Product is already present in your Compare List",
                    position: "topRight",
                });
                return;
            }
            compare.push(compare_item);
        } else {
            compare = [compare_item];
        }
        localStorage.setItem("compare", JSON.stringify(compare));
        iziToast.success({
            message: "Product Added To Compare",
            position: "topRight",
        });
    });

    $(".star-rating").on("rating:change", function (event, value, caption) {
        Livewire.dispatch("updateRating", { update_rating: value });
    });

    function attributes() {
        $(".attributes").on("change", function (e) {
            e.preventDefault();
            let prices = [];
            let variant_ids = [];
            let variant_prices = [];
            let variant = [];
            let variants = [];
            let attributes_length = [];
            let selected_attributes = [];
            let is_variant_available = false;
            let price = "";
            $(".variants").each(function () {
                prices = {
                    price: $(this).data("price"),
                    spacial_price: $(this).data("special_price"),
                };
                variant_ids.push($(this).data("id"));
                variant_prices.push(prices);
                variant = $(this).val().split(",");
                variants.push(variant);
            });
            attributes_length = variant.length;
            $(this).parent().siblings().children().prop("checked", false);
            $(".attributes").each(function (i, e) {
                if ($(this).prop("checked")) {
                    selected_attributes.push($(this).val());
                    var selected_variant_id = "";
                    if (selected_attributes.length == attributes_length) {
                        prices = [];
                        var selected_variant_id = "";
                        $.each(variants, function (i, e) {
                            if (arrays_equal(selected_attributes, e)) {
                                is_variant_available = true;
                                prices.push(variant_prices[i]);
                                selected_variant_id = variant_ids[i];
                            }
                        });
                        if (is_variant_available) {
                            $("#add_cart").attr(
                                "data-product-variant-id",
                                selected_variant_id
                            );
                            $(".modal_add_cart").attr(
                                "data-product-variant-id",
                                selected_variant_id
                            );
                            $(".dlt-add-cart").attr(
                                "data-product-variant-id",
                                selected_variant_id
                            );
                            if (
                                prices[0].spacial_price < prices[0].price &&
                                prices[0].spacial_price != 0
                            ) {
                                price = parseFloat(prices[0].spacial_price);
                                $(".add_cart").attr(
                                    "data-variant-price",
                                    prices[0].spacial_price
                                );
                                $(".product_price").html(
                                    currency_symbol + price.toFixed(2)
                                );
                            } else {
                                price = parseFloat(prices[0].price);
                                $(".add_cart").attr(
                                    "data-variant-price",
                                    prices[0].price
                                );
                                $(".product_price").html(
                                    currency_symbol + price.toFixed(2)
                                );
                            }
                            $(".add_cart").removeAttr("disabled");
                        } else {
                            price = "No variant Available!";
                            $(".product_price").html(price);
                            $(".add_cart").attr("disabled", "true");
                        }
                    }
                }
            });
        });
    }
    attributes();

    initialize();
    if ($(".kv-ltr-theme-svg-star").length > 0) {
        $(".kv-ltr-theme-svg-star").rating({
            hoverOnClear: false,
            theme: "krajee-svg",
        });
    }

    function goBack() {
        parent.history.back();
    }
    $(document).on("click", ".remove_compare_item", function (e) {
        e.preventDefault();
        let product_id = $(this).attr("data-product-id");

        var compare_count = $("#compare_count").text();
        compare_count--;

        $("#compare_count").html(compare_count);
        $(".compare" + product_id).remove();
        if (compare_count == 0) {
            $(".compare_item").remove();
            let comp = "";
            comp +=
                '<div id="page-content"><div class="container"><div class="row">' +
                '<div class="col-12 col-sm-12 col-md-12 col-lg-12 text-center">' +
                '<p><img src="' +
                appUrl +
                "frontend/elegant/images/empty-img.gif" +
                '" alt="image"  width="500" /></p>' +
                '<h2 class="fs-4 mt-4"><strong>SORRY,</strong> This Compare is currently empty</h2>' +
                '<p class="same-width-btn"><a wire:navigate href="#" onClick="goBack()" class="btn btn-secondary btn-lg mb-2 mx-3">GO Back</a></p>' +
                "</div></div></div> </div>`";
            $("#compare_item").html(comp);
        }

        let compare = localStorage.getItem("compare");
        compare = compare !== null ? JSON.parse(compare) : null;
        if (compare) {
            let new_compare = compare.filter(function (item) {
                return item.product_id != product_id;
            });
            localStorage.setItem("compare", JSON.stringify(new_compare));
            display_compare();
        }
    });
    let compareUrl = custom_url.split("?");
    if (compareUrl[0] === appUrl + "compare") {
        if (localStorage.getItem("compare")) {
            var compare = localStorage.getItem("compare").length;
            compare = compare !== null ? JSON.parse(compare) : null;
            if (compare) {
                display_compare();
            }
        }
    }

    function cart_sync() {
        let cart = localStorage.getItem("cart");
        cart = localStorage.getItem("cart") != null ? JSON.parse(cart) : "";
        if (cart != "" && cart != undefined) {
            let product_variant_id = [];
            let qty = [];
            let product_type = [];
            let store_id = [];
            cart.forEach((e) => {
                product_variant_id.push(e.product_variant_id);
                qty.push(e.qty);
                product_type.push(e.product_type);
                store_id.push(e.store_id);
            });
            $.ajax({
                type: "POST",
                url: appUrl + "cart/cart-sync",
                data: {
                    product_variant_id: product_variant_id,
                    qty: qty,
                    product_type: product_type,
                    store_id: store_id,
                    is_saved_for_later: false,
                },
                dataType: "json",
                success: function (response) {
                    if (response.error == false) {
                        Livewire.dispatch("refreshComponent");
                        $(".cart-count").text(response.cart_count);
                        localStorage.removeItem("cart");
                        iziToast.success({
                            message: response.message,
                            position: "topRight",
                        });
                        return;
                    }
                    localStorage.removeItem("cart");
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                    return;
                },
            });
            return;
        }
        return;
    }
    if (is_logged == true) {
        cart_sync();
    }

    initListner("click", ".select-store", function () {
        let store_id = $(this).data("store-id");
        let store_name = $(this).data("store-name");
        let store_image = $(this).data("store-image");
        let store_slug = $(this).data("store-slug");

        $.ajax({
            type: "POST",
            url: "/set_store",
            data: {
                store_id,
                store_name,
                store_image,
                store_slug,
            },
            success: function (data) {
                if (data) {
                } else {
                    iziToast.error({
                        message: "Error In Setting Store",
                        position: "topRight",
                    });
                }
                let url = setUrlParameter(current_url, "store", store_slug);
                Livewire.navigate(url);
            },
        });
    });

    $(document).on("click", ".store-show", function () {
        $(".stores-main").addClass("sticky-stores-active");
        $(".store-show")
            .children("ion-icon")
            .attr("name", "chevron-forward-outline");
        $(this).removeClass("store-show");
        $(this).addClass("store-hide");
    });

    $(document).on("click", ".store-hide", function () {
        $(".stores-main").removeClass("sticky-stores-active");
        $(".store-hide")
            .children("ion-icon")
            .attr("name", "chevron-back-outline");
        $(this).removeClass("store-hide");
        $(this).addClass("store-show");
    });

    function setUrlParameter(url, paramName, paramValue) {
        paramName = paramName.replace(/\s+/g, "-");
        if (paramValue == null || paramValue == "") {
            return url
                .replace(
                    new RegExp("[?&]" + paramName + "=[^&#]*(#.*)?$"),
                    "$1"
                )
                .replace(new RegExp("([?&])" + paramName + "=[^&]*&"), "$1");
        }
        var pattern = new RegExp("\\b(" + paramName + "=).*?(&|#|$)");
        if (url.search(pattern) >= 0) {
            return url.replace(pattern, "$1" + paramValue + "$2");
        }
        url = url.replace(/[?#]$/, "");
        return (
            url +
            (url.indexOf("?") > 0 ? "&" : "?") +
            paramName +
            "=" +
            paramValue
        );
    }

    $(document).on("change", "#SortBy", function (e) {
        e.preventDefault();
        var sort = $(this).val();
        let link = setUrlParameter(location.href, "sort", sort);
        Livewire.navigate(link);
    });
    $(document).on("click", ".list_view", function (e) {
        e.preventDefault();
        var list_view = $(this).data("value");
        let link = setUrlParameter(location.href, "mode", list_view);
        Livewire.navigate(link);
    });

    $(document).on("change", "#perPage", function (e) {
        e.preventDefault();
        var perPage = $(this).val();
        let link = setUrlParameter(location.href, "perPage", perPage);
        Livewire.navigate(link);
    });

    $(document).on("click", ".bySearch", function (e) {
        e.preventDefault();
        let search = $(".search_text").val();
        let search_text = search.split(" ").join("_");
        let link = setUrlParameter(appUrl + "products/", "search", search_text);
        if (search != "") {
            Livewire.navigate(link);
        }
    });
    $(document).on("click", ".quick-view-modal", function (e) {
        e.preventDefault();
        let product_id = $(this).data("product-id");
        let product_type = $(this).data("product-type");
        if (product_type == undefined || product_type == null) {
            product_type = "regular";
        }
        if (product_id != null && product_id != undefined) {
            Livewire.dispatch("quick_view", {
                id: product_id,
                product_type: product_type,
            });
        }
    });

    $("#search-drawer").on("shown.bs.offcanvas", function () {
        $(".searchInput").trigger("focus");
    });

    $("#quickview_modal").on("hidden.bs.modal", function () {
        Livewire.dispatch("clear_quickview_modal");
    });

    function getUrlParameter(sParam, custom_url = "") {
        sParam = sParam.replace(/\s+/g, "-");
        if (custom_url != "") {
            if (custom_url.indexOf("?") > -1) {
                var sPageURL = custom_url.substring(
                    custom_url.indexOf("?") + 1
                );
            } else {
                return undefined;
            }
        } else {
            var sPageURL = window.location.search.substring(1);
        }

        var sURLVariables = sPageURL.split("&"),
            sParameterName,
            i;

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split("=");

            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined
                    ? true
                    : decodeURIComponent(sParameterName[1]);
            }
        }
    }

    function buildUrlParameterValue(
        paramName,
        paramValue,
        action,
        custom_url = ""
    ) {
        if (custom_url != "") {
            var param = getUrlParameter(paramName, custom_url);
        } else {
            var param = getUrlParameter(paramName);
        }
        if (action == "add") {
            if (param == undefined) {
                param = paramValue;
            } else {
                param += "|" + paramValue;
            }
            return param;
        } else if (action == "remove") {
            if (param != undefined) {
                param = param.split("|");
                param.splice($.inArray(paramValue, param), 1);
                return param.join("|");
            } else {
                return "";
            }
        }
    }

    $(document).on("change", ".product-filter", function (e) {
        e.preventDefault();
        var attribute_name = $(this).data("attribute");
        attribute_name = "filter-" + attribute_name;
        var get_param = getUrlParameter(attribute_name);
        var current_param_value = $(this).val();
        if (get_param == undefined) {
            get_param = "";
        }
        if (this.checked) {
            var param = buildUrlParameterValue(
                attribute_name,
                current_param_value,
                "add",
                custom_url
            );
        } else {
            var param = buildUrlParameterValue(
                attribute_name,
                current_param_value,
                "remove",
                custom_url
            );
        }
        custom_url = setUrlParameter(custom_url, attribute_name, param);
    });

    $(".product-filter-btn").on("click", function (e) {
        e.preventDefault();
        Livewire.navigate(custom_url);
    });

    $(document).on("click", ".logout", function (e) {
        e.preventDefault();
        $.ajax({
            type: "get",
            url: appUrl + "login/logout",
            data: "",
            dataType: "json",
            success: function (response) {
                iziToast.destroy();
                iziToast.success({
                    message: response.message,
                    position: "topRight",
                });
                location.reload();
                return;
            },
        });
    });
    $(document).on("change", ".brand", function (e) {
        e.preventDefault();
        let t = $(this).val();
        let u = $(this)
            .parent()
            .siblings()
            .children(".brand")
            .prop("checked", false)
            .val();
        var param = buildUrlParameterValue("brand", u, "remove", custom_url);
        custom_url = setUrlParameter(custom_url, "brand", param);
        if (this.checked) {
            var param = buildUrlParameterValue("brand", t, "add", custom_url);
        } else {
            var param = buildUrlParameterValue(
                "brand",
                t,
                "remove",
                custom_url
            );
        }
        custom_url = setUrlParameter(custom_url, "brand", param);
    });

    // price slider
    function price_slider() {
        let min_price = $("#min-price").val();
        let max_price = $("#max-price").val();
        let selected_min_price = $("#selected_min_price").val();
        let selected_max_price = $("#selected_max_price").val();
        $("#slider-range").slider({
            range: true,
            min: parseInt(min_price),
            max: parseInt(max_price),
            values: [selected_min_price, selected_max_price],
            slide: function (event, ui) {
                $("#amount").val(
                    currency_symbol +
                    ui.values[0] +
                    " - " +
                    currency_symbol +
                    ui.values[1]
                );
                $("#min-price").val(ui.values[0]);
                $("#max-price").val(ui.values[1]);
            },
        });
        $("#amount").val(
            currency_symbol +
            $("#slider-range").slider("values", 0) +
            " - " +
            currency_symbol +
            $("#slider-range").slider("values", 1)
        );
    }
    price_slider();

    initListner("click", ".price-filter-btn", function (e) {
        e.preventDefault();
        let min_price = $("#min-price").val();
        let max_price = $("#max-price").val();

        custom_url = setUrlParameter(custom_url, "min_price", min_price);
        custom_url = setUrlParameter(custom_url, "max_price", max_price);
        Livewire.navigate(custom_url);
    });

    $(".show_tabs").on("click", function () {
        $(".widget-title").trigger("click");
        $(this).addClass("d-none");
        $(".close_tabs").removeClass("d-none");
    });
    $(".close_tabs").on("click", function () {
        $(".widget-title").trigger("click");
        $(this).addClass("d-none");
        $(".show_tabs").removeClass("d-none");
    });
    window.addEventListener("toast_fire", (e) => {
        if (e.detail.data.type == "success") {
            iziToast.success({
                message: e.detail.data.message,
                position: "topRight",
            });
            return;
        }
        iziToast.error({
            message: e.detail.data.message,
            position: "topRight",
        });
    });

    $(".add_address").on("click", function () {
        let name = $("#name").val();
        $(".add_address").html("Add Address");
        let type = $("#type").val();
        let mobile = $("#mobile").val();
        let alternate_mobile = $("#alternate_mobile").val();
        let address = $("#form_address").val();
        let landmark = $("#landmark").val();
        let city_list = $("#city_list").val();
        let postcode = $("#postcode").val();
        let state = $("#state").val();
        let country = $("#country_list").val();
        let latitude = $("#latitude").val();
        let longitude = $("#longitude").val();
        let address_id = $("#edit_address_id").val();
        $.ajax({
            type: "POST",
            url: appUrl + "addresses/add_address",
            data: {
                name: name,
                type: type,
                mobile: mobile,
                alternate_mobile: alternate_mobile,
                address: address,
                landmark: landmark,
                city: city_list,
                pincode: postcode,
                state: state,
                country: country,
                latitude: latitude,
                longitude: longitude,
                address_id: address_id,
            },
            success: function (response) {
                if (response.error == true) {
                    $.each(response.message, function (key, value) {
                        iziToast.error({
                            message: value[0],
                            position: "topRight",
                        });
                        return false;
                    });
                    return false;
                }
                iziToast.success({
                    message: response.message,
                    position: "topRight",
                });
                Livewire.dispatch("refreshComponent");
                $("#addNewModal").modal("hide");
            },
        });
    });
    $("#addNewModal").on("hidden.bs.modal", function () {
        $("#name").val("");
        $("#type").val("");
        $("#mobile").val("");
        $("#alternate_mobile").val("");
        $("#form_address").val("");
        $("#landmark").val("");
        $("#city_list").val("");
        $("#postcode").val("");
        $("#state").val("");
        $("#country_list").val("");
        $("#latitude").val("");
        $("#longitude").val("");
        $("#edit_address_id").val("");
    });
    $(".edit-address-btn").on("click", function () {
        var addressId = $(this).data("address-id");
        $("#edit_address_id").val(addressId);
        $.ajax({
            url: appUrl + "my-account/addresses/edit_address",
            method: "GET",
            data: { address_id: addressId },
            success: function (response) {
                var country = new Option(response.country, false, false, false);
                $("#country_list").append(country).trigger("change");

                var city = new Option(response.city, false, false, false);
                $("#city_list").append(city).trigger("change");

                $("#name").val(response.name);
                $("#type").val(response.type);
                $("#mobile").val(response.mobile);
                $("#alternate_mobile").val(response.alternate_mobile);
                $("#form_address").val(response.address);
                $("#landmark").val(response.landmark);
                $("#postcode").val(response.pincode);
                $("#state").val(response.state);
                $("#latitude").val(response.latitude);
                $("#longitude").val(response.longitude);
                $(".add_address").html("Update Address");
            },
            error: function (xhr, status, error) {
                console.error(error);
            },
        });
    });

    $(document).on("click", ".delete_address", function (e) {
        e.preventDefault();
        let address_id = $(this).attr("data-address-id");
        Swal.fire({
            title: "Do you Really want to Remove This Address?",
            showCancelButton: true,
            confirmButtonText: "Delete",
        }).then((result) => {
            if (result.isConfirmed) {
                Livewire.dispatch("deleteAddress", { address_id });
            }
            iziToast.success({
                message: "Address Deleted Successfully",
                position: "topRight",
            });
        });
    });

    $(".city_list").select2({
        ajax: {
            url: appUrl + "my-account/get_Cities",
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
        dropdownParent: $(".city_list_div"),
        minimumInputLength: 1,
        placeholder: "Search for City",
    });
    // Call the function for each select element
    initializeSelect2(
        "#country_list",
        "my-account/get_Countries",
        "Search for countries",
        $(".country_list_div")
    );
    initializeSelect2(
        "#edit-country_list",
        "my-account/get_Countries",
        "Search for countries",
        $(".edit-country_list_div")
    );
    initializeSelect2(
        "#city_list",
        "my-account/get_Cities",
        "Search for City",
        $(".city_list_div")
    );
    initializeSelect2(
        "#edit-city_list",
        "my-account/get_Cities",
        "Search for City",
        $(".edit-city_list_div")
    );

    // user wallet transaction table
    let $table = $("#user_wallet_transactions");
    let $refreshButton = $("#tableRefresh");

    $refreshButton.on("click", function () {
        $table.bootstrapTable("refresh");
    });

    let $searchInput = $("#searchInput");

    $searchInput.on("input", function () {
        let searchText = $(this).val();
        $table.bootstrapTable("searchText", searchText);
    });

    $(function () {
        $("#toolbar")
            .find("select")
            .change(function () {
                $table.bootstrapTable("destroy").bootstrapTable({
                    exportDataType: $(this).val(),
                    exportTypes: [
                        "json",
                        "xml",
                        "csv",
                        "txt",
                        "sql",
                        "excel",
                        "pdf",
                    ],
                    columns: [
                        {
                            field: "state",
                            checkbox: true,
                            visible: $(this).val() === "selected",
                        },
                        {
                            field: "id",
                            title: "ID",
                        },
                        {
                            field: "name",
                            title: "Item Name",
                        },
                        {
                            field: "price",
                            title: "Item Price",
                        },
                    ],
                });
            })
            .trigger("change");
    });

    // edit profile
    initListner("submit", "#edit-profile-form", (e) => {
        e.preventDefault();

        let formData = new FormData();
        if ($("#city_list").val() == null) {
            iziToast.error({
                message: "Please Select City",
                position: "topRight",
            });
            return;
        }
        if ($("#country_list").val() == null) {
            iziToast.error({
                message: "Please Select Country",
                position: "topRight",
            });
            return;
        }
        let image = "";
        if ($("#profile_upload").val() != "") {
            image = $("#profile_upload")[0].files[0];
        }
        formData.append("username", $("#edit-username").val());
        formData.append("city", $("#city_list").val());
        formData.append("country", $("#country_list").val());
        formData.append("address", $("#edit-streetaddress").val());
        formData.append("zipcode", $("#edit-zipcode").val());
        formData.append("profile_upload", image);
        $.ajax({
            type: "POST",
            url: appUrl + "my-account/profile_update",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.error == false) {
                    Livewire.dispatch("refreshComponent");
                    $("#editProfileModal").modal("hide");
                    iziToast.success({
                        message: response.message,
                        position: "topRight",
                    });
                    return;
                }
                $.each(response.message, function (key, value) {
                    iziToast.error({
                        message: value[0],
                        position: "topRight",
                    });
                    return false;
                });
                return false;
            },
        });
    });

    $(".check-product-deliverability").on("click", function () {
        let product_deliverability_type = $(
            "#product_deliverability_type"
        ).val();
        let product_id = $("#product_id").val();
        let city = $("#city_list").val();
        let pincode = $("#pincode").val();
        let product_type = $("#product_type").val();
        if (product_deliverability_type == "city_wise_deliverability") {
            if (city == undefined || city == null || city == "") {
                iziToast.error({
                    message:
                        "Please Choose City to Verify the Product Deliverability",
                    position: "topRight",
                });
                return;
            }
        } else {
            if (pincode == undefined || pincode == null || pincode == "") {
                iziToast.error({
                    message:
                        "Please Enter Pincode to Verify the Product Deliverability",
                    position: "topRight",
                });
                return;
            }
        }
        $.ajax({
            type: "POST",
            url: appUrl + "check-product-deliverability",
            data: {
                product_id,
                city,
                pincode,
                product_type,
            },
            dataType: "json",
            success: function (response) {
                if (response.error == true) {
                    $.each(response.message, function (key, value) {
                        iziToast.error({
                            message: value[0],
                            position: "topRight",
                        });
                        return false;
                    });
                }
                if (response[0].is_deliverable == false) {
                    $(".deliverability-res")
                        .removeClass("text-success")
                        .addClass("text-danger")
                        .html("Sorry, but the product cannot be delivered.");
                    return false;
                }
                $(".deliverability-res")
                    .removeClass("text-danger")
                    .addClass("text-success")
                    .html(
                        "The product is deliverable, and the delivery charges will be added during checkout."
                    );
                return false;
            },
        });
    });

    $(".AddNewTicket").on("click", function () {
        let ticket_id = $(this).data("ticket-id");
        let user_id = $("#user_id").val();
        $("#ticket_id").val(ticket_id);

        $.ajax({
            type: "post",
            url: appUrl + "my-account/support/get-ticket",
            data: {
                ticket_id,
                user_id,
            },
            success: function (response) {
                if (response.error == false) {
                    $("#ticket_type").val(response.data.ticket_type_id);
                    $("#ticket_email").val(response.data.email);
                    $("#ticket_description").val(response.data.description);
                    $("#ticket_subject").val(response.data.subject);
                    $(".add_ticket_btn").html("Update Ticket");
                    $(".add_new_ticket").html("Update Ticket");
                }
            },
        });
    });
    $("#AddNewTicket").on("hidden.bs.modal", function () {
        $("#ticket_id").val("");
        $("#ticket_type").val("");
        $("#ticket_email").val("");
        $("#ticket_description").val("");
        $("#ticket_subject").val("");
        $(".add_ticket_btn").html("Add");
        $(".add_new_ticket").html("Add Ticket");
    });

    $(".add_ticket_btn").on("click", function () {
        let ticket_type = $("#ticket_type").val();
        let ticket_email = $("#ticket_email").val();
        let ticket_description = $("#ticket_description").val();
        let ticket_subject = $("#ticket_subject").val();
        let ticket_id = $("#ticket_id").val();

        $.ajax({
            type: "post",
            url: appUrl + "my-account/support/add-ticket",
            data: {
                ticket_type,
                ticket_email,
                ticket_description,
                ticket_subject,
                ticket_id,
            },
            success: function (response) {
                if (response.error == false) {
                    Livewire.dispatch("refreshComponent");
                    $("#AddNewTicket").modal("hide");
                    iziToast.success({
                        message: response.message,
                        position: "topRight",
                    });
                    return;
                }
                $.each(response.message, function (key, value) {
                    iziToast.error({
                        message: value[0],
                        position: "topRight",
                    });
                    return false;
                });
                return false;
            },
        });
    });

    // change password
    $(document).on("click", ".change_password", () => {
        let current_password = $("#current_password").val();
        let new_password = $("#new_password").val();
        let verify_password = $("#verify_password").val();

        $.ajax({
            type: "POST",
            url: appUrl + "my-account/change-password",
            data: {
                current_password: current_password,
                new_password: new_password,
                verify_password: verify_password,
            },
            success: function (response) {
                if (response.error == false) {
                    Livewire.dispatch("refreshComponent");
                    $("#editLoginModal").modal("hide");
                    iziToast.success({
                        message: response.message,
                        position: "topRight",
                    });
                    return;
                }
                if (typeof response.message === "string") {
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                } else {
                    $.each(response.message, function (key, value) {
                        iziToast.error({
                            message: value[0],
                            position: "topRight",
                        });
                        return false;
                    });
                }
                return false;
            },
        });
    });

    $(".update_order_item_status").on("click", function () {
        let order_status = $(this).data("status");
        let order_item_id = $(this).data("item-id");
        let confirm_title = "";
        let confirm_btn = "";
        if (order_status == "cancelled") {
            confirm_title =
                "Are you sure you want to cancel this ordered item?";
            confirm_btn = "Yes Remove";
        } else if (order_status == "returned") {
            confirm_title = "Are you sure you want to return the ordered item?";
            confirm_btn = "Yes Return";
        }
        Swal.fire({
            title: confirm_title,
            showCancelButton: true,
            confirmButtonText: confirm_btn,
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    type: "POST",
                    url: appUrl + "orders/update-order-item-status",
                    data: {
                        order_status,
                        order_item_id,
                    },
                    dataType: "json",
                    success: function (response) {
                        if (response.error == false) {
                            iziToast.success({
                                message: response.message,
                                position: "topRight",
                            });
                            Livewire.dispatch("refreshComponent");
                            return false;
                        } else {
                            iziToast.error({
                                message: response.message,
                                position: "topRight",
                            });
                        }
                    },
                });
            }
        });
    });

    $(".chat-btn-popup").on("click", function () {
        $("#chat-iframe").toggleClass("chat-iframe-show");
    });

    $(function () {
        $("#number").intlTelInput({
            allowExtensions: !0,
            formatOnDisplay: !0,
            autoFormat: !0,
            autoHideDialCode: !0,
            autoPlaceholder: !0,
            defaultCountry: "in",
            ipinfoToken: "yolo",
            nationalMode: !1,
            numberType: "MOBILE",
            preferredCountries: ["in", "ae", "qa", "om", "bh", "kw", "ma"],
            preventInvalidNumbers: !0,
            separateDialCode: !0,
            initialCountry: "auto",
            geoIpLookup: function (e) {
                $.get("https://ipinfo.io", function () { }, "jsonp").always(
                    function (t) {
                        var a = t && t.country ? t.country : "";
                        e(a);
                    }
                );
            },
            utilsScript:
                "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/11.0.9/js/utils.js",
        });
    });
    let authentication_method = $("#authentication_method").val();
    if (authentication_method == "firebase") {
        FirebaseAuth();
    } else if (authentication_method == "sms") {
        CustomSmsAuth();
    }

    let store = setUrlParameter(custom_url, "store", store_slug);
    let split_url = custom_url.split("?");
    const urlParams = new URLSearchParams("?".concat(split_url[1]));
    const store_exsist = urlParams.get("store");
    if (store_exsist == null) {
        Livewire.navigate(store);
    }
});

$(function () {
    var $pswp = $(".pswp")[0],
        image = [],
        getItems = function () {
            var items = [];
            $(".lightboximages a").each(function () {
                var $href = $(this).attr("href"),
                    $size = $(this).data("size").split("x"),
                    item = {
                        src: $href,
                        w: $size[0],
                        h: $size[1],
                    };
                items.push(item);
            });
            return items;
        };
    var items = getItems();

    $.each(items, function (index, value) {
        image[index] = new Image();
        image[index].src = value["src"];
    });
    $(".prlightbox").on("click", function (event) {
        event.preventDefault();

        var $index = $(".active-thumb").parent().attr("data-slick-index");
        $index++;
        $index = $index - 1;

        var options = {
            index: $index,
            bgOpacity: 0.7,
            showHideOpacity: true,
        };
        var lightBox = new PhotoSwipe(
            $pswp,
            PhotoSwipeUI_Default,
            items,
            options
        );
        lightBox.init();
    });
});
$(".messages").animate({ scrollTop: $(document).height() }, "fast");

$("#profile-img").on("click", function () {
    $("#status-options").toggleClass("active");
});

$(".expand-button").on("click", function () {
    $("#profile").toggleClass("expanded");
    $("#contacts").toggleClass("expanded");
});

$("#status-options ul li").on("click", function () {
    $("#profile-img").removeClass();
    $("#status-online").removeClass("active");
    $("#status-away").removeClass("active");
    $("#status-busy").removeClass("active");
    $("#status-offline").removeClass("active");
    $(this).addClass("active");

    if ($("#status-online").hasClass("active")) {
        $("#profile-img").addClass("online");
    } else if ($("#status-away").hasClass("active")) {
        $("#profile-img").addClass("away");
    } else if ($("#status-busy").hasClass("active")) {
        $("#profile-img").addClass("busy");
    } else if ($("#status-offline").hasClass("active")) {
        $("#profile-img").addClass("offline");
    } else {
        $("#profile-img").removeClass();
    }

    $("#status-options").removeClass("active");
});

function newMessage() {
    let message = $(".message-input input").val();
    if ($.trim(message) == "") {
        return false;
    }
    $(
        '<li class="sent"><img src="http://emilcarlsson.se/assets/mikeross.png" alt="" /><p>' +
        message +
        "</p></li>"
    ).appendTo($(".messages ul"));
    $(".message-input input").val(null);
    $(".contact.active .preview").html("<span>You: </span>" + message);
    $(".messages").animate({ scrollTop: $(document).height() }, "fast");
}

$(".submit").on("click", function () {
    newMessage();
});

$(window).on("keydown", function (e) {
    if (e.which == 13) {
        newMessage();
        return false;
    }
});

function initializeSelect2(selector, url, placeholder, dropdownParent) {
    $(selector).select2({
        ajax: {
            url: appUrl + url,
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
        dropdownParent: dropdownParent,
        minimumInputLength: 1,
        placeholder: placeholder,
    });
}

$(".delete_rating").on("click", function () {
    $(".review_rating").rating("clear");
    $("#review_image").val("");
    $("#message").val("");
});

$(document).on("click", ".eye-icon", function () {
    let inputField = $(this).siblings("input");
    if (inputField.prop("type") == "text") {
        $(this).attr("name", "eye-off-outline");
        inputField.attr("type", "password");
        return;
    }
    $(this).attr("name", "eye-outline");
    inputField.attr("type", "text");
    return;
});

function display_compare() {
    // let compare = localStorage.getItem("compare");
    // let currency_symbol = $("#currency").val();

    let compare = localStorage.getItem("compare");
    let currency_symbol = $("#currency").val();
    compare = compare ? JSON.parse(compare) : [];
    console.log(compare);
    // If compare list is empty, show empty UI and return early
    if (compare.length === 0) {
        let comp = "";
        comp +=
            '<div id="page-content"><div class="container"><div class="row">' +
            '<div class="col-12 text-center">' +
            '<p><img src="' +
            appUrl +
            'frontend/elegant/images/empty-img.gif" alt="No Products" width="500" /></p>' +
            '<h2 class="fs-4 mt-4"><strong>SORRY,</strong> No products found for comparison.</h2>' +
            '<p class="same-width-btn"><a href="#" onClick="goBack()" class="btn btn-secondary btn-lg mb-2 mx-3">GO BACK</a></p>' +
            "</div></div></div></div>";
        $("#compare_item").html(comp);
        $("#compare_count").html(0); // Reset the compare count
        $("#compare_count_alert").html(""); // Clear alert if needed
        return;
    }

    $.ajax({
        type: "POST",
        url: appUrl + "product/add-to-compare",
        data: {
            product_id: compare,
        },
        success: function (response) {
            let compare_count = compare.length;
            if (compare_count >= 0) {
                $("#compare_count").html(compare_count);
            }
            let comp = "";
            // if (response.error == false && compare_count > 0) {
            if (response.error == false) {
                if (compare !== null && compare_count > 0) {
                    let comp_alert =
                        '<div class="alert alert-success py-2 alert-dismissible fade show cart-alert" role="alert">There are <span class="text-primary fw-600" id="compare_count">' +
                        compare_count +
                        '</span> products in this Compare list <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    $("#compare_count_alert").html(comp_alert);
                    comp +=
                        '<table class="table table-borderless align-middle compare_table">' +
                        "<tbody>" +
                        '<tr><th class="name">Products</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">' +
                            '<div class="product-image position-relative">' +
                            '<button type="button" class="btn remove-icon close-btn remove_compare_item" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove" data-product-id="' +
                            e.id +
                            '"><ion-icon name="close-outline"></ion-icon></button>' +
                            '<img class="image rounded-0 blur-up lazyload" data-src="' +
                            e.image +
                            '" src="' +
                            e.image +
                            '" alt="' +
                            e.name +
                            '" title="' +
                            e.name +
                            '" />' +
                            '<button type="button" class="btn btn-light quick-view-modal" data-bs-toggle="modal" data-bs-target="#quickview_modal" data-product-id="' +
                            e.id +
                            '"' +
                            '><ion-icon name="search-outline" class="fs-5"></ion-icon></button></div>' +
                            '<div class="product-name mt-3"><a href="' +
                            appUrl +
                            "products/" +
                            e.slug +
                            '">' +
                            e.name +
                            "</a></div>" +
                            '<div class="product-price fw-500"><span class="price">' +
                            currency_symbol +
                            (e.type == "simple_product"
                                ? e.variants[0]["special_price"] > 0
                                    ? e.variants[0]["special_price"]
                                    : e.variants[0]["price"]
                                : (e.min_max_price.max_price > 0
                                    ? e.min_max_price.max_price
                                    : e.min_max_price.price) +
                                "-" +
                                (e.min_max_price.special_min_price > 0
                                    ? e.min_max_price.special_min_price
                                    : e.min_max_price.min_price)) +
                            "</span></div>";
                    });

                    response.data.combo_products.forEach((e) => {
                        var finalPrice =
                            e.special_price && e.special_price > 0
                                ? e.special_price
                                : e.price;
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">' +
                            '<div class="product-image position-relative">' +
                            '<button type="button" class="btn remove-icon close-btn remove_compare_item" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove" data-product-id="' +
                            e.id +
                            '">' +
                            '<ion-icon name="close-outline"></ion-icon></button>' +
                            '<img class="image rounded-0 blur-up lazyload" data-src="' +
                            e.image +
                            '" src="' +
                            e.image +
                            '" alt="' +
                            e.name +
                            '" title="' +
                            e.name +
                            '" />' +
                            '<button type="button" class="btn btn-light quick-view-modal" data-bs-toggle="modal" data-bs-target="#quickview_modal" data-product-type="combo-product" data-product-id="' +
                            e.id +
                            '">' +
                            '<ion-icon name="search-outline" class="fs-5"></ion-icon></button></div>' +
                            '<div class="product-name mt-3"><a href="' +
                            appUrl +
                            "products/" +
                            e.slug +
                            '">' +
                            e.name +
                            "</a></div>" +
                            '<div class="product-price fw-500">' +
                            '<span class="price">' +
                            currency_symbol +
                            finalPrice +
                            "</span>" +
                            "</div>" +
                            "</td>";
                    });

                    comp += '<tr><th class="name">Description</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">' +
                            e.description +
                            "</td>";
                    });

                    response.data.combo_products.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">' +
                            e.description +
                            "</td>";
                    });

                    comp += '</tr><tr><th class="name">Ratings</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '"><div class="product-review d-flex-center mt-0">' +
                            '<i class="icon anm anm-star"></i>' +
                            e.rating +
                            ' | <span class="caption ms-1">' +
                            e.no_of_ratings +
                            " Reviews</span>" +
                            "</div></td>";
                    });

                    response.data.combo_products.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '"><div class="product-review d-flex-center mt-0">' +
                            '<i class="icon anm anm-star"></i>' +
                            e.rating +
                            ' | <span class="caption ms-1">' +
                            e.no_of_ratings +
                            " Reviews</span>" +
                            "</div></td>";
                    });

                    comp += '</tr><tr><th class="name">Brand</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            ' <td class="item-row compare' +
                            e.id +
                            '">' +
                            (e.brand_name != "" ? e.brand_name : "-") +
                            "</td>";
                    });
                    response.data.combo_products.forEach((e) => {
                        comp +=
                            ' <td class="item-row compare' + e.id + '">-</td>';
                    });
                    comp += '</tr><tr><th class="name">Category</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">' +
                            e.category_name;
                        ("</td>");
                    });
                    response.data.combo_products.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' + e.id + '">-</td>';
                    });

                    comp += '</tr><tr><th class="name">Product Type</th>';
                    response.data.regular_product.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">Single Product</td>';
                    });
                    response.data.combo_products.forEach((e) => {
                        comp +=
                            '<td class="item-row compare' +
                            e.id +
                            '">Combo Product</td>';
                    });
                    comp += "</tr>";
                    comp += "</tr></tbody></table>";
                    $("#compare_item").html(comp);
                }
            }
        },
    });
}

$(document).on("click", ".clear-rating", () => {
    $(".star-rating").rating("reset");
});
$(document).on("click", ".clear-rating", () => {
    $(".star-rating").rating("reset");
});

function arrays_equal(e, t) {
    if (!Array.isArray(e) || !Array.isArray(t) || e.length !== t.length)
        return !1;
    const a = e.concat().sort(),
        r = t.concat().sort();
    for (let e = 0; e < a.length; e++) if (a[e] !== r[e]) return !1;
    return !0;
}
$("#editLoginModal").on("hidden.bs.modal", function () {
    $(this).find('input[type="password"]').val("");
});

Livewire.on("cart-updated", ({ message }) => {
    // alert(message);
    iziToast.error({
        message: message,
        position: "topRight",
    });
});
var ticket_id = '';
// Support Ticket chat
$(document).on("click", ".view_ticket", function (e, row) {
    e.preventDefault();
    var scrolled = 0;
    $(".ticket_msg").data("max-loaded", false);
    ticket_id = $(this).data("ticket-id");

    var date_created = $(this).data("date_created");
    var subject = $(this).data("subject");
    var status = $(this).data("status");

    var ticket_type = $(this).data("ticket_type");
    $('input[name="ticket_id"]').val(ticket_id);

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

    $('#ticket_type_chat').text(ticket_type);
    $('#subject_chat').text(subject);
    $('#date_created').html(date_created);
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
            url: appUrl + "my-account/support/get-ticket-message",
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
                                messages.user_type == "admin" ? "left justify-content-start align-items-start" : "right justify-content-end align-items-end";
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

Dropzone.autoDiscover = false;
// Dropzone configuration
let myDropzone = new Dropzone("#myAlbumTicket", {
    url: appUrl + "my-account/support/send-message",
    autoProcessQueue: false,
    paramName: "attachments",
    addRemoveLinks: true,
    parallelUploads: 10,
    uploadMultiple: true,
    maxFiles: 3,
    dictMaxFilesExceeded: 'Only 3 files are allowed at once',
    dictRemoveFile: 'x',
});

$('#ticket_send_msg_form').on('submit', function (e) {
    e.preventDefault();

    var formdata = new FormData(this);

    var csrfToken = document.head.querySelector(
        'meta[name="csrf-token"]'
    ).content;
    formdata.append("_token", csrfToken);

    var ticket_id = $('#ticket_id').val();
    var message = $("#message_input").val();
    let drop_file_count = myDropzone.files.length;

    if (message.length > 0 || drop_file_count > 0) {
        // Append ticket_id and other necessary data to Dropzone's formData
        myDropzone.on('sending', function (file, xhr, formData) {
            formData.append('ticket_id', ticket_id);
            formData.append('user_id', $('#user_id').val());
            formData.append('user_type', $('#user_type').val());
            formData.append('message', message); // Include message if present
            formData.append("_token", csrfToken);
        });

        if (drop_file_count > 0) {
            // Process Dropzone queue for file uploads
            myDropzone.processQueue();
        } else {
            // No files, proceed with regular AJAX for text message
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formdata,

                cache: false,
                contentType: false,
                processData: false,
                dataType: "json",
                success: function (result) {
                    var token = $('meta[name="csrf-token"]').attr("content");
                    $("#submit_btn").html("Send").attr("disabled", false);
                    if (result.error == false) {
                        $(".product-image-container").remove();
                        if (result.data.id > 0) {
                            var messages = result.data;
                            var message_html = "";
                            var i = 1;
                            var atch_html = "";
                            var is_left =
                                messages.user_type == "admin" ? "left justify-content-start align-items-start" : "right justify-content-end align-items-end";

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
                            message_html +=
                                "<div class='d-flex " + (messages.attachments.length > 0 ? '' : 'direct-chat-msg') + " flex-column " +
                                is_left +
                                "'>" +
                                "<div class='direct-chat-infos clearfix text-black-50'>" +
                                "<span class='direct-chat-name float-" +
                                is_left +
                                "' id='name'>" +
                                " " +
                                messages.name +
                                " " +
                                "</span>" +
                                "<span class='direct-chat-timestamp float-" +
                                is_left +
                                "' id='last_updated'>" +
                                messages.updated_at +
                                " " +
                                "</span>" +
                                "</div>" +
                                "<div class='direct-chat-text float-" +
                                is_left +
                                "' id='message'>" +
                                messages.message +
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
                error: function () {
                    $('#submit_btn').html('Send').attr('disabled', false);

                    iziToast.error({
                        message:
                            '<span style="text-transform:capitalize">An error occurred while sending the message.</span>',
                    });
                }
            });
        }
    } else {


        iziToast.error({
            message:
                '<span style="text-transform:capitalize">Please enter a message or attach a file.</span>',
        });
    }

    return false;
});

$(function () {
    var scrolled = 0;
    if ($("#element").length) {
        $("#element").scrollTop($("#element")[0].scrollHeight);
        $("#element").scroll(function () {
            if ($("#element").scrollTop() == 0) {

                load_messages($(".ticket_msg"), ticket_id);
            }
        });
        $("#element").bind("mousewheel", function (e) {
            if (e.originalEvent.wheelDelta / 120 > 0) {
                if ($(".ticket_msg")[0].scrollHeight < 370 && scrolled == 0) {

                    load_messages($(".ticket_msg"), ticket_id);
                    scrolled = 1;
                }
            }
        });
    }
});


$(document).ready(function () {

    // Handle duplicate file prevention
    myDropzone.on("addedfile", function (file) {
        let i = 0;
        if (this.files.length) {
            for (let _i = 0, _len = this.files.length; _i < _len - 1; _i++) {
                if (
                    this.files[_i].name === file.name &&
                    this.files[_i].size === file.size &&
                    this.files[_i].lastModifiedDate.toString() === file.lastModifiedDate.toString()
                ) {
                    this.removeFile(file);
                } else if (this.files[4] != null) {
                    this.removeFile(file);
                }
                i++;
            }
        }
    });

    // Handle successful file upload
    myDropzone.on('successmultiple', function (file, response) {
        let data = typeof response === 'string' ? JSON.parse(response) : response;

        if (data.error == false && data.data.id > 0) {
            var messages = data.data;
            var message_html = "";

            var i = 1;
            var atch_html = "";
            var is_left = messages.user_type == "admin" ? "left justify-content-start align-items-start" : "right justify-content-end align-items-end";

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
            message_html +=
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

            $('.ticket_msg').append(message_html);
            $("#message_input").val('');
            $("#element").scrollTop($("#element")[0].scrollHeight);

            iziToast.success({
                message:
                    '<span style="text-transform:capitalize">' +
                    data.message +
                    "</span> ",
            });
        } else {

            iziToast.error({
                message:
                    '<span style="text-transform:capitalize">' + data.message + '</span>',
            });
        }

        closeDropZone();
        myDropzone.removeAllFiles(true);
    });

    // Handle Dropzone errors
    myDropzone.on('error', function (file, errorMessage) {

        iziToast.error({
            message:
                '<span style="text-transform:capitalize">File upload failed: ' + errorMessage + '</span>',
        });
        myDropzone.removeFile(file);
    });

    // Show/hide Dropzone
    $(document).on("dragenter", "#chat-box-content", function (e) {
        showDropZone();
    });

    $(document).on("click", ".btn-attech", function () {

        showDropZone();
    });

    window.closeDropZone = function () {
        $('#chat-box-content').show();
        $('#chat-dropbox').addClass("d-none");
        myDropzone.removeAllFiles(true);
    };

    window.showDropZone = function () {
        $('#chat-dropbox').removeClass("d-none");
        $('#chat-box-content').hide();
    };
});

$(document).ready(function () {
    $('#message_input').on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#ticket_send_msg_form').submit();
        }
    });
});