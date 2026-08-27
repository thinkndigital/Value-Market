
var appUrl = document.getElementById("app_url").dataset.appUrl;
if (appUrl.charAt(appUrl.length - 1) !== '/') {
    appUrl += '/';
}
var user_id = $('#user_id').val()
const d = new Date();

function initListner(event, selector, callback) {
    $(selector).off(event)
    $(selector).on(event, callback)
}

function razorpay_setup(
    key,
    amount,
    app_name,
    logo,
    razorpay_order_id,
    order_id,
    username,
    user_email,
    user_contact
) {

    var options = {

        key: key,
        amount: amount * 100,
        currency: 'INR',
        name: app_name,
        description: 'Purchase Product',
        image: logo,
        order_id: razorpay_order_id,
        handler: function (response) {
            $('#razorpay_payment_id').val(response.razorpay_payment_id)
            $('#razorpay_signature').val(response.razorpay_signature)
            place_order().done(function (result) {
                if (result.error == false) {
                    iziToast.error({
                        message: result.message,
                        position: "topRight",
                    });
                    Livewire.navigate(appUrl + 'payments?response=order_success');
                    return
                } else {
                    iziToast.error({
                        message: result.message,
                        position: "topRight",
                    });
                }
                return
            })
        },
        prefill: {
            name: username,
            email: user_email,
            contact: user_contact
        },
        notes: {
            order_id: order_id
        },
        theme: {
            color: '#3399cc'
        },
        escape: false,
        modal: {
            ondismiss: function () {
                $('#place_order_btn').attr('disabled', false).html('Place Order')
            }
        }
    }
    var rzp = new Razorpay(options)
    return rzp
}

function place_order() {
    let myForm = document.getElementById('place_order_form');
    let formdata = new FormData(myForm);
    formdata.append('promo_code', $('#coupon-code').val());
    let latitude = sessionStorage.getItem("latitude") === null ? '' : sessionStorage.getItem("latitude");
    let longitude = sessionStorage.getItem("longitude") === null ? '' : sessionStorage.getItem("longitude");
    formdata.append('latitude', latitude);
    formdata.append('status', "awaiting");
    formdata.append('longitude', longitude);

    return $.ajax({
        type: "post",
        url: appUrl + "cart/place_order",
        data: formdata,
        dataType: "json",
        cache: false,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.error == false) {
                iziToast.destroy();
                iziToast.success({
                    message: response.message,
                    position: "topRight",
                });
                return
            }
            iziToast.destroy();
            iziToast.error({
                message: response.message,
                position: "topRight",
            });
            return
        }
    });
}

Livewire.on('validate_promo_code', (e) => {
    $('.apply-coupon-btn').addClass('d-none');
    $('.remove-coupon-btn').removeClass('d-none');
    if (e.is_promo_valid.error == false) {
        setTimeout(() => {
            if (e.is_promo_valid.data[0].is_cashback == 1) {
                $('.cashback-text').removeClass('d-none');
                $('.cashback-amount').text(e.is_promo_valid.data[0].final_discount);
            } else {
                $('.coupon-box').removeClass('d-none');
                $('.coupon-field').text(e.is_promo_valid.data[0].final_discount);
            }
        }, 500);
        $('#discount').val(e.is_promo_valid.data[0].final_discount);
        iziToast.success({
            message: "Promo Code Applied Successfully",
            position: "topRight",
        });
    } else {
        iziToast.error({
            message: e.is_promo_valid.message,
            position: "topRight",
        });
    }
})

const handleComplete = async function () {
    // Destroy Checkout instance
    checkout.destroy()

    // Retrieve details from server (which loads Checkout Session)
    const details = await retrievePurchaseDetails();

    // Show custom purchase summary
    showPurchaseSummary(details);
}
function renderStripePopup(clientSecret, publicKey) {
    var stripe = Stripe(publicKey);
    stripe.initEmbeddedCheckout({
        clientSecret,
        onComplete: handleComplete
    }).then(function (checkout) {
        checkout.mount('#stripe-checkout');
        var iframe = document.querySelector('#stripe-checkout iframe');
        iframe.addEventListener('load', function () {
            var iframeWindow = iframe.contentWindow;
            iframeWindow.addEventListener('message', function (event) {
                if (event.data === 'embedded_checkout.closed') {
                    console.log('Checkout closed');
                } else if (event.data === 'embedded_checkout.complete') {
                    console.log('Payment successful');
                    // Perform any additional actions for successful payment
                }
            });
        });
    }).catch(function (error) {
        console.error('Initialization error:', error);
    });
}

function payWithPaystack(email, amount, public_key, reference_id) {
    if (email == "") {
        iziToast.error({
            message: "Email is Required.",
            position: "topRight",
        });
    }
    if (public_key == "") {
        iziToast.error({
            message: "Something Went Wrong!!",
            position: "topRight",
        });
    }
    let handler = PaystackPop.setup({
        key: public_key,
        email,
        amount: amount * 100,
        ref: reference_id,
        onClose: function () {
            console.log('Window closed.');
        },
        callback: function (response) {
            console.log(response)
            if (response.status == "success") {
                $('#paystack_reference').val(response.reference)
                place_order().done(function (result) {
                    if (result.error == false) {
                        Livewire.navigate(appUrl + 'payments?response=order_success');
                        return
                    }
                })
            }
        },
        onClose: function () {
            $('#place_order_btn').attr('disabled', false).html('Place Order')
        },
    });
    handler.openIframe();
}
document.addEventListener('livewire:navigated', () => {

    // select address
    $("#address-modal").on('show.bs.modal', function () {
        let address_id = $('#selected_address_id').val();
        if (address_id != "") {
            $('#select-address' + address_id).prop('checked', true)
            $('#select-address' + address_id).parent().siblings().children().prop('checked', false);
        }
    });
    $(document).on("click", '.address-radio', function () {
        let address_id = $(this).data('address-id');
        if (address_id != "") {
            $('#select-address' + address_id).prop('checked', true)
            $(this).parent().siblings().children().prop('checked', false);
            console.log(address_id);
            $(document).on("click", ".set-address", function () {
                $('#address-modal').modal('hide')
                Livewire.dispatch('get_selected_address', { address_id: address_id });
            })
        }
    })

    // select promo code
    $(document).on("click", '.promo-radio', function () {
        let promo_id = $(this).data('promocode-id');
        let promo_code = $(this).data('promocode');
        $(this).parent().siblings().children().prop('checked', false);
        if (promo_code != "" && promo_code != null) {
            $(document).on("click", ".set-promo", function () {
                $('#promo-modal').modal('hide')
                $('#coupon-code').val(promo_code)
                $('#coupon-code').attr('data-promocode-id', promo_id)
            })
        }
    })
    initListner("click", '.apply-coupon-btn', function (e) {
        e.preventDefault()
        let promo_code = $('#coupon-code').attr('data-promocode-id');
        if (promo_code != "" && promo_code != null) {
            Livewire.dispatch('get_selected_promo', { promo_code: promo_code });
            $('#promo_set').val(1)
            $('.promo-code-id').val(promo_code)
        } else {
            $('#promo-modal').modal('show')
            iziToast.error({
                message: "Please Select Promo code From List",
                position: "topRight",
            });
        }
    })

    initListner("click", '.remove-coupon-btn', function (e) {
        e.preventDefault()
        let promo_code = "";
        Livewire.dispatch('get_selected_promo', { promo_code: promo_code });
        $('.apply-coupon-btn').removeClass('d-none');
        $('.remove-coupon-btn').addClass('d-none');
        $('#coupon-code').val(promo_code)
        $('#coupon-code').attr('data-promocode-id', promo_code)
        $('#promo_set').val(0)
        $('.promo-code-id').val('')
        iziToast.error({
            message: "Promo Code Removed Successfully",
            position: "topRight",
        });
    })

    $('#datepicker').attr({
        placeholder: 'Preferred Delivery Date',
        autocomplete: 'off'
    })
    $('#datepicker').on('cancel.daterangepicker', function (ev, picker) {
        $(this).val('')
        $('#start_date').val('')
    })
    $('#datepicker').on('apply.daterangepicker', function (ev, picker) {
        var drp = $('#datepicker').data('daterangepicker')
        var current_time = moment().format('HH:mm')
        if (moment(drp.startDate).isSame(moment(), 'd')) {
            $('.time-slot-inputs').each(function (i, e) {
                if ($(this).data('last_order_time') < current_time) {
                    $(this).prop('checked', false).attr('required', false)
                    $(this).parent().hide()
                } else {
                    $(this).attr('required', true)
                    $(this).parent().show()
                }
            })
        } else {
            $('.time-slot-inputs').each(function (i, e) {
                $(this).attr('required', true)
                $(this).parent().show()
            })
        }
        $('#start_date').val(drp.startDate.format('YYYY-MM-DD'))
        $('#delivery_date').val(drp.startDate.format('YYYY-MM-DD'))
        $(this).val(picker.startDate.format('DD/MM/YYYY'))
    })
    let mindate = '',
        maxdate = ''
    if ($('#delivery_starts_from').val() != '') {
        mindate = moment().add($('#delivery_starts_from').val() - 1, 'days')
    } else {
        mindate = null
    }

    if ($('#delivery_ends_in').val() != '') {
        maxdate = moment(mindate).add($('#delivery_ends_in').val() - 1, 'days')
    } else {
        maxdate = null
    }

    // wallet
    $(document).on('change', '#wallet-pay', function () {
        if ($('#wallet-pay').prop('checked')) {
            let wallet_balance = $(this).data('wallet-balance')
            let final_total = $('#final_total').val();
            if (wallet_balance != "" && wallet_balance != null) {
                Livewire.dispatch('is_wallet_use', { is_wallet_use: true });
                if (wallet_balance >= final_total) {
                    $('.payment-type').addClass('d-none')
                }
                return
            }
            iziToast.error({
                message: "You Don't Have Enough Balance",
                position: "topRight",
            });
        }
        Livewire.dispatch('is_wallet_use', { is_wallet_use: false });
        $('.payment-type').removeClass('d-none')
    })

    $('#place_order_form').on('submit', (e) => {
        e.preventDefault();
        let address_id = $("#selected_address_id").val();
        let address_mobile_no = $("#address-mobile").val();
        let product_type = $("#product_type").val();
        let promocodeid = $('#coupon-code').attr('data-promocode-id');
        let documents = $('#documents').val();
        let wallet_used = 0
        let wallet_balance = $('#wallet-pay').data('wallet-balance')
        if ($('#wallet-pay').is(":checked")) {
            wallet_used = 1;
        }

        let promo_set = $('#promo_set').val();
        let promo_code = '';
        let promo_code_id = '';
        if (promo_set == 1) {
            promo_code = $('#coupon-code').val();
            promo_code_id = $('#coupon-code').attr('data-promocode-id');
        }
        let final_total = $("#final_total").val();
        let btn_html = $('#place_order_btn').html();

        $('#place_order_btn').attr('disabled', true).html('Please Wait...');
        if (($('#is_time_slots_enabled').val() == 1 && ($('input[name="delivery_time"]').is(':checked') == false || $('input[type=hidden][id="start_date"]').val() == "") && product_type != 'digital_product')) {
            iziToast.error({
                message: "Please select Delivery Date & Time.",
                position: "topRight",
            });
            $('#place_order_btn').attr('disabled', false).html(btn_html);
            return false;
        }
        if ((address_id == null || address_id == undefined || address_id == '') && product_type != 'digital_product') {
            iziToast.destroy();
            iziToast.error({
                message: "Please add/choose address.",
                position: "topRight",
            });
            $('#place_order_btn').attr('disabled', false).html(btn_html);
            return false;
        }
        let payment_methods = $("input[name='payment_method']:checked").val();
        if (payment_methods == undefined && final_total != 0) {
            iziToast.destroy();
            iziToast.error({
                message: "Please Select One Payment Method.",
                position: "topRight",
            });
            $('#place_order_btn').attr('disabled', false).html(btn_html);
            return false;
        }
        final_total = parseFloat(final_total).toFixed(2);
        if (payment_methods == 'phonepe') {
            $.post(appUrl + "payments/phonepe", {
                final_total,
                user_id,
                mobile: address_mobile_no
            },
                function (response) {
                    if (response.error == false) {

                        $('#phonepe_transaction_id').val(response.transaction_id);
                        if (response.payment_url != "") {
                            place_order().done(function (result) {
                                if (result.error == false) {
                                    window.location.replace(response.payment_url);
                                    return
                                }
                                $('#place_order_btn').attr('disabled', false).html(btn_html);
                                return
                            })
                        }
                        return
                    }
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                },
                "json"
            );
        } else if (payment_methods == 'paypal') {
            $.ajax({
                type: "POST",
                url: appUrl + "pre-payment-setup",
                data: {
                    address_id,
                    wallet_used,
                    promo_code,
                    product_type,
                    promo_code_id,
                    payment_method: payment_methods
                },
                dataType: "json",
                success: function (response) {
                    $('#paypal-button-container').removeClass('d-none');
                    let reference_id = d.getTime() + Math.round("100", "999");
                    if (response.error == false) {
                        paypal.Buttons({
                            createOrder: function (data, actions) {
                                return actions.order.create({
                                    purchase_units: [{
                                        amount: {
                                            value: response.final_amount,
                                        },
                                        reference_id: reference_id,
                                    }]
                                });
                            },
                            onApprove: function (data, actions) {
                                return actions.order.capture().then(function (details) {
                                    if (details.status == "COMPLETED") {
                                        $('#paypal_transaction_id').val(details.purchase_units[0].reference_id);
                                        place_order().done(function (result) {
                                            if (result.error == false) {
                                                Livewire.navigate(appUrl + 'payments?response=order_success');
                                                return
                                            }
                                        })
                                    }
                                });
                            },
                            onCancel: function (data) {
                                console.log("Payment Cancel");
                                console.log(data);
                            }
                        }).render('#paypal-button-container');
                        return
                    }
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                    $('#place_order_btn').attr('disabled', false).html(btn_html);
                    return
                }
            });
        } else if (payment_methods == 'paystack') {
            $.ajax({
                type: "POST",
                url: appUrl + "pre-payment-setup",
                data: {
                    address_id,
                    wallet_used,
                    promo_code,
                    product_type,
                    promo_code_id,
                    payment_method: payment_methods
                },
                dataType: "json",
                success: function (response) {
                    let reference_id = d.getTime() + Math.round("100", "999");
                    let public_key = $('#paystack_public_key').val();
                    let email = $("#user-email").val();
                    if (response.error == false) {
                        payWithPaystack(email, response.final_amount, public_key, reference_id)
                        return
                    }
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                    $('#place_order_btn').attr('disabled', false).html(btn_html);
                    return
                }
            });
            return
        } else if (payment_methods == 'stripe') {
            $.ajax({
                type: "POST",
                url: appUrl + "pre-payment-setup",
                data: {
                    address_id,
                    wallet_used,
                    promo_code,
                    product_type,
                    promo_code_id,
                    payment_method: payment_methods
                },
                dataType: "json",
                success: function (response) {
                    if (response.error == false) {
                        let myForm = document.getElementById('place_order_form');
                        let formdata = new FormData(myForm);
                        formdata.append('promo_code', $('#coupon-code').val());
                        let latitude = sessionStorage.getItem("latitude") === null ? '' : sessionStorage.getItem("latitude");
                        let longitude = sessionStorage.getItem("longitude") === null ? '' : sessionStorage.getItem("longitude");
                        formdata.append('latitude', latitude);
                        formdata.append('longitude', longitude);
                        formdata.append('amount', response.final_amount);
                        formdata.append('product_name', response.product_name);
                        $.ajax({
                            type: 'POST',
                            url: `${appUrl}payments/stripe`,
                            data: formdata,
                            dataType: 'json',
                            cache: false,
                            processData: false,
                            contentType: false,
                            success: response => {
                                renderStripePopup(response.client_secret, response.publicKey)
                            }
                        });
                        return
                    }
                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                    $('#place_order_btn').attr('disabled', false).html(btn_html);
                    return
                }
            });
            return
        } else if (payment_methods == 'razorpay') {
            $.ajax({
                type: "POST",
                url: appUrl + "pre-payment-setup",
                data: {
                    address_id,
                    wallet_used,
                    promo_code,
                    product_type,
                    promo_code_id,
                    payment_method: payment_methods
                },
                dataType: "json",
                success: function (result) {
                    if (result.error == false) {
                        let amount = result.data.amount
                        $.ajax({
                            type: 'POST',
                            url: appUrl + "payments/razorpay",
                            data: {
                                amount,
                            },
                            dataType: 'json',
                            success: response => {
                                if (response.status == "created") {
                                    let key = response.public_key
                                    let amount = response.amount
                                    let razorpay_order_id = response.id
                                    let order_id = response.id
                                    let app_name = $('#app_name').val()
                                    let logo = $('#logo').val()
                                    let username = $('#username').val()
                                    let user_email = $('#user-email').val()
                                    let user_contact = $('#mobile').val()
                                    let rzp1 = razorpay_setup(key, amount, app_name, logo, razorpay_order_id, order_id, username, user_email, user_contact);
                                    rzp1.open()
                                    rzp1.on('payment.failed', function (response) {
                                        Livewire.navigate(appUrl + 'payments?response=wallet_failed')
                                    })
                                }
                            }
                        });
                        return
                    }
                    iziToast.error({
                        message: result.message,
                        position: "topRight",
                    });
                    $('#place_order_btn').attr('disabled', false).html(btn_html);
                    return
                }
            });
            return
        } else if (payment_methods == 'cod') {
            place_order().done(function (result) {
                if (result.error == false) {
                    window.location.replace(appUrl + 'payments?response=order_success');
                    return
                }
            })
        } else if (final_total == 0 && wallet_used == 1) {
            console.log(wallet_balance);
            place_order().done(function (result) {
                $('#place_order_btn').attr('disabled', false).html(btn_html);
                if (result.error == false) {
                    window.location.replace(appUrl + 'payments?response=order_success');
                    return
                }
            })
        }
    })
})
