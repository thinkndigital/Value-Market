@extends('admin/layout')
@section('title')
{{ labels('admin_labels.update_store', 'Update Store') }}
@endsection
@section('content')
<?php
$store_settings = json_decode($data->store_settings);
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <form action="{{ url('/admin/store/update/' . $data->id) }}" enctype="multipart/form-data" method="POST" class="submit_form">
                @method('PUT')
                @csrf
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Store Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">

                                <div class="row">
                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="basic-default-fullname">{{ labels('admin_labels.name', 'Name') }}<span class='text-asterisks text-sm'>*</span></label>
                                        <input type="text" class="form-control" id="basic-default-fullname" placeholder="Electronics Store" name="name" value="{{ $data->name }}">

                                    </div>
                                    <div class="mb-3 col-md-6">
                                        <label class="form-label" for="basic-default-fullname">{{ labels('admin_labels.description', 'Description') }}<span class='text-asterisks text-sm'>*</span></label>
                                        <textarea class="form-control" id="short_description" placeholder="Electronics Store" name="description">{{ $data->description }}</textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="image">{{ labels('admin_labels.image', 'Image') }}
                                                <span class='text-asterisks text-sm'>*</span><small>(Recommended Size
                                                    :
                                                    131 x
                                                    131
                                                    pixels)</small></label>
                                            <div class="col-sm-12">
                                                <div class='col-md-3'>
                                                    <label for="file-upload" class="btn btn-outline-secondary">
                                                        <i class="fa fa-cloud-upload"></i> Upload Image
                                                    </label>
                                                    <input type="file" accept="image/*" id="file-upload" name="image" style="display: none;" />
                                                </div>
                                                <?php
                                                if (($data->image) && !empty($data->image)) {
                                                ?>
                                                    <label for="" class="text-danger mt-3">*Only Choose When Update is
                                                        necessary</label>
                                                    <div class="container-fluid row image-upload-section">
                                                        <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image">
                                                            <div class='image-upload-div'><img class="img-fluid mb-2" src="{{ asset('storage/' . $data->image) }}" alt="Not Found"></div>
                                                            <input type="hidden" name="image" value='<?= $data->image ?>'>
                                                        </div>
                                                    </div>
                                                <?php
                                                } ?>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="form-group">
                                                <label for="image">Banner <span class='text-asterisks text-sm'>*</span></label>
                                                <div class="col-sm-12">
                                                    <div class='col-md-3'>
                                                        <label for="image-file-upload" class="btn btn-outline-secondary">
                                                            <i class="fa fa-cloud-upload"></i> Upload Image
                                                        </label>
                                                        <input type="file" accept="image/*" id="image-file-upload" name="banner_image" style="display: none;" />
                                                    </div>
                                                    <?php
                                                    if (($data->banner_image) && !empty($data->banner_image)) {
                                                    ?>
                                                        <label for="" class="text-danger mt-3">*Only Choose When Update is
                                                            necessary</label>
                                                        <div class="container-fluid row image-upload-section">
                                                            <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image">
                                                                <div class='image-upload-div'><img class="img-fluid mb-2" src="{{ asset('storage/' . $data->banner_image) }}" alt="Not Found"></div>
                                                                <input type="hidden" name="banner_image" value='<?= $data->banner_image ?>'>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="image">Single Seller Order System?<span class='text-danger text-sm'></span><small> (For Cart)
                                            </small></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_single_seller_order_system" name="is_single_seller_order_system" <?= isset($data->is_single_seller_order_system) && $data->is_single_seller_order_system == '1' ? 'Checked' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="image">Is Default Store?<span class='text-danger text-sm'></span></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_default_store" name="is_default_store" <?= isset($data->is_default_store) && $data->is_default_store == '1' ? 'Checked' : '' ?> </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="color_picker" class="d-block text-center">Choose a Theme
                                                Primary Color</label>
                                            <input type="color" id="light_theme_color_picker" oninput=updateColorCode() class="form-control d-block mx-auto" value={{ isset($data->primary_color) && !empty($data->primary_color) ? $data->primary_color : '' }}>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="light_theme_color_code" name="primary_color" class="form-control d-block mx-auto" value={{ !empty($data->primary_color) ? $data->primary_color : '' }}>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="color_picker" class="d-block text-center">Choose a Theme
                                                Secondary Color</label>
                                            <input type="color" id="dark_theme_color_picker" oninput=updateColorCode() class="form-control d-block mx-auto" value={{ isset($data->secondary_color) && !empty($data->secondary_color) ? $data->secondary_color : '' }}>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="dark_theme_color_code" name="secondary_color" class="form-control d-block mx-auto" value={{ !empty($data->secondary_color) ? $data->secondary_color : '' }}>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="color_picker" class="d-block text-center">Choose Link Hover
                                                Color</label>
                                            <input type="color" id="hover_color" oninput=updateColorCode() class="form-control d-block mx-auto" value={{ isset($data->hover_color) && !empty($data->hover_color) ? $data->hover_color : '' }}>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="hover_color_code" name="hover_color" class="form-control d-block mx-auto" value={{ !empty($data->hover_color) ? $data->hover_color : '' }}>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="color_picker" class="d-block text-center">Choose Link Active
                                                Color</label>
                                            <input type="color" id="active_color" oninput=updateColorCode() class="form-control d-block mx-auto" value={{ isset($data->active_color) && !empty($data->active_color) ? $data->active_color : '' }}>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="active_color_code" name="active_color" class="form-control d-block mx-auto" value={{ !empty($data->active_color) ? $data->active_color : '' }}>
                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            App Setting
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="on_boarding_image">On Boarding Image <span class='text-asterisks text-sm'>*</span></label>
                                            <div class="col-sm-10">
                                                <input type="file" accept="image/*" class="form-control phone-mask" id="file-upload" name="on_boarding_image" />

                                                <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image product-image-container">
                                                    <div class='image-upload-div'>
                                                        <img class="img-fluid mb-2" src={{ getMediaImageUrl($data->on_boarding_image) }} alt="Not Found" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="on_boarding_video">On Boarding Video <span class='text-asterisks text-sm'>*</span></label>
                                            <div class="col-sm-10">
                                                <input type="file" class="form-control phone-mask" id="on_boarding_video" name="on_boarding_video">
                                                <video class="app_setting_image_box" controls>
                                                    <source src="{{ getimageurl($data->on_boarding_video) }}" type="video/mp4">
                                                </video>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="banner_image_for_most_selling_product">Banner
                                                Image(For
                                                Most
                                                Selling Products) <span class='text-asterisks text-sm'>*</span></label>
                                            <div class="col-sm-10">
                                                <input type="file" accept="image/*" id="basic-default-phone" name="banner_image_for_most_selling_product" class="form-control phone-mask">

                                                <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image product-image-container">
                                                    <div class='image-upload-div'>
                                                        <img src="{{ getimageurl($data->banner_image_for_most_selling_product) }}" alt="user-avatar" class="d-block rounded mt-4" id="uploadedAvatar" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="banner_image_for_most_selling_product">Stack Image(App Home Page
                                            Background Image) <span class='text-asterisks text-sm'>*</span></label>
                                        <div class="col-sm-10">
                                            <input type="file" accept="image/*" id="basic-default-phone" name="stack_image" class="form-control phone-mask">

                                            <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image product-image-container">
                                                <div class='image-upload-div'>
                                                    <img src="{{ getimageurl($data->stack_image) }}" alt="user-avatar" class="d-block rounded mt-4" id="uploadedAvatar" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="banner_image_for_most_selling_product">Login Page Image(App Login
                                            Page Background Image) <span class='text-asterisks text-sm'>*</span></label>
                                        <div class="col-sm-10">
                                            <input type="file" accept="image/*" id="basic-default-phone" name="login_image" class="form-control phone-mask">
                                            <div class="col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image product-image-container">
                                                <div class='image-upload-div'>
                                                    <img class="img-fluid mb-2" src={{ getimageurl($data->login_image) }} alt="Not Found" />
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Sellers & Products Display Style
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">
                                    <label class="form-label" for="store_style">{{ labels('admin_labels.store_display_style', 'Stores display style') }}</label>
                                    <ul class="list-unstyled">
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_1" name="store_style" id="store_style_1" class="radio-input d-none" {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_1' ? 'checked' : '' }} />
                                            <label class="store_style_box {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_1' ? 'active' : '' }}" for="store_style_1">
                                                <img src="<?php echo getimageurl('system_images/store_style_1.png'); ?>" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_2" name="store_style" id="store_style_2" class="radio-input d-none" {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_2' ? 'checked' : '' }} />
                                            <label class="store_style_box {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_2' ? 'active' : '' }}" for="store_style_2">
                                                <img src="<?php echo getimageurl('system_images/store_style_2.png'); ?>" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_3" name="store_style" id="store_style_3" class="radio-input d-none" {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_3' ? 'checked' : '' }} />
                                            <label class="store_style_box {{ isset($store_settings->store_style) && $store_settings->store_style === 'style_3' ? 'active' : '' }}" for="store_style_3">
                                                <img src="<?php echo getimageurl('system_images/store_style_3.png'); ?>" />
                                            </label>
                                        </li>
                                    </ul>

                                </div>
                                <label class="form-label" for="product_style">{{ labels('admin_labels.product_display_style', 'Products display style') }}</label>
                                <ul class="list-unstyled">
                                    <li class="d-inline-block mx-4">
                                        <input type="radio" value="style_1" name="product_style" id="product_style_1" class="radio-input d-none" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_1' ? 'checked' : '' }} />
                                        <label class="product_style_box" for="product_style_1" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_1' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/product_card_style_1.png'); ?>" />
                                        </label>
                                    </li>
                                    <li class="d-inline-block mx-4">
                                        <input type="radio" value="style_2" name="product_style" id="product_style_2" class="radio-input d-none" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_2' ? 'checked' : '' }} />
                                        <label class="product_style_box" for="product_style_2" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_2' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/product_card_style_2.png'); ?>" />
                                        </label>
                                    </li>
                                    <li class="d-inline-block">
                                        <input type="radio" value="style_3" name="product_style" id="product_style_3" class="radio-input d-none" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_3' ? 'checked' : '' }} />
                                        <label class="product_style_box" for="product_style_3" {{ isset($store_settings->product_style) && $store_settings->product_style === 'style_3' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/product_card_style_3.png'); ?>" />
                                        </label>
                                    </li>
                                </ul>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Categories Display Style
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label" for="basic-default-fullname">{{ !trans()->has('admin_labels.title') ? 'Categories Section Title' : trans('admin_labels.title') }}<span class='text-asterisks text-sm'>*</span></label>
                                        <input type="text" class="form-control" id="basic-default-fullname" placeholder="Shop By Categories" name="category_section_title" value="{{ isset($store_settings->category_section_title) && $store_settings->category_section_title ? $store_settings->category_section_title : '' }}">

                                    </div>
                                    <label class="form-label" for="category_style">{{ !trans()->has('admin_labels.store_display_style') ? 'Categories display style' : trans('admin_labels.store_display_style') }}</label>
                                    <ul class="list-unstyled">
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_1" name="category_style" id="category_style_1" class="radio-input d-none" {{ isset($store_settings->category_style) && $store_settings->category_style === 'style_1' ? 'checked' : '' }} />
                                            <label class="category_style_box" for="category_style_1" {{ isset($store_settings->category_style) && $store_settings->category_style === 'style_1' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/categories_style_1.png'); ?>" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_2" name="category_style" id="category_style_2" class="radio-input d-none" {{ isset($store_settings->category_style) && $store_settings->category_style === 'style_2' ? 'checked' : '' }} />
                                            <label class="category_style_box" for="category_style_2" {{ isset($store_settings->category_style) && $store_settings->category_style === 'style_2' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/categories_style_2.png'); ?>" />
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                                <label class="form-label" for="category_card_style">{{ !trans()->has('admin_labels.product_display_style') ? 'Categories Cards Style' : trans('admin_labels.product_display_style') }}</label>
                                <ul class="list-unstyled">
                                    <li class="d-inline-block mx-4">
                                        <input type="radio" value="style_1" name="category_card_style" id="category_card_style_1" class="radio-input d-none" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_1' ? 'checked' : '' }} />
                                        <label class="category_card_box" for="category_card_style_1" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_1' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/categories_cards_style_1.jpg'); ?>" />
                                        </label>
                                    </li>
                                    <li class="d-inline-block mx-4">
                                        <input type="radio" value="style_2" name="category_card_style" id="category_card_style_2" class="radio-input d-none" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_2' ? 'checked' : '' }} />
                                        <label class="category_card_box" for="category_card_style_2" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_2' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/categories_cards_style_2.jpg'); ?>" />
                                        </label>
                                    </li>
                                    <li class="d-inline-block">
                                        <input type="radio" value="style_3" name="category_card_style" id="category_card_style_3" class="radio-input d-none" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_3' ? 'checked' : '' }} />
                                        <label class="category_card_box" for="category_card_style_3" {{ isset($store_settings->category_card_style) && $store_settings->category_card_style === 'style_3' ? 'active' : '' }}>
                                            <img src="<?php echo getimageurl('system_images/categories_cards_style_3.jpg'); ?>" />
                                        </label>
                                    </li>
                                </ul>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Brands Display Style
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">
                                    <ul class="list-unstyled">
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_1" name="brand_style" id="brand_style_1" class="radio-input d-none" {{ isset($store_settings->brand_style) && $store_settings->brand_style === 'style_1' ? 'checked' : '' }} />
                                            <label class="category_style_box" for="brand_style_1" {{ isset($store_settings->brand_style) && $store_settings->brand_style === 'style_1' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/brands_style_1.png'); ?>" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_2" name="brand_style" id="brand_style_2" class="radio-input d-none" {{ isset($store_settings->brand_style) && $store_settings->brand_style === 'style_2' ? 'checked' : '' }} />
                                            <label class="category_style_box" for="brand_style_2" {{ isset($store_settings->brand_style) && $store_settings->brand_style === 'style_2' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/brands_style_2.png'); ?>" />
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Offers Display Style
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">

                                    <ul class="list-unstyled">
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_1" name="offers_style" id="offers_style_1" class="radio-input d-none" {{ isset($store_settings->offers_style) && $store_settings->offers_style === 'style_1' ? 'checked' : '' }} />
                                            <label class="product_style_box" for="offers_style_1" {{ isset($store_settings->offers_style) && $store_settings->offers_style === 'style_1' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_style_1.png'); ?>" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_2" name="offers_style" id="offers_style_2" class="radio-input d-none" {{ isset($store_settings->offers_style) && $store_settings->offers_style === 'style_2' ? 'checked' : '' }} />
                                            <label class="store_style_box" for="offers_style_2" {{ isset($store_settings->offers_style) && $store_settings->offers_style === 'style_2' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_style_2.png'); ?>" />
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Offer Sliders Display Style
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="form-group">
                                <div class="row">
                                    <ul class="list-unstyled">
                                        <li class="d-inline-block mx-4">
                                            <input type="radio" value="style_1" name="offer_slider_style" id="offer_slider_style_1" class="radio-input d-none" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_1' ? 'checked' : '' }} />
                                            <label class="store_style_box" for="offer_slider_style_1" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_1' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_slider_section_style_1.png'); ?>" alt="Style 1" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block mx-4">
                                            <input type="radio" value="style_2" name="offer_slider_style" id="offer_slider_style_2" class="radio-input d-none" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_2' ? 'checked' : '' }} />
                                            <label class="store_style_box" for="offer_slider_style_2" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_2' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_slider_section_style_2.png'); ?>" alt="Style 2" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block mx-4">
                                            <input type="radio" value="style_2" name="offer_slider_style" id="offer_slider_style_3" class="radio-input d-none" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_3' ? 'checked' : '' }} />
                                            <label class="store_style_box" for="offer_slider_style_3" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_3' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_slider_section_style_3.png'); ?>" alt="Style 3" />
                                            </label>
                                        </li>
                                        <li class="d-inline-block">
                                            <input type="radio" value="style_2" name="offer_slider_style" id="offer_slider_style_4" class="radio-input d-none" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_4' ? 'checked' : '' }} />
                                            <label class="store_style_box" for="offer_slider_style_4" {{ isset($store_settings->offer_slider_style) && $store_settings->offer_slider_style === 'style_4' ? 'active' : '' }}>
                                                <img src="<?php echo getimageurl('system_images/offer_slider_section_style_4.png'); ?>" alt="Style 4" />
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <div class="form-group">
                            <button type="submit" class="btn btn-dark mt-4 me-2" id="">Update
                                Store</button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
    @endsection
