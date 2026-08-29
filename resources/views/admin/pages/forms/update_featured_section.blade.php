@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.update_featured_section', 'Update Featured Section') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.update_featured_section', 'Update Featured Section')" :subtitle="labels(
        'admin_labels.showcase_top_picks_with_effortless_featured_item_management',
        'Showcase Top Picks with Effortless Featured Item Management',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.featured_section', 'Featured Section'), 'url' => route('feature_section.index')],
        ['label' => labels('admin_labels.update_featured_section', 'Update Featured Section')],
    ]" />
    @php
        use App\Services\MediaService;
        $selectedCategoryIds = !empty($data->categories) ? explode(',', $data->categories) : [];
    @endphp
    <form class="form-horizontal form-submit-event submit_form" action="{{ route('feature_section.update', $data->id) }}"
        method="POST" id="" enctype="multipart/form-data">
        @method('PUT')
        @csrf
        <div class="row">
            <div class="col-md-12 col-xxl-6">
                <div class="card">
                    <div class="card-body ">
                        <h5 class="mb-3">
                            {{ labels('admin_labels.manage_featured_section', 'Manage Featured Section') }}
                        </h5>
                        <ul class="nav nav-tabs" id="brandTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="language-nav-link nav-link active" id="tab-en" data-bs-toggle="tab"
                                    data-bs-target="#content-en" type="button" role="tab" aria-controls="content-en"
                                    aria-selected="true">
                                    {{ labels('admin_labels.default', 'Default') }}
                                </button>
                            </li>
                            <x-language.multi_language_tabs :languages="$languages" />
                        </ul>

                        <div class="tab-content mt-3" id="brandTabsContent">
                            <div class="tab-pane fade show active" id="content-en" role="tabpanel" aria-labelledby="tab-en">
                                <div class="mb-3">
                                    <label for="title" class="form-label">
                                        {{ labels('admin_labels.title', 'Title') }}<span
                                            class="text-asterisks text-sm">*</span>
                                    </label>
                                    <input type="text" placeholder="Best Deals" name="title" class="form-control"
                                        value="{{ isset($data->title) ? json_decode($data->title)->en : '' }}">
                                </div>
                                <div class="col-md-12">
                                    <label for="short_description" class="control-label mb-2 mt-2">
                                        {{ labels('admin_labels.short_description', 'Short Description') }}
                                        <span class='text-asterisks text-sm'>*</span>
                                    </label>
                                    <input type="text" class="form-control" name="short_description"
                                        id="short_description"
                                        value="{{ isset($data->short_description) ? json_decode($data->short_description)->en : '' }}"
                                        placeholder="Short description">
                                </div>
                            </div>

                            <x-language.multi_language_updateable_inputs :languages="$languages" :data="$data->title"
                                nameKey="admin_labels.title" nameValue="Title" inputName="translated_featured_section_title" />
                            <x-language.multi_language_updateable_inputs :languages="$languages" :data="$data->short_description"
                                nameKey="admin_labels.short_description" nameValue="Short Description"
                                inputName="translated_featured_section_description" />
                        </div>
                        <div class="form-group row select-categories {{ in_array($data->product_type, ['custom_products', 'custom_combo_products', 'digital_product']) ? 'd-none' : '' }}">
                            <label for="categories"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.categories', 'Categories') }}</label>
                            <div class="col-md-12">
                                <select name="categories[]" id="category_sliders_category"
                                    class="category_sliders_category w-100" multiple
                                    data-placeholder=" Type to search and select categories" onload="multiselect()">
                                    @foreach ($categories as $category)
                                        @if (in_array($category->id, $selectedCategoryIds))
                                            <option value="{{ $category->id }}" selected>
                                                {{ json_decode($category->name)->en ?? '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            @php
                                $product_type = [
                                    'new_added_products',
                                    'products_on_sale',
                                    'top_rated_products',
                                    'most_selling_products',
                                    'custom_products',
                                    'digital_product',
                                    'custom_combo_products',
                                ];
                            @endphp

                            <label for="product_type" class="control-label mb-2 mt-2">
                                {{ labels('admin_labels.product_type', 'Product Types') }}
                                <span class='text-danger text-sm'>* </span>
                            </label>

                            <div class="col-md-12">
                                <select name="product_type" class="form-control product_type form-select">
                                    <option value=" ">
                                        {{ labels('admin_labels.select_type', 'Select Type') }}
                                    </option>
                                    @foreach ($product_type as $row)
                                        <option value="{{ $row }}" @selected($data->product_type == $row)>
                                            {{ ucwords(str_replace('_', ' ', $row)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>


                        <!-- for custom product -->

                        <div class="form-group row custom_products {{ $data->product_type == 'custom_products' ? '' : 'd-none' }}">
                            <label for="product_ids"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.products', 'Products') }}
                                <span class='text-danger text-sm'>
                                    * </span></label>
                            <div class="col-md-12 search_admin_product_parent">
                                <select name="product_ids[]" class="search_admin_product w-100" multiple
                                    data-placeholder=" Type to search and select products" onload="multiselect()">
                                    @if ($data->product_type == 'custom_products')
                                        @foreach ($product_details as $product)
                                            <option value="{{ $product['id'] }}" selected>
                                                {{ json_decode($product['name'])->en ?? '' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        <!-- for custom combo product -->

                        <div class="form-group row custom_combo_products {{ $data->product_type == 'custom_combo_products' ? '' : 'd-none' }}">
                            <label for="product_ids"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.combo_products', 'Combo Products') }}
                                <span class='text-danger text-sm'>
                                    * </span></label>
                            <div class="col-md-12">
                                <select name="product_ids[]" class="search_admin_combo_product w-100" multiple
                                    data-placeholder=" Type to search and select products" onload="multiselect()">
                                    @if ($data->product_type == 'custom_combo_products')
                                        @foreach ($combo_product_details as $comboProduct)
                                            <option value="{{ $comboProduct['id'] }}" selected>
                                                {{ json_decode($comboProduct['title'])->en ?? '' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <!-- for digital product -->
                        <div class="form-group row digital_products {{ $data->product_type == 'digital_product' ? '' : 'd-none' }}">
                            <label for="digital_product_ids"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.products', 'Products') }}
                                *</label>
                            <div class="col-md-12">
                                <select name="digital_product_ids[]" class="search_admin_digital_product w-100" multiple
                                    data-placeholder=" Type to search and select products" onload="multiselect()">
                                    @if ($data->product_type == 'digital_product')
                                        @foreach ($product_details as $product)
                                            <option value="{{ $product['id'] }}" selected>
                                                {{ json_decode($product['name'])->en ?? '' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md-12 mt-2 mb-4">
                            <label for="image"
                                class="mb-2">{{ labels('admin_labels.banner_image', 'Banner Image') }}
                                <span class='text-asterisks text-sm'>*</span>
                            </label>

                            <div class="col-md-12">
                                <div class="row form-group">
                                    <div class="col-md-6 file_upload_box border file_upload_border mt-2">
                                        <div class="mt-2">
                                            <div class="col-md-12  text-center">
                                                <div>
                                                    <a class="media_link" data-input="banner_image" data-isremovable="0"
                                                        data-is-multiple-uploads-allowed="0" data-bs-toggle="modal"
                                                        data-bs-target="#media-upload-modal" value="Upload Photo">
                                                        <h4><i class='bx bx-upload'></i> Upload
                                                    </a></h4>
                                                    <p class="image_recommendation">Recommended Size: 1648 x 610 pixels</p>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 container-fluid row mt-3 image-upload-section">
                                        <div
                                            class="col-md-12 col-sm-12 p-3 mb-5 bg-white rounded m-4 text-center grow image">
                                            @if (!empty($data->banner_image))
                                                <img src="{{ app(MediaService::class)->getMediaImageUrl($data->banner_image) }}"
                                                    class="img-fluid rounded" alt="">
                                                <input type="hidden" name="banner_image"
                                                    value="{{ $data->banner_image }}">
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12 col-xxl-6 mt-md-2 mt-sm-2">
                <div class="card">
                    <div class="card-body ">
                        <h5 class="mb-3">
                            {{ labels('admin_labels.select_style', 'Select Style') }}
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="feature_section_color_picker"
                                        class="d-block">{{ labels('admin_labels.choose_background_color', 'Choose Background Color') }}</label>
                                    <input type="color" value="{{ $data->background_color ?? '#e0ffee' }}"
                                        id="feature_section_color_picker"
                                        onchange="updateColorCode('feature_section_color_picker')"
                                        class="form-control d-block mx-auto">
                                </div>
                            </div>
                            <div class="col-md-6 mt-4 mb-2">
                                <div class="form-group">
                                    <input type="text" id="feature_section_color_picker_code" name="background_color"
                                        class="form-control d-block mx-auto"
                                        value="{{ $data->background_color ?? '' }}"
                                        oninput="updateColorPicker('feature_section_color_picker', this.value)">
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label" for="category_style_select">
                                        {{ labels('admin_labels.style', 'Select Section Header Style') }}
                                    </label>
                                    <select class="feature_section_header_style form-control form-select"
                                        name="header_style">
                                        <option value="header_style_1" @selected($data->header_style == 'header_style_1')>Style 1</option>
                                        <option value="header_style_2" @selected($data->header_style == 'header_style_2')>Style 2</option>
                                        <option value="header_style_3" @selected($data->header_style == 'header_style_3')>Style 3</option>
                                    </select>
                                </div>

                                <div class="feature_section_header_style_images feature_section_header_style_box">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/feature_section_heading_style_1.png') }}"
                                        class="header_style_1" alt="Feature Section Heading Style 1">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/feature_section_heading_style_2.png') }}"
                                        class="header_style_2" alt="Feature Section Heading Style 2">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/feature_section_heading_style_3.png') }}"
                                        class="header_style_3" alt="Feature Section Heading Style 3">

                                </div>
                            </div>

                            <div class="col-md-6 feature_section_style_main">
                                <div class="mb-4">
                                    <label class="form-label" for="category_style_select">
                                        {{ labels('admin_labels.style', 'Select Section Style') }}
                                    </label>
                                    <select class="feature_section_style form-control form-select" name="style">
                                        <option value="style_1" @selected($data->style == 'style_1')>Style 1</option>
                                        <option value="style_2" @selected($data->style == 'style_2')>Style 2</option>
                                        <option value="style_3" @selected($data->style == 'style_3')>Style 3</option>
                                    </select>
                                </div>

                                <div class="feature_section_style_images feature_section_style_box">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/featured_section_style_1.png') }}"
                                        class="style_1" alt="Featured Section Style 1">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/featured_section_style_2.png') }}"
                                        class="style_2" alt="Featured Section Style 2">
                                    <img src="{{ app(MediaService::class)->getImageUrl('system_images/featured_section_style_3.png') }}"
                                        class="style_3" alt="Featured Section Style 3">

                                </div>
                            </div>
                        </div>


                        <div class="d-flex justify-content-end">
                            <button type="reset"
                                class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                            <button type="submit"
                                class="btn btn-primary submit_button">{{ labels('admin_labels.update_featured_section', 'Update Featured Section') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
