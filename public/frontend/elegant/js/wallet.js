var appUrl = document.getElementById("app_url").dataset.appUrl;
if (appUrl.charAt(appUrl.length - 1) !== '/') {
    appUrl += '/';
}
var user_id = $('#user_id').val()
let custom_url = $('#custom_url').val();

const d = new Date();

function wallet_refill() {
    let myForm = document.getElementById('wallet_refill_form');
    let formdata = new FormData(myForm);

    return $.ajax({
        type: "post",
        url: appUrl + "wallet/refill",
        data: formdata,
        dataType: "json",
        cache: false,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.error == false) {
                iziToast.success({
                    message: response.message,
                    position: "topRight",
                });
                return
            }
            iziToast.error({
                message: response.message,
                position: "topRight",
            });
            return
        }
    });
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
        description: 'Wallet Refill',
        image: logo,
        order_id: razorpay_order_id,
        handler: function (response) {
            $('#razorpay_payment_id').val(response.razorpay_payment_id)
            $('#razorpay_signature').val(response.razorpay_signature)
            wallet_refill().done(function (result) {
                if (result.error == false) {
                    Livewire.navigate(appUrl + 'payments?response=wallet_success')
                    return
                } else {
                    Livewire.navigate(appUrl + 'payments?response=wallet_failed')
                    iziToast.error({
                        message: result.message,
                        position: "topRight",
                    });
                }
            });
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
        }
    }
    var rzp = new Razorpay(options)
    return rzp
}

function renderStripePopup(clientSecret, publicKey) {
    var stripe = Stripe(publicKey);
    stripe.initEmbeddedCheckout({
        clientSecret,
    }).then(function (checkout) {
        checkout.mount('#stripe-checkout');
        var iframe = document.querySelector('#stripe-checkout iframe');
        iframe.addEventListener('load', function () {
            var iframeWindow = iframe.contentWindow;
            iframeWindow.addEventListener('message', function (event) {
                console.log(event);
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
    // console.log(amount);
    // return
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
            alert('Window closed.');
        },
        callback: function (response) {
            console.log(response)
            if (response.status == "success") {
                $('#paystack_reference').val(response.reference)
                wallet_refill().done(function (result) {
                    if (result.error == false) {
                        Livewire.navigate(appUrl + 'payments?response=wallet_success')
                        return
                    } else {
                        Livewire.navigate(appUrl + 'payments?response=wallet_failed')
                        iziToast.error({
                            message: result.message,
                            position: "topRight",
                        });
                    }
                    return
                })
            }
        }
    });
    handler.openIframe();
}
document.addEventListener('livewire:navigated', () => {

    $("input[name='payment_method']").on('change', function () {
        let payment_methods = $("input[name='payment_method']:checked").val();
        if (payment_methods != 'paypal') {
            $('.paypal-buttons').remove();
            $('#place_order_btn').attr('disabled', false).html('Place Order')
        }
        if (payment_methods != 'stripe') {
            $("#stripe-checkout").addClass('d-none')
            $('#place_order_btn').attr('disabled', false).html('Place Order')
            return
        }
        $("#stripe-checkout").removeClass('d-none')
    })

    $('#wallet_refill_form').on('submit', (e) => {
        e.preventDefault();
        let amount = $('#add_amount').val();
        let mobile = $('#mobile').val();
        if (amount == "") {
            iziToast.error({
                message: "Please Add Amount To Refill.",
                position: "topRight",
            });
            return false;
        }
        let payment_methods = $("input[name='payment_method']:checked").val();
        if (payment_methods == undefined) {
            iziToast.error({
                message: "Please Select One Payment Method.",
                position: "topRight",
            });
            return false;
        }
        amount = parseFloat(amount).toFixed(2);

        if (payment_methods == 'phonepe') {
            $.post(appUrl + "payments/phonepe", {
                final_total: amount,
                user_id,
                mobile
            },
                function (response) {
                    if (response.error == false) {
                        $('#transaction_id').val(response.transaction_id);
                        if (response.payment_url != "") {
                            wallet_refill().done(function (result) {
                                if (result.error == false) {
                                    window.location.replace(response.payment_url);
                                    // iziToast.success({
                                    //     message: response.message,
                                    //     position: "topRight",
                                    // });
                                    return
                                } else {
                                    ziToast.error({
                                        message: result.message,
                                        position: "topRight",
                                    });
                                }
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
            $('#paypal-button-container').removeClass('d-none');
            // const d = new Date();
            let reference_id = d.getTime() + Math.round("100", "999");
            paypal.Buttons({
                createOrder: function (data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: amount,
                            },
                            reference_id: reference_id,
                        }]
                    });
                },
                onApprove: function (data, actions) {
                    return actions.order.capture().then(function (details) {
                        if (details.status == "COMPLETED") {
                            $('#transaction_id').val(reference_id);
                            wallet_refill().done(function (result) {
                                if (result.error == false) {
                                    Livewire.navigate(appUrl + 'payments?response=wallet_success')
                                    return
                                } else {
                                    Livewire.navigate(appUrl + 'payments?response=wallet_failed')
                                    iziToast.error({
                                        message: result.message,
                                        position: "topRight",
                                    });
                                }
                                return
                            })
                        }
                    });
                },
                onCancel: function (data) {
                    console.log("Payment Cancel");
                    console.log(data);
                }
            }).render('#paypal-button-container');
        } else if (payment_methods == 'paystack') {
            let reference_id = d.getTime() + Math.round("100", "999");
            let public_key = $('#paystack_public_key').val();
            let email = $("#user-email").val();
            payWithPaystack(email, amount, public_key, reference_id)
        } else if (payment_methods == 'stripe') {
            let type = "wallet";
            let product_name = "Wallet Refill";
            $.ajax({
                type: 'POST',
                url: appUrl + "payments/stripe",
                data: {
                    amount,
                    product_name,
                    type,
                },
                dataType: 'json',
                success: response => {
                    console.log(response);
                    renderStripePopup(response.client_secret, response.publicKey)
                }
            });
        } else if (payment_methods == 'razorpay') {
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
                    console.log(response);
                }
            });
        }
    })

    $("#withdrawal_modal").on('hidden.bs.modal', function () {
        $('#withdrawal_amount').val('');
        $('#payment_address').val('');
    });
    $("#add_wallet_modal").on('hidden.bs.modal', function () {
        $('#add_amount').val('');
    });

    $('#withdrawal_form').on('submit', (e) => {
        e.preventDefault();
        let amount_requested = $('#withdrawal_amount').val();
        amount_requested = parseInt(amount_requested);
        let payment_address = $('#payment_address').val();
        let balance = $('#balance').val();
        balance = parseInt(balance);
        if (amount_requested == "") {
            iziToast.error({
                message: "Please Fill Amount You Went to Withdraw",
                position: "topRight",
            });
            return
        }
        if (balance < amount_requested) {
            iziToast.error({
                message: "Unfortunately you don't have enough funds to Withdraw",
                position: "topRight",
            });
            return
        }
        if (amount_requested <= 0) {
            iziToast.error({
                message: "Please Enter Correct Amount",
                position: "topRight",
            });
            return
        }
        if (payment_address == "") {
            iziToast.error({
                message: "Please Fill Account Detail",
                position: "topRight",
            });
            return
        }
        $.ajax({
            type: "POST",
            url: appUrl + "wallet/withdrawal",
            data: {
                amount_requested,
                payment_address
            },
            success: function (response) {
                // console.log(response);
                if (response.error == false) {
                    $('#wallet_balance').text(response.balance)
                    $('#withdrawal_modal').modal('hide');
                    Livewire.dispatch('refreshComponent');
                    $("#wallet_withdrawal_request").bootstrapTable("refresh");
                    iziToast.success({
                        message: response.message,
                        position: "topRight",
                    });
                    return
                }
                iziToast.error({
                    message: response.message,
                    position: "topRight",
                });
                return
            }
        });
    })
})
