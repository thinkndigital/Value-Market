"use strict";

var session_user_id = $("#session_user_id").val();

var appUrl = document.getElementById("app_url").dataset.appUrl;

// register user

// AJAX form submission handler
$(document).on("submit", ".register_form", function (e) {
    e.preventDefault();

    var $form = $(this); // Reference to the form that was submitted
    var formData = new FormData($form[0]);
    formData.append("_token", $('meta[name="csrf-token"]').attr("content"));

    $.ajax({
        type: "POST",
        url: $form.attr("action"),
        dataType: "json",
        data: formData,
        processData: false,
        contentType: false,

        beforeSend: function () {
            $("#save-register-result-btn").html("Please Wait..");
            $("#save-register-result-btn").attr("disabled", true);
        },
        success: function (result) {
            if (result.error == false) {
                iziToast.success({
                    message: result.message,
                    position: "topRight",
                });
                $form[0].reset(); // Reset the specific form on success
                $("#register").modal("hide");
            } else {
                result.message.forEach((message) => {
                    iziToast.error({
                        message: message,
                        position: "topRight",
                    });
                });
            }
            $("#save-register-result-btn")
                .html("Register")
                .attr("disabled", false);
        },
        error: function (xhr, status, error) {
            // Handle AJAX errors if needed
            console.error(error);
        },
    });
});

// Reset form when modal is closed
$(document).on("hidden.bs.modal", "#register", function () {
    $(this).find(".register_form")[0].reset(); // Reset the form inside the closed modal
});

// search user

var pos_user_id = 0;

$("#select_user_id").on("change", function () {
    pos_user_id = $("#select_user_id").val();

    console.log(pos_user_id);

    $.ajax({
        type: "POST",
        url: appUrl + "seller/point_of_sale/getCustomerAddress",
        dataType: "json",
        data: {
            pos_user_id: pos_user_id,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },

        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");

            var html = "";
            if (result.error == false) {
                $(".customer_edit_address").removeClass("d-none");

                html = `<div class="col-md-8">
                <span class="user_aadress">${result.data.address}</span>
                </div >

                <div class="d-flex mt-3 align-items-center ">
                    <div class="d-flex align-items-center pos-customer-detail">
                        <img src="${result.data.user_image}" class="customer-img-box" alt="">
                            <h6 class="ms-2 pos-customer-name">Hi, ${result.data.user_name}</h6>
                    </div>
                    <div class="col-md-6 ms-3">
                        <div class="d-flex align-items-center">
                            <h6 class="m-0 mt-1">
                                ${result.data.mobile}
                            </h6>
                        </div>
                    </div>
                </div>`;

                $(".customer-address-detail").html(html);

                $("#product_address_id").val(result.data.address_id);
                $("#product_customer_address").val(result.data.address);
                $("#name").val(result.data.user_name);
                $("#mobile").val(result.data.mobile);
                $("#state").val(result.data.state);
                $("#city").val(result.data.city);
                $("#address").val(result.data.user_address);
                var selectedCountry = result.data.country;

                // Set the value of the select element with class 'country_list'
                $("#country_list").val(selectedCountry);
            } else {
                $(".customer_edit_address").addClass("d-none");

                html = `<div class="col-md-12 text-center text-danger">
                <span>${result.message}</span>
                </div>`;

                $(".customer-address-detail").html(html);
            }
        },
    });
});

// search combo user

var combo_user_id = 0;
$("#select_combo_user_id").on("change", function () {
    combo_user_id = $("#select_combo_user_id").val();

    $.ajax({
        type: "POST",
        url: appUrl + "seller/point_of_sale/getCustomerAddress",
        dataType: "json",
        data: {
            pos_user_id: combo_user_id,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },

        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");

            var html = "";
            if (result.error == false) {
                $(".combo_customer_edit_address").removeClass("d-none");

                html = `<div class="col-md-8">
                <span class="user_aadress">${result.data.address}</span>
                </div >

                <div class="d-flex mt-3 align-items-center ">
                    <div class="d-flex align-items-center pos-customer-detail">
                        <img src="${result.data.user_image}" class="customer-img-box" alt="">
                            <h6 class="ms-2 pos-customer-name">Hi, ${result.data.user_name}</h6>
                    </div>
                    <div class="col-md-6 ms-3">
                        <div class="d-flex align-items-center">
                            <h6 class="m-0 mt-1">
                                ${result.data.mobile}
                            </h6>
                        </div>
                    </div>
                </div>`;

                $("#combo_address_id").val(result.data.address_id);
                $("#combo_customer_address").val(result.data.address);
                $("#combo_name").val(result.data.user_name);
                $("#combo_mobile").val(result.data.mobile);
                $("#combo_state").val(result.data.state);
                $("#combo_city").val(result.data.city);
                $("#combo_address").val(result.data.user_address);
                var selectedCountry = result.data.country;

                // Set the value of the select element with class 'country_list'
                $("#country_list").val(selectedCountry);
            } else {
                $(".combo_customer_edit_address").addClass("d-none");

                html = `<div class="col-md-12 text-center text-danger">
                <span>${result.message}</span>
                </div>`;
            }
            $(".combo_customer-address-detail").html(html);
        },
    });
});

$("#select_combo_user_id").on("change", function () {
    combo_user_id = $("#select_combo_user_id").val();
});

$(".select_user").select2({
    ajax: {
        url: appUrl + "seller/point_of_sale/get_users",
        type: "GET",
        dataType: "json",
        delay: 250,
        data: function (params) {
            return {
                search: params.term, // search term
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
    placeholder: "Search for user",
});

// clear selected user

$(".clear_user_search").on("click", function () {
    $(".select_user").empty();
});

// ready event

$(function () {
    var category_id = $("#product_categories").val();
    var limit = $("#limit").val();
    var offset = $("#offset").val();
    var combo_limit = $("#combo_products_limit").val();
    var combo_offset = $("#combo_products_offset").val();
    get_products(category_id, limit, offset);
    get_combo_products(category_id, combo_limit, combo_offset);
    display_cart();
    display_combo_cart();
});

// get category wise product

$("#product_categories").on("change", function () {
    var category_id = $("#product_categories").val();
    var limit = $("#limit").val();
    $("#current_page").val("0");
    get_products(category_id, limit, 0);
});

//product tab pagination

function paginate(total, current_page, limit) {
    var number_of_pages = Math.ceil(total / limit);

    var pagination = `<div class="align-items-center d-flex justify-content-between">
        <div class="col-12 float-right pe-6 pagination-sm">
            <nav class="d-flex justify-items-center justify-content-between">
                <div class="d-flex justify-content-between flex-fill d-sm-none">
                    <ul class="pagination">
                        <li class="page-item ${
                            current_page == 0 ? "disabled" : ""
                        }" aria-disabled="true">
                            <span class="page-link" onclick="prev_page()">« Previous</span>
                        </li>
                        <li class="page-item ${
                            current_page == number_of_pages - 1
                                ? "disabled"
                                : ""
                        }">
                            <a class="page-link" href="javascript:next_page()">Next »</a>
                        </li>
                    </ul>
                </div>
                <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
                    <div>
                        <p class="small text-muted">
                            Showing
                            <span class="fw-semibold">${
                                current_page * limit + 1
                            }</span>
                            -
                            <span class="fw-semibold">${Math.min(
                                (current_page + 1) * limit,
                                total
                            )}</span>
                            of
                            <span class="fw-semibold">${total}</span>

                        </p>
                    </div>
                    <div>
                    <ul class="pagination">
                    <li class="page-item ${
                        current_page == 0 ? "disabled" : ""
                    }" aria-disabled="true" aria-label="« Previous">
                        <a class="page-link"  href="javascript:prev_page()">‹</a>
                    </li>

                    ${generatePageLinks(current_page, number_of_pages)}

                    <li class="page-item ${
                        current_page == number_of_pages ? "disabled" : ""
                    }">
                        <a class="page-link" href="javascript:next_page()">›</a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>

    </div>
</div>`;

    $(".pagination-container").html(pagination);
}

function generatePageLinks(currentPage, numberOfPages) {
    let links = "";
    for (let i = 0; i < numberOfPages; i++) {
        const isActive = currentPage == i ? "active" : "";
        links += `<li class="page-item ${isActive}">
                    <a class="page-link" href="javascript:go_to_page(8,${i})">${
            i + 1
        }</a>
                  </li>`;
    }
    return links;
}

function next_page() {
    var current_page = parseInt($("#current_page").val());
    var total = parseInt($("#total_products").val());
    var limit = parseInt($("#limit").val());

    var number_of_pages = Math.ceil(total / limit);
    var next_page = current_page + 1;

    if (next_page < number_of_pages) {
        go_to_page(limit, next_page);
    }
}

function go_to_page(limit, page_number) {
    var total = $("#total_products").val();
    var category_id = $("#product_categories").val();
    var offset = page_number * limit;

    get_products(category_id, limit, offset);
    paginate(total, page_number, limit);
    $("#limit").val(limit);
    $("#combo_products_limit").val(limit);
    $("#offset").val(offset);
    $("#current_page").val(page_number);
}

function prev_page() {
    var current_page = parseInt($("#current_page").val());
    var limit = parseInt($("#limit").val());
    var prev_page = current_page - 1;

    if (prev_page >= 0) {
        go_to_page(limit, prev_page);
    }
}

//combo product tab pagination

function combo_product_paginate(total, current_page, limit) {
    var number_of_pages = Math.ceil(total / limit);

    var pagination = `<div class="align-items-center d-flex justify-content-between">
        <div class="col-12 float-right pe-6 pagination-sm">
            <nav class="d-flex justify-items-center justify-content-between">
                <div class="d-flex justify-content-between flex-fill d-sm-none">
                    <ul class="pagination">
                        <li class="page-item ${
                            current_page == 0 ? "disabled" : ""
                        }" aria-disabled="true">
                            <span class="page-link" onclick="combo_product_prev_page()">« Previous</span>
                        </li>
                        <li class="page-item ${
                            current_page == number_of_pages - 1
                                ? "disabled"
                                : ""
                        }">
                            <a class="page-link" href="javascript:combo_product_next_page()">Next »</a>
                        </li>
                    </ul>
                </div>
                <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
                    <div>
                        <p class="small text-muted">
                            Showing
                            <span class="fw-semibold">${
                                current_page * limit + 1
                            }</span>
                            -
                            <span class="fw-semibold">${Math.min(
                                (current_page + 1) * limit,
                                total
                            )}</span>
                            of
                            <span class="fw-semibold">${total}</span>

                        </p>
                    </div>
                    <div>
                    <ul class="pagination">
                    <li class="page-item ${
                        current_page == 0 ? "disabled" : ""
                    }" aria-disabled="true" aria-label="« Previous">
                        <a class="page-link"  href="javascript:combo_product_prev_page()">‹</a>
                    </li>

                    ${combo_product_generatePageLinks(
                        current_page,
                        number_of_pages
                    )}

                    <li class="page-item ${
                        current_page == number_of_pages ? "disabled" : ""
                    }">
                        <a class="page-link" href="javascript:combo_product_next_page()">›</a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>

    </div>
</div>`;

    $(".combo-product-pagination-container").html(pagination);
}

function combo_product_generatePageLinks(currentPage, numberOfPages) {
    let links = "";
    for (let i = 0; i < numberOfPages; i++) {
        const isActive = currentPage == i ? "active" : "";
        links += `<li class="page-item ${isActive}">
                    <a class="page-link" href="javascript:combo_product_go_to_page(8,${i})">${
            i + 1
        }</a>
                  </li>`;
    }
    return links;
}

function combo_product_next_page() {
    var current_page = parseInt($("#combo_product_current_page").val());
    var total = parseInt($("#total_combo_products").val());
    var limit = parseInt($("#combo_products_limit").val());

    var number_of_pages = Math.ceil(total / limit);
    var next_page = current_page + 1;

    if (next_page < number_of_pages) {
        combo_product_go_to_page(limit, next_page);
    }
}

function combo_product_go_to_page(limit, page_number) {
    var total = $("#total_combo_products").val();
    var category_id = $("#product_categories").val();
    var offset = page_number * limit;

    get_combo_products(category_id, limit, offset);
    combo_product_paginate(total, page_number, limit);
    $("#combo_products_limit").val(limit);
    $("#combo_products_offset").val(offset);
    $("#combo_product_current_page").val(page_number);
}

function combo_product_prev_page() {
    var current_page = parseInt($("#combo_product_current_page").val());
    var limit = parseInt($("#combo_products_limit").val());
    var prev_page = current_page - 1;

    if (prev_page >= 0) {
        combo_product_go_to_page(limit, prev_page);
    }
}

// get products function

function get_products(
    category_id = "",
    limit = 8,
    offset = 0,
    search_parameter = ""
) {
    // console.log(limit);
    $.ajax({
        type: "GET",
        url: `/seller/point_of_sale/get_products?category_id=${category_id}&limit=${limit}&offset=${offset}&search=${search_parameter}`,
        dataType: "json",
        beforeSend: function () {
            $("#get_products").html(
                `<div class="text-center" style='min-height:450px;' ><h4>Please wait.. . loading products..</h4></div>`
            );
        },
        success: function (data) {
            if (data.error == "false") {
                $("#total_products").val(data.total);
                $("#get_products").empty();
                display_products(data.products);
                var total = $("#total_products").val();

                var current_page = $("#current_page").val();
                var limit = $("#limit").val();
                var search_parameter = $("#search_products").val();
                paginate(total, current_page, limit, search_parameter);
            } else {
                $("#get_products").html(data.message);
                $("#get_products").empty();
            }
        },
    });
}

function get_combo_products(
    category_id = "",
    limit = 8,
    offset = 0,
    search_parameter = ""
) {
    $.ajax({
        type: "GET",
        url: `/seller/point_of_sale/get_combo_products?category_id=${category_id}&limit=${limit}&offset=${offset}&search=${search_parameter}`,
        dataType: "json",
        beforeSend: function () {
            $("#get_combo_products").html(
                `<div class="text-center" style='min-height:450px;' ><h4>Please wait.. . loading products..</h4></div>`
            );
        },
        success: function (data) {
            if (data.error == "false") {
                $("#total_combo_products").val(data.products.total);
                $("#get_combo_products").empty();
                display_combo_data(data.products);
                var total = $("#total_combo_products").val();
                var current_page = $("#combo_product_current_page").val();
                var limit = $("#combo_products_limit").val();
                var search_parameter = $("#search_products").val();
                combo_product_paginate(
                    total,
                    current_page,
                    limit,
                    search_parameter
                );
            } else {
                $("#get_combo_products").html(data.message);
                $("#get_combo_products").empty();
            }
        },
    });
}

function display_combo_data(data = "", groupName = "") {
    var display_combo_data = "";
    var i;
    var j;
    var k;
    var product_id;
    var data = data;

    if (data.combo_product.length > 0) {
        for (i = 0; i < data.combo_product.length; i++) {
            var productsJson = JSON.stringify(
                data.combo_product[i].product_details
            );

            var currency = $("#cart-total-price").attr("data-currency");
            var product_special_price =
                data.combo_product[i].special_price > 0
                    ? data.combo_product[i].special_price
                    : data.combo_product[i].price;

            var product_price =
                data.combo_product[i].special_price > 0
                    ? data.combo_product[i].price
                    : 0;
            var product_final_price =
                product_price > 0 ? currency + product_price : 0;

            display_combo_data +=
                '<div class="col-md-6 col-lg-4 mt-5">' +
                '<div class="shop-item">' +
                '<div class="shop-item-image d-flex justify-content-center align-item-center mb-3">' +
                '<img class="item-image" src="' +
                data.combo_product[i].image +
                '" />' +
                "</div>" +
                '<span class="shop-item-title text-start">' +
                data.combo_product[i].title +
                "</span>" +
                '<input type="hidden" name="shop-item-id" value="' +
                data.combo_product[i].id +
                '">' +
                '<input type="hidden" name="shop-item-seller-id" value="' +
                data.combo_product[i].seller_id +
                '">' +
                '<input type="hidden" name="combo_price" value="' +
                product_special_price +
                '">' +
                "</div >" +
                '<div class="d-flex justify-content-between shop-item-details mt-3">' +
                "<div>" +
                '<p class="mb-0 product-price">' +
                product_special_price +
                "</p>" +
                '<p class="mb-0 text-muted"><del>' +
                product_final_price +
                "</del></p>" +
                "</div>" +
                "<div>" +
                '<button class="btn btn-xs btn-primary btn-show-combo-modal" data-bs-toggle="modal" data-id="' +
                data.combo_product[i].id +
                '" onclick="add_combo_item(event)" data-bs-target="#combo_modal">Show Products</button>' +
                '<input type="hidden" class="products-data" value=\'' +
                productsJson.replace(/'/g, "&quot") +
                "' />" +
                "</div>" +
                "</div>" +
                "</div>";
        }
        $("#get_combo_products").append(display_combo_data);
    } else {
        $("#get_combo_products").html(
            `<div class="text-center" style='min-height:450px;' ><h4> No products available...</h4></div>`
        );
    }
}

function add_combo_item(e) {
    $("#combo_data_detail").empty();
    $("#combo_id").val(e.target.dataset.id);
    var button = e.target;
    var shopItem = button.parentElement.parentElement;

    var seller_id = $('input[name="shop-item-seller-id"]').val();
    var combo_item_id = button.dataset.id;

    var display_price = $(shopItem).find(".product-price").text();

    var title =
        button.parentElement.parentElement.parentElement.getElementsByClassName(
            "shop-item-title"
        )[0].innerText;

    var currency = $(".cart-total-price").attr("data-currency");
    var image =
        button.parentElement.parentElement.parentElement.getElementsByClassName(
            "item-image"
        )[0].src;

    var productsData = JSON.parse($(shopItem).find(".products-data").val());

    document.getElementById("combo_data_detail").innerHTML =
        '<div class="align-items-center d-flex justify-content-between product-detail">' +
        "<div class='me-2 d-flex align-items-center'>" +
        '<img class="img-responsive rounded table-image item-image" width="100%" src="' +
        image +
        '" data-zoom="" alt="Product image">' +
        '<span class="ms-2 product-title">' +
        title +
        "</span>" +
        "</div>" +
        "<div>" +
        "<h6>" +
        currency +
        display_price +
        "</h6>" +
        "</div>" +
        "</div>";

    var productContainer = '<div class="row">';

    for (var i = 0; i < productsData.length; i++) {
        var product = productsData[i];

        var product_id = product.id;
        var productName = product.name;
        var productImage = product.image;

        productContainer +=
            '<div class="col-lg-3 mt-5">' +
            '<div class="mt-2' +
            '" data-product-id="' +
            product_id +
            '">' +
            '<div class="d-flex justify-content-center justify-content-start active">' +
            '<img class="img-responsive rounded combo-product-image" src="' +
            window.location.origin +
            "/storage/" +
            productImage +
            '" data-zoom="" alt="Product image">' +
            '<div class="cz-image-zoom-pane"></div>' +
            "</div>" +
            '<div class="mt-3 text-center">' +
            "<span>" +
            productName +
            "</span>" +
            "</div>" +
            "</div>" +
            "</div>";
    }

    $("#combo_data_detail").append(productContainer);

    var comboPrice = display_price;

    $(".add_combo_button").one("click", function () {
        var combo_item = {
            id: combo_item_id.trim(),
            title: $(".product-title").text(),
            price: comboPrice,
            image: image,
            quantity: 1,
            product_type: "combo_product",
            seller_id: seller_id,
        };

        var combo_items = localStorage.getItem("combo_product_cart");
        combo_items = combo_items !== null ? JSON.parse(combo_items) : [];

        // Check if the item with the same ID already exists in the cart
        var existingItemIndex = combo_items.findIndex(function (item) {
            return item.id === combo_item.id;
        });

        if (existingItemIndex !== -1) {
            iziToast.error({
                message: ["Combo item already exists in cart"],
                position: "topRight",
            });
            return; // Exit the function to prevent adding the duplicate item
        }

        combo_items.push(combo_item);
        localStorage.setItem("combo_product_cart", JSON.stringify(combo_items));

        iziToast.success({
            message: ["Combo item added to cart"],
            position: "topRight",
        });

        display_combo_cart();
        $("#combo_modal").modal("hide");
        var combo_items =
            JSON.parse(localStorage.getItem("combo_product_cart")) || [];
        var currency = $("#combo_total_price").data("currency");
        calculate_combo_total(combo_items, currency);
    });
}

function display_combo_cart() {
    var combo_items =
        JSON.parse(localStorage.getItem("combo_product_cart")) || [];

    $(".combo-cart-items").empty();
    $(".total_combo_cart_items").text(
        "(" + combo_items.length + ")" + " " + "Items in Cart"
    );
    var cartItem = "";
    var i = 1;
    var currency = $("#combo_total_price").data("currency");

    if (combo_items !== null && combo_items.length > 0) {
        combo_items.forEach((combo_item) => {
            if (combo_item.seller_id == session_user_id) {
                cartItem += `<div class="row ${
                    i > 3 ? "d-none" : ""
                } mt-5" data-id="${combo_item.id}">
                        <div class="align-items-center col-md-2 d-flex">
                            <span>${i}</span>
                            <img src="${
                                combo_item.image
                            }" class="table-image ms-1">
                        </div>
                        <div class="align-items-center col-md-6 d-flex">
                            <span>${wordLimit(combo_item.title)}</span>
                        </div>
                        <div class="col-md-4 pe-0">
                            <div class="input-group">
                                <input type="hidden" class="product-variant" name="variant_ids[]" type="number" value=${
                                    combo_item.id
                                }>
                                <button type="button" class="combo_cart-quantity-input btn" data-operation="plus"><i class='bx bx-plus-circle'></i></button>
                                <input class="combo_cart-quantity-input-new form-control text-center p-0" name="quantity[]" value="${
                                    combo_item.quantity
                                }">
                                <button type="button" class="combo_cart-quantity-input btn" data-operation="minus"><i class='bx bx-minus-circle'></i></button>
                            </div>
                            <div class="d-flex mt-2 justify-content-between align-items-center">
                                <div><h6 class="cart-price">${
                                    currency +
                                    parseFloat(
                                        combo_item.price
                                    ).toLocaleString()
                                }</h6></div>
                                <div><button type="button" class="btn remove-combo-cart-item" data-variant_id=${
                                    combo_item.id
                                }><i class='bx bx-trash'></i></div>
                            </div>
                        </div>
                        <hr class="mt-5">
                    </div>`;

                i++;
            }
        });
    } else {
        cartItem = `

              <div class="row">
                  <div class="col mt-4 d-flex justify-content-center text-danger h5">No items in cart</div>
              </div>`;
    }

    $(".combo-cart-items").append(cartItem);

    if (combo_items !== null && combo_items.length > 3) {
        $(".combo-cart-items").append(
            '<div class="d-flex justify-content-center align-items-center"><a class="btn" id="comboShowMoreBtn">Expand More </a><i class="bx bx-chevron-down"></i> </div>'
        );
    }

    var currency = $("#combo_total_price").data("currency");
    var totalPrice = calculate_combo_total(combo_items, currency);

    $("#combo_total_price").text(totalPrice);
    // update_combo_cart_quantity();
}

// diaply products in view

$(document).on("keyup", ".combo_delivery_charge_service", function (e) {
    e.preventDefault();
    var combo_items = localStorage.getItem("combo_product_cart");
    combo_items =
        localStorage.getItem("combo_product_cart") !== null
            ? JSON.parse(localStorage.getItem("combo_product_cart"))
            : [];
    var currency = $("#combo_total_price").data("currency");
    calculate_combo_total(combo_items, currency);
    return;
});

$(document).on("keyup", ".combo_discount_service", function (e) {
    var combo_items = localStorage.getItem("combo_product_cart");
    combo_items =
        localStorage.getItem("combo_product_cart") !== null
            ? JSON.parse(localStorage.getItem("combo_product_cart"))
            : [];
    var currency = $("#combo_total_price").data("currency");
    calculate_combo_total(combo_items, currency);
});

function calculate_combo_total(combo_items, currency) {
    var final_total = 0;

    var delivery_charges = $(".combo_delivery_charge_service").val();
    var discount = $(".combo_discount_service").val();

    if (delivery_charges != 0 && delivery_charges != null) {
        final_total = parseFloat(final_total) + parseFloat(delivery_charges);
    }

    if (discount != 0 && discount != null) {
        final_total = parseFloat(final_total) - parseFloat(discount);
    }

    var main_cart_total = 0;
    if (Array.isArray(combo_items)) {
        combo_items.forEach(function (combo_item) {
            {
                // Check the order type of the combo item
                var price = parseFloat(combo_item.price);
                var quantity = parseInt(combo_item.quantity);
                final_total += price * quantity;
                main_cart_total += price * quantity;
            }
        });

        var currency = $("#combo_total_price").data("currency");
        // for display delivery charge under cart data

        $("#combo_total_price").text(currency + " " + final_total.toFixed(2));
        $("#combo-cart-total").text(main_cart_total.toFixed(2));
        $(".combo-cart-main-total").val(main_cart_total.toFixed(2));
        $(".combo_total_price").html(currency + " " + main_cart_total);
        $(".combo_hidden_sub_total").val(main_cart_total);
        $(".combo_final_total").html(final_total);
        $(".combo_hidden_final_total").val(final_total);
        return currency + " " + final_total.toFixed(2);
    }
}

$(document).on("click", ".remove-combo-cart-item", function (e) {
    e.preventDefault();
    if (
        e.delegateTarget.activeElement.classList.value.includes(
            "remove-combo-cart-item"
        )
    ) {
        iziToast.error({
            message: ["Product removed from cart"],
        });
        var id = $(this).data("variant_id");
        $(this).parent().parent().remove();
        var cart = localStorage.getItem("combo_product_cart");
        cart =
            localStorage.getItem("combo_product_cart") !== null
                ? JSON.parse(cart)
                : null;

        if (cart) {
            var new_cart = cart.filter(function (item) {
                return item.id != id;
            });
            localStorage.setItem(
                "combo_product_cart",
                JSON.stringify(new_cart)
            );
            display_combo_cart();
        }
    }
});

$(document).on("click", ".combo_cart-quantity-input", function (e) {
    var operation = $(this).data("operation");
    var id = $(this).siblings().val();

    var input =
        operation == "plus" ? $(this).siblings()[1] : $(this).siblings()[2];
    var qty = $(this)
        .parent()
        .siblings(".item-quantity")
        .find(".combo_cart-quantity-input")
        .val();
    var qty = parseInt(input.value, 10);
    var data = (input.value = operation == "minus" ? qty - 1 : qty + 1);

    update_combo_cart_quantity(data, id);
});

$(document).on("change", ".combo_cart-quantity-input-new", function (e) {
    var id = $(this).siblings().val();
    var quantity = $(this).val();
    var data = quantity;

    update_combo_cart_quantity(data, id);
});

function update_combo_cart_quantity(data, id) {
    if (isNaN(data) || data <= 0) {
        data = 1;
    }
    var cart = localStorage.getItem("combo_product_cart");
    cart =
        localStorage.getItem("combo_product_cart") !== null
            ? JSON.parse(cart)
            : null;
    if (cart) {
        var i = cart.map((i) => i.id).indexOf(id);
        cart[i].quantity = data;
        localStorage.setItem("combo_product_cart", JSON.stringify(cart));
        display_combo_cart();
    }
}

var prod_id = "";

function display_products(products = "") {
    var display_products = "";

    //   var modal = "";
    var i;
    var j;
    var k;
    var product_id;
    var products = products.product;

    if (products !== null && products.length > 0) {
        for (i = 0; i < products.length; i++) {
            var product_special_price = "";
            var product_final_price = "";
            var product_price = "";
            // console.log(products[i]);
            display_products +=
                '<div class="col-md-6 col-lg-4 mt-5">' +
                '<div class="shop-item">' +
                '<input type="hidden"  name="shop_item_id" value="' +
                products[i].id +
                '">' +
                '<input type="hidden" name="pos_shop_item_product_type" value="' +
                products[i].product_type +
                '">' +
                '<div class="shop-item-image d-flex justify-content-center align-item-center mb-3">' +
                '  <img class="item-image" src="' +
                products[i].image +
                '" />' +
                "</div>" +
                '<span class="shop-item-title text-start">' +
                products[i].name +
                "<div class='title-overlay'></div>" +
                " </span>" +
                '<input type="hidden" name="shop_item_seller_id" value="' +
                products[i].seller_id +
                '">';

            var variants = products[i]["variants"];

            var total_price = document.getElementById("cart-total-price");
            var currency = "";
            if ($("#cart-total-price").length) {
                currency = total_price.getAttribute("data-currency");
            }
            if (products[i].product_type == "variable_product") {
                display_products +=
                    '<a href="#" class="form-control form-select mt-2 product-variants variant_value"  data-product-id="' +
                    products[i].id +
                    '">';

                var variant_values = variants[0]["variant_values"]
                    ? variants[0]["variant_values"]
                    : "";

                var variant_price =
                    variants[0]["special_price"] > 0
                        ? variants[0]["special_price"]
                        : variants[0]["price"];

                display_products +=
                    variant_values +
                    " " +
                    currency +
                    parseFloat(variant_price).toLocaleString() +
                    "</a>";

                // }
                product_special_price =
                    variants[0]["special_price"] > 0
                        ? variants[0]["special_price"]
                        : variants[0]["price"];

                product_price =
                    variants[0]["special_price"] > 0 ? variants[0]["price"] : 0;
                product_final_price =
                    product_price > 0 ? currency + product_price : "";
            } else {
                product_special_price =
                    variants[0]["special_price"] > 0
                        ? variants[0]["special_price"]
                        : variants[0]["price"];

                product_price =
                    variants[0]["special_price"] > 0 ? variants[0]["price"] : 0;
                product_final_price =
                    product_price > 0 ? currency + product_price : 0;
                // display_products += '<div></div>';
            }

            display_products +=
                "</div>" +
                '<div class="d-flex justify-content-between shop-item-details ' +
                (products[i].product_type === "variable_product"
                    ? "mt-3"
                    : "mt-10") +
                '">' +
                "<div>" +
                '<p class="mb-0 product-price">' +
                currency +
                parseFloat(product_special_price).toLocaleString() +
                "</p>";

            if (product_price > 0) {
                display_products +=
                    '<p class="mb-0 text-muted"><del>' +
                    product_final_price +
                    "</del></p>";
            }
            var isDisabled = "";
            if (products[i].product_type == "variable product") {
                // console.log('here');
                isDisabled =
                    variants[0]["stock_type"] === ""
                        ? ""
                        : (variants[0]["availability"] === 0 ||
                              variants[0]["availability"] === 1) &&
                          variants[0]["stock"] > 0
                        ? ""
                        : "disabled";
            } else {
                // console.log(products[i]["availability"]);
                var isDisabled =
                    variants[0]["stock_type"] == ""
                        ? ""
                        : (products[i]["availability"] == 0 ||
                              products[i]["availability"] == 1) &&
                          products[i]["stock"] > 0
                        ? ""
                        : "disabled";
            }

            display_products +=
                "</div>" +
                "<div>" +
                '<button class="btn btn-xs btn-primary shop-item-button p-2" ' +
                isDisabled +
                ' data-price="' +
                product_special_price +
                '" data-variant_id="' +
                variants[0]["id"] +
                '" data-variant_value="' +
                variants[0]["variant_values"] +
                '" data-special_price="' +
                variants[0]["special_price"] +
                '" data-variants_price="' +
                variants[0]["price"] +
                '" data-product_type="' +
                "regular" +
                '" onclick="add_to_cart(event)" type="button">Add</button>' +
                "</div>" +
                "</div>";
            display_products += "</div>" + "</div>" + "</div>";
        }
        $("#get_products").append(display_products);
    } else {
        $("#get_products").html(
            `<div class="text-center" style='min-height:450px;' ><h4> No products available...</h4></div>`
        );
    }
}

//search product

$("#search_products").on("keyup", function (e) {
    e.preventDefault();
    var search = $(this).val();
    get_products("", 25, 0, search);
});
$("#search_combo_products").on("keyup", function (e) {
    e.preventDefault();
    var search = $(this).val();
    get_combo_products("", 25, 0, search);
});

// add to cart

function add_to_cart(e) {
    var cartRow = document.createElement("div");
    cartRow.classList.add("cart-row");
    var button = e.target;
    var shopItem = button.parentElement.parentElement;
    // console.log(shopItem);
    var variant_dropdown = shopItem.children[0].children[4];
    var display_price = button.dataset.price;
    var product_id = $('input[name="shop_item_id"]').val();
    // var product_type = $('input[name="pos_shop_item_product_type"]').val();
    // console.log(product_type);
    var variant_id = button.dataset.variant_id;
    var seller_id = $('input[name="shop_item_seller_id"]').val();

    var variant_values = button.dataset.variant_value;

    var special_price = button.dataset.special_price;
    var price = button.dataset.variants_price;
    var product_type = button.dataset.product_type;

    var title =
        button.parentElement.parentElement.parentElement.getElementsByClassName(
            "shop-item-title"
        )[0].innerText;
    var image =
        button.parentElement.parentElement.parentElement.getElementsByClassName(
            "item-image"
        )[0].src;
    /* create JSON array object */
    var cart_item = {
        product_id: product_id.trim(),
        product_type: product_type.trim(),
        seller_id: seller_id.trim(),
        variant_id: variant_id,
        title: title,
        variant: variant_values,
        image: image,
        display_price: display_price.trim(),
        quantity: 1,
        special_price: special_price,
        price: price,
    };
    var cart = localStorage.getItem("cart");
    cart = localStorage.getItem("cart") !== null ? JSON.parse(cart) : null;
    if (cart !== null && cart !== undefined) {
        if (cart.find((item) => item.variant_id === variant_id)) {
            alert("This item is already present in your cart");
            return;
        } else {
            iziToast.success({
                message: ["Product added to cart"],
            });
        }
        cart.push(cart_item);
    } else {
        cart = [cart_item];
    }
    localStorage.setItem("cart", JSON.stringify(cart));
    display_cart();
}

//display cart

function display_cart() {
    var cart = localStorage.getItem("cart");

    // var session_user_id = 2;
    cart = localStorage.getItem("cart") !== null ? JSON.parse(cart) : null;
    $(".total_product_cart_items").text(
        "(" + cart.length + ")" + " " + "Items in Cart"
    );
    var currency = $(".cart-total-price").attr("data-currency");
    var cartRowContents = "";
    var i = 1;
    if (cart !== null && cart.length > 0) {
        cart.forEach((item) => {
            // console.log(item);
            if (item.seller_id == session_user_id) {
                cartRowContents += `<div class="row ${
                    i > 3 ? "d-none" : ""
                } mt-5">
                        <div class="align-items-center col-md-2 d-flex">
                            <span>${i}</span>
                            <img src="${item.image}" class="table-image ms-1">
                        </div>
                        <div class="align-items-center col-md-6 d-flex">
                            <span>${wordLimit(item.title)}</span>
                            ${
                                item.variant !== "null"
                                    ? `<span class="ms-2">  (${item.variant})</span>`
                                    : ""
                            }
                        </div>
                        <div class="col-md-4 pe-0">
                            <div class="input-group">
                                <input type="hidden" class="product-variant" name="variant_ids[]" type="number" value=${
                                    item.variant_id
                                }>
                                <button type="button" class="cart-quantity-input btn" data-operation="plus"><i class='bx bx-plus-circle'></i></button>
                                <input class="cart-quantity-input-new form-control text-center p-0" name="quantity[]" value="${
                                    item.quantity
                                }">
                                <button type="button" class="cart-quantity-input btn" data-operation="minus"><i class='bx bx-minus-circle'></i></button>
                            </div>
                            <div class="d-flex mt-2 justify-content-between align-items-center">
                                <div><h6 class="cart-price">${
                                    currency +
                                    parseFloat(
                                        item.display_price
                                    ).toLocaleString()
                                }</h6></div>
                                <div><button type="button" class="btn remove-cart-item" data-variant_id=${
                                    item.variant_id
                                }><i class='bx bx-trash'></i></div>
                            </div>
                        </div>
                        <hr class="mt-5">
                    </div>`;

                i++;
            }
        });
    } else {
        cartRowContents = `

              <div class="row">
                  <div class="col mt-4 d-flex justify-content-center text-danger h5">No items in cart</div>
              </div>`;
    }

    $(".cart-items").html(cartRowContents);
    if (cart !== null && cart.length > 3) {
        $(".cart-items").append(
            '<div class="d-flex justify-content-center align-items-center"><a class="btn" id="showMoreBtn">Expand More </a><i class="bx bx-chevron-down"></i> </div>'
        );
    }
    update_cart_total();
    update_final_cart_total();
}

$(document).on("click", "#showMoreBtn", function (e) {
    // Toggle visibility of hidden items
    $(".cart-items .row:gt(2)").toggleClass("d-none");
    // Update the button text based on the current state

    const buttonText =
        $(this).text() == "Expand More " ? "Expand Less " : "Expand More ";

    $(this).text(buttonText);
});

$(document).on("click", "#comboShowMoreBtn", function (e) {
    // Toggle visibility of hidden items
    $(".combo-cart-items .row:gt(2)").toggleClass("d-none");
    // Update the button text based on the current state

    const buttonText =
        $(this).text() == "Expand More " ? "Expand Less " : "Expand More ";

    $(this).text(buttonText);
});

// display limited word

function wordLimit(string, length = 42, dots = "...") {
    return string.length > length
        ? string.slice(0, length - dots.length) + dots
        : string;
}

// quantity update operation

$(document).on("click", ".cart-quantity-input", function (e) {
    var operation = $(this).data("operation");
    var variant_id = $(this).siblings().val();
    var input =
        operation == "plus" ? $(this).siblings()[1] : $(this).siblings()[2];
    var qty = $(this)
        .parent()
        .siblings(".item-quantity")
        .find(".cart-quantity-input")
        .val();
    var qty = parseInt(input.value, 10);
    var data = (input.value = operation == "minus" ? qty - 1 : qty + 1);

    update_quantity(data, variant_id);
});

$(document).on("change", ".cart-quantity-input-new", function (e) {
    var variant_id = $(this).siblings().val();
    var quantity = $(this).val();
    var data = quantity;

    update_quantity(data, variant_id);
});

function update_quantity(data, variant_id) {
    if (isNaN(data) || data <= 0) {
        data = 1;
    }
    var cart = localStorage.getItem("cart");
    cart = localStorage.getItem("cart") !== null ? JSON.parse(cart) : null;
    if (cart) {
        var i = cart.map((i) => i.variant_id).indexOf(variant_id);
        cart[i].quantity = data;
        localStorage.setItem("cart", JSON.stringify(cart));
        display_cart();
    }
}

//update cart total

function update_cart_total() {
    var total = get_cart_total();
    // console.log(total);
    $(".cart-total-price").html(
        total.currency + "" + total.cart_total_formated
    );
    return;
}

// update final total

function update_final_cart_total() {
    var cart = get_cart_total();
    var sub_total = cart.cart_total;
    var delivery_charges = $(".delivery_charge_service").val();
    var discount = $(".discount_service").val();
    var final_total = sub_total;
    var currency = $("#cart-total-price").attr("data-currency");

    if (delivery_charges != 0 && delivery_charges != null) {
        final_total = parseFloat(final_total) + parseFloat(delivery_charges);
    }

    if (discount != 0 && discount != null) {
        final_total = parseFloat(final_total) - parseFloat(discount);
    }

    var res = {
        currency: currency,
        total: final_total,
        cart_total: parseFloat(final_total).toLocaleString(),
    };
    $(".final_total").html(
        final_total.currency + "" + final_total.cart_total_formated
    );
    $(".final_total").html(res.currency + "" + res.cart_total);
    $(".main_cart_total").val(res.cart_total);
    return;
}

// get cart total

function get_cart_total() {
    var cart = localStorage.getItem("cart");
    cart = cart !== null && cart !== undefined ? JSON.parse(cart) : null;
    var cart_total = 0;
    // var session_user_id = 2;

    if (cart !== null && cart !== undefined) {
        cart_total = cart.reduce((cart_total, item) => {
            if (item.seller_id == session_user_id) {
                return (
                    cart_total +
                    parseFloat(item.display_price) * parseFloat(item.quantity)
                );
            }
            return cart_total;
        }, 0);
    }

    var currency = $("#cart-total-price").attr("data-currency");
    var total = {
        currency: currency,
        cart_total: cart_total,
        cart_total_formated: parseFloat(cart_total).toLocaleString(),
    };
    return total;
}

// delivery charge and dicount keyup event

$(document).on("keyup", ".delivery_charge_service", function (e) {
    e.preventDefault();
    update_final_cart_total();
    return;
});

$(document).on("keyup", ".discount_service", function (e) {
    update_final_cart_total();
    return;
});

$(document).on("click", ".remove-cart-item", function (e) {
    e.preventDefault();
    if (
        e.delegateTarget.activeElement.classList.value.includes(
            "remove-cart-item"
        )
    ) {
        iziToast.error({
            message: ["Product removed from cart"],
        });
        var variant_id = $(this).data("variant_id");
        $(this).parent().parent().remove();
        var cart = localStorage.getItem("cart");
        cart = localStorage.getItem("cart") !== null ? JSON.parse(cart) : null;
        if (cart) {
            var new_cart = cart.filter(function (item) {
                return item.variant_id != variant_id;
            });
            localStorage.setItem("cart", JSON.stringify(new_cart));
            display_cart();
        }
    }
});

// payment methods dic hide and show

$(function () {
    $(".transaction_id").hide();
    $(".payment_method_name").hide();
});

/* payment method selected event  */
$(".payment_method").on("click", function () {
    var payment_method = $(this).val();
    var exclude_txn_id = ["COD"];
    var include_payment_method_name = ["other"];

    if (exclude_txn_id.includes(payment_method)) {
        $(".transaction_id").hide();
    } else {
        $(".transaction_id").show();
    }

    if (include_payment_method_name.includes(payment_method)) {
        $(".payment_method_name").show();
    } else {
        $(".payment_method_name").hide();
    }
});

// cleart whole cart

$(document).on("click", ".clear_cart", function (e) {
    e.preventDefault();
    delete_cart_items();
});
$(document).on("click", ".clear_combo_cart", function (e) {
    e.preventDefault();
    delete_combo_cart_items();
});

function delete_cart_items() {
    var cart = localStorage.getItem("cart");

    cart = cart !== null && cart !== undefined ? JSON.parse(cart) : [];

    // var session_user_id = 2;

    // Filter out items with a matching seller_id and session_user_id
    var updatedCart = cart.filter((item) => item.seller_id !== session_user_id);

    // Save the updated cart back to localStorage
    localStorage.setItem("cart", JSON.stringify(updatedCart));

    // iziToast.success({
    //     message: "Cart Clear Successfully!!",
    //     position: "topRight",
    // });

    display_cart();
}
function delete_combo_cart_items() {
    var cart = localStorage.getItem("combo_product_cart");

    cart = cart !== null && cart !== undefined ? JSON.parse(cart) : [];

    // var session_user_id = 2;

    // Filter out items with a matching seller_id and session_user_id
    var updatedCart = cart.filter((item) => item.seller_id !== session_user_id);

    // Save the updated cart back to localStorage
    localStorage.setItem("combo_product_cart", JSON.stringify(updatedCart));
    display_combo_cart();
}

// get selected user id

var pos_user_id = 0;
$("#select_user_id").on("change", function () {
    pos_user_id = $("#select_user_id").val();
});
var combo_user_id = 0;
$("#select_combo_user_id").on("change", function () {
    combo_user_id = $("#select_combo_user_id").val();
});

function show_message(prefix = "Great!", message, type = "success") {
    Swal.fire(prefix, message, type);
}

// place order

$("#pos_form").on("submit", function (e) {
    e.preventDefault();
    if (confirm("Are you sure? Want to check out.")) {
        var cart = localStorage.getItem("cart");
        if (cart == null || !cart) {
            var message = "Please add items to the cart";
            show_message("Oops!", message, "error");
            return;
        }

        cart = cart !== null ? JSON.parse(cart) : [];

        // Filter out items with a matching seller_id and session_user_id
        var filtered_cart = cart.filter(
            (item) => item.seller_id == session_user_id
        );

        var address_id = $("#product_address_id").val();
        var address = $("#product_customer_address").val();
        var delivery_charges = $(".delivery_charge_service").val() || "";
        var discount = $(".discount_service").val() || "";
        var payment_method = $(".payment_method:checked").val();
        var self_pickup =
            $(".self_pickup:checked").length > 0
                ? $(".self_pickup:checked").val()
                : 0;
        var txn_id = $("#transaction_id").val();
        var payment_method_name = $("#payment_method_name").val() || "";

        const request_body = {
            _token: $('meta[name="csrf-token"]').attr("content"),
            data: JSON.stringify(cart),
            payment_method: payment_method,
            self_pickup: self_pickup,
            user_id: pos_user_id,
            address_id: address_id,
            address: address,
            txn_id: txn_id,
            delivery_charges: delivery_charges,
            discount: discount,
            payment_method_name: payment_method_name,
        };
        $.ajax({
            type: "POST",
            url: $(this).attr("action"),
            data: request_body,
            dataType: "json",
            success: function (result) {
                if (result.error == true) {
                    iziToast.error({
                        message: "<span>" + result.message + "</span> ",
                        position: "topRight",
                    });
                } else {
                    iziToast.success({
                        message:
                            '<span style="text-transform:capitalize">' +
                            result.message +
                            "</span> ",
                        position: "topRight",
                    });

                    delete_cart_items();
                    setTimeout(function () {
                        // Redirect to the appropriate URL
                        window.location.reload();
                    }, 3000);
                }
            },
        });
    }
});

$("#combo_product_form").on("submit", function (e) {
    e.preventDefault();
    if (confirm("Are you sure you want to check out?")) {
        var cart = localStorage.getItem("combo_product_cart");
        if (cart == null || !cart) {
            var message = "Please add items to the cart";
            show_message("Oops!", message, "error");
            return;
        }
        cart = cart !== null ? JSON.parse(cart) : [];

        // Filter out items with a matching seller_id and session_user_id
        var filtered_cart = cart.filter(
            (item) => item.seller_id == session_user_id
        );

        if (filtered_cart.length === 0) {
            var message =
                "There are no items matching your seller ID in the cart.";
            show_message("Oops!", message, "error");
            return;
        }
        var combo_user_id = 0;
        combo_user_id = $("#select_combo_user_id").val();

        var address_id = $("#combo_address_id").val();
        var address = $("#combo_customer_address").val();
        var delivery_charges = $(".combo_delivery_charge_service").val() || "";
        var discount = $(".combo_discount_service").val() || "";
        var payment_method = $(".payment_method:checked").val();
        var sub_total = $(".combo_hidden_sub_total").val();
        var final_total = $("#combo_final_total").text();
        var self_pickup =
            $(".self_pickup:checked").length > 0
                ? $(".self_pickup:checked").val()
                : 0;
        var txn_id = $("#combo_transaction_id").val();
        var payment_method_name = $("#combo_payment_method_name").val() || "";

        const request_body = {
            _token: $('meta[name="csrf-token"]').attr("content"),
            data: JSON.stringify(filtered_cart),
            payment_method: payment_method,
            self_pickup: self_pickup,
            user_id: combo_user_id,
            address_id: address_id,
            address: address,
            txn_id: txn_id,
            delivery_charges: delivery_charges,
            discount: discount,
            payment_method_name: payment_method_name,
            sub_total: sub_total,
            final_total: final_total,
        };

        // return false;
        $.ajax({
            type: "POST",
            url: $(this).attr("action"),
            data: request_body,
            dataType: "json",
            success: function (result) {
                "_token", $('meta[name="csrf-token"]').attr("content");
                if (result.error == true) {
                    iziToast.error({
                        message: "<span>" + result.message + "</span> ",
                        position: "topRight",
                    });
                } else {
                    iziToast.success({
                        message:
                            '<span style="text-transform:capitalize">' +
                            result.message +
                            "</span> ",
                        position: "topRight",
                    });
                    delete_combo_cart_items();
                    setTimeout(function () {
                        location.reload();
                    }, 600);
                }
            },
        });
    }
});

$(document).on("click", ".product-variants", function (e) {
    e.preventDefault();
    var product_id = $(this).data("product-id");

    $.ajax({
        type: "POST",
        url: appUrl + "seller/point_of_sale/get_poduct_variants",
        dataType: "json",
        data: {
            product_id: product_id,
            _token: $('meta[name="csrf-token"]').attr("content"),
        },

        success: function (result) {
            token = $('meta[name="csrf-token"]').attr("content");
            if (result.error == false) {
                var display_variant = "";
                var variants = result.data;

                var currency = $(".cart-total-price").attr("data-currency");

                display_variant =
                    '<div class="align-items-center d-flex product-detail">' +
                    '<div class="me-2">' +
                    '<img src="' +
                    result.data[0].product_image +
                    '" class="table-image item-image">' +
                    "</div>" +
                    '<span class="shop-item-title">' +
                    result.data[0].product_name +
                    "</span>" +
                    "</div>" +
                    "<div>";

                for (var j = 0; j < variants.length; j++) {
                    var product_special_price =
                        variants[j]["special_price"] > 0
                            ? variants[j]["special_price"]
                            : variants[j]["price"];
                    var variant_values = variants[j]["variant_values"]
                        ? variants[j]["variant_values"]
                        : "";

                    var variant_values_array = variant_values.split(",");

                    var variant_price =
                        variants[j]["special_price"] > 0
                            ? variants[j]["special_price"]
                            : variants[j]["price"];

                    display_variant +=
                        '<div class="variant-details d-flex justify-content-between mt-5 align-items-center">';

                    for (var k = 0; k < variant_values_array.length; k++) {
                        display_variant +=
                            "<span>" + variant_values_array[k] + "</span>";
                    }

                    var price =
                        variants[j]["stock_type"] == ""
                            ? '<span class="variant-price">' +
                              currency +
                              " " +
                              parseFloat(variant_price).toLocaleString() +
                              "</span>"
                            : (variants[j]["availability"] === 0 ||
                                  variants[j]["availability"] === 1) &&
                              variants[j]["stock"] > 0
                            ? '<span class="variant-price">' +
                              currency +
                              " " +
                              parseFloat(variant_price).toLocaleString() +
                              "</span>"
                            : '<span class="text-danger">Out of Stock</span>';

                    var isDisabled =
                        variants[j]["stock_type"] == ""
                            ? ""
                            : (variants[j]["availability"] === 0 ||
                                  variants[j]["availability"] === 1) &&
                              variants[j]["stock"] > 0
                            ? ""
                            : "disabled";

                    display_variant +=
                        price +
                        '<button type="button" class="btn btn-primary shop-item-button ' +
                        isDisabled +
                        ' " data-price="' +
                        product_special_price +
                        '" data-variant_id="' +
                        variants[j]["id"] +
                        '" data-variant_value="' +
                        variants[j]["variant_values"] +
                        '" data-special_price="' +
                        variants[j]["special_price"] +
                        '" data-variants_price="' +
                        variants[j]["price"] +
                        '" data-product_type="' +
                        "regular" +
                        '" onclick="add_to_cart(event)">Add</button>' +
                        "</div>" +
                        "</div>";
                }
                $(".pos-variant-detail").html(display_variant);

                $("#product-variants-modal").modal("show");
            } else {
                iziToast.error({
                    message: "<span>" + result.message + "</span> ",
                    position: "topRight",
                });
            }
        },
    });
});

$(function () {
    $(".delivery_charge_service").on("input", function () {
        // Get the input value
        var inputValue = $(this).val();
        var currency = $("#cart-total-price").attr("data-currency");
        // Update the content of the table cell
        $(".delivery_charge").text(currency + inputValue);
    });

    $(".discount_service").on("input", function () {
        // Get the input value
        var inputValue = $(this).val();
        var currency = $("#cart-total-price").attr("data-currency");

        // Update the content of the table cell
        $(".discount_amount").text("-" + " " + currency + inputValue);
    });

    // for combo products
    $(".combo_delivery_charge_service").on("input", function () {
        // Get the input value
        var inputValue = $(this).val();
        var currency = $("#cart-total-price").attr("data-currency");
        // Update the content of the table cell
        $(".combo_delivery_charge").text(currency + inputValue);
    });

    $(".combo_discount_service").on("input", function () {
        // Get the input value
        var inputValue = $(this).val();
        var currency = $("#cart-total-price").attr("data-currency");

        // Update the content of the table cell
        $(".combo_discount_amount").text("-" + " " + currency + inputValue);
    });
});

$(".product_pay_now").on("click", function () {
    // Retrieve values from DOM elements
    var payment_method = $(".payment_method").val();
    var payment_method_name = $("#payment_method_name").val();
    var transaction_id = $("#transaction_id").val();

    // Set retrieved values to hidden input fields
    $('input[name="payment_method"]').val(payment_method);
    $('input[name="payment_method_name"]').val(payment_method_name);
    $('input[name="transaction_id"]').val(transaction_id);

    // Trigger a click event on another element with class "place_order_btn"
    $(".place_order_btn").trigger("click");

    // After 1 second (1000 milliseconds), hide an element with ID "cart_product_payment"

    // setTimeout(function () {
    //     $("#cart_product_payment").hide();
    // }, 1000);
});

$("#combo_product_pay_now").on("click", function () {
    var payment_method = $(".payment_method").val();
    var payment_method_name = $("#combo_cart_payment_method_name").val();
    var transaction_id = $("#combo_cart_transaction_id").val();
    $("#combo_payment_method").val(payment_method);
    $("#combo_payment_method_name").val(payment_method_name);
    $("#combo_transaction_id").val(transaction_id);

    $("#combo_place_order_btn").trigger("click");
});

$(".pos-nav-tab-link").on("click", function () {
    var curent_tab = $(this).data("tab");
    if (curent_tab == "product_tab") {
        $(".pos-product-cart-detail").removeClass("d-none");
        $(".pos-combo-product-cart-detail").addClass("d-none");
        $(".combo-product-search").addClass("d-none");
        $(".product-search").removeClass("d-none");
        $("#product_categories").removeClass("d-none");
    } else {
        $(".combo-product-search").removeClass("d-none");
        $(".product-search").addClass("d-none");
        $(".pos-product-cart-detail").addClass("d-none");
        $(".pos-combo-product-cart-detail").removeClass("d-none");
        $("#product_categories").addClass("d-none");
    }
});
$(document).on("click", ".customer_edit_address", function (e) {
    var pos_user_id = $("#select_user_id").val(); // Use var or let to declare pos_user_id locally

    if (pos_user_id !== null && pos_user_id.trim() !== "") {
        $.ajax({
            url: appUrl + "seller/point_of_sale/getCustomerAddress",
            dataType: "json",
            method: "POST",
            data: {
                pos_user_id: pos_user_id,
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                // Handle success response from server
                // console.log(response);
                $(".address_id").val(response.data.address_id);
                $(".customer_name").val(response.data.user_name);
                $(".customer_mobile").val(response.data.mobile);
                $(".customer_state").val(response.data.state);
                $(".customer_country").val(response.data.country);
                // $(".customer_zipcode").val(response.data.user_name);
                $(".customer_city").val(response.data.city);
                $(".customer_address").html(response.data.address);
            },
            error: function (xhr, status, error) {
                // Handle error response
                console.error("AJAX request error:", error);
            },
        });
    }
});

$(document).on("click", ".combo_customer_edit_address", function (e) {
    var pos_user_id = $("#select_combo_user_id").val(); // Use var or let to declare pos_user_id locally

    if (pos_user_id !== null && pos_user_id.trim() !== "") {
        $.ajax({
            url: appUrl + "seller/point_of_sale/getCustomerAddress",
            dataType: "json",
            method: "POST",
            data: {
                pos_user_id: pos_user_id,
                _token: $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                // Handle success response from server

                $(".combo_address_id").val(response.data.address_id);
                $(".combo_customer_name").val(response.data.user_name);
                $(".combo_customer_mobile").val(response.data.mobile);
                $(".combo_customer_state").val(response.data.state);
                $(".combo_customer_country").val(response.data.country);
                // $(".customer_zipcode").val(response.data.user_name);
                $(".combo_customer_city").val(response.data.city);
                $(".combo_customer_address").html(response.data.address);
            },
            error: function (xhr, status, error) {
                // Handle error response
                console.error("AJAX request error:", error);
            },
        });
    }
});

$("#discount_service").on("keyup", function () {
    var cartPrice = parseFloat($(".main_cart_total").val().replace(/,/g, "")); // Remove commas and convert to float
    // console.log(cartPrice);

    var discountValue = parseFloat($(this).val()); // Get discount value entered

    // Check if discount value is greater than cart price
    if (discountValue > cartPrice) {
        var currency = $("#cart-total-price").attr("data-currency");
        $(".discount_amount").text("-" + " " + currency + 0);
        $(".discount_service").val("");
        iziToast.error({
            message: "Discount value is not greater than total price!!",
            position: "topRight",
        });
    }
});

$("#combo_discount_service").on("keyup", function () {
    var cartPrice = parseFloat($("#combo_final_total").val().replace(/,/g, ""));

    // console.log(cartPrice);
    var discountValue = parseFloat($(this).val()); // Get discount value entered

    // Check if discount value is greater than cart price
    if (discountValue > cartPrice) {
        var currency = $("#cart-total-price").attr("data-currency");
        $(".combo_discount_amount").text("-" + " " + currency + 0);
        $(".combo_discount_service").val("");
        iziToast.error({
            message: "Discount value is not greater then total price!!",
            position: "topRight",
        });
    }
});
