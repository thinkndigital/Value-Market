@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.update_category_slider', 'Update Category Slider') }}
@endsection
@section('content')
    @php
        use App\Services\MediaService;
        $selectedCategoryIds = !empty($data->category_ids) ? explode(',', $data->category_ids) : [];
    @endphp
    <x-admin.breadcrumb :title="labels('admin_labels.update_category_slider', 'Update Category Slider')" :subtitle="labels(
        'admin_labels.dynamic_category_display_with_seamless_slider_management',
        'Dynamic Category Display with Seamless Slider Management',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.categories', 'Categories'), 'url' => route('categories.index')],
        ['label' => labels('admin_labels.categories_sliders', 'Categories Sliders'), 'url' => route('category_slider.index')],
        ['label' => labels('admin_labels.update_category_slider', 'Update Category Slider')],
    ]" />

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">
                        {{ labels('admin_labels.update_category_slider', 'Update Category Slider') }}
                    </h5>
                    <div class="form-group">
                        <form id="" action="{{ url('category_sliders/update/' . $data->id) }}" class="submit_form"
                            enctype="multipart/form-data" method="POST">
                            @method('PUT')
                            @csrf
                            <ul class="nav nav-tabs" id="brandTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="language-nav-link nav-link active" id="tab-en"
                                        data-bs-toggle="tab" data-bs-target="#content-en" type="button" role="tab"
                                        aria-controls="content-en" aria-selected="true">
                                        {{ labels('admin_labels.default', 'Default') }}
                                    </button>
                                </li>
                                <x-language.multi_language_tabs :languages="$languages" />
                            </ul>

                            <div class="tab-content mt-3" id="UpdateCategorySliderTabsContent">
                                <div class="tab-pane fade show active" id="content-en" role="tabpanel"
                                    aria-labelledby="tab-en">
                                    <div class="mb-3">
                                        <label for="basic-default-fullname"
                                            class="form-label">{{ labels('admin_labels.title', 'Title') }}<span
                                                class="text-asterisks text-sm">*</span></label>
                                        <input type="text" class="form-control" id="basic-default-fullname"
                                            placeholder="Popular Categories" name="title"
                                            value="{{ isset($data->title) ? json_decode($data->title)->en : '' }}">
                                    </div>
                                </div>
                                <x-language.multi_language_updateable_inputs :languages="$languages" :data="$data->title"
                                    nameKey="admin_labels.title" nameValue="Title" inputName="translated_category_slider_title" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label"
                                    for="basic-default-fullname">{{ labels('admin_labels.select_category', 'Select Category') }}<span
                                        class='text-asterisks text-sm'>*</span></label>
                                <select name="category_ids[]" required id="category_sliders_category"
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

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_slider_color_picker" class="d-block mb-2">Choose
                                            Background Color<span class='text-asterisks text-sm'>*</span></label>
                                        <input type="color" value="{{ $data->background_color ?? '#e0ffee' }}"
                                            id="category_slider_color_picker" class="form-control d-block mx-auto"
                                            onchange="updateColorCode('category_slider_color_picker')">
                                    </div>
                                </div>
                                <div class="col-md-6 mt-4 mb-2">
                                    <div class="form-group">
                                        <input type="text" id="category_slider_color_picker_code"
                                            name="background_color" class="form-control d-block mx-auto"
                                            value="{{ $data->background_color ?? '' }}"
                                            oninput="updateColorPicker('category_slider_color_picker', this.value)">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="category_style_select">
                                    {{ labels('admin_labels.select_style', 'Select Slider Style') }}
                                </label>
                                <select class="category_slider_style form-select form-control"
                                    name="category_slider_style">
                                    <option value="style_1" @selected(($data->style ?? '') == 'style_1')>Style 1</option>
                                    <option value="style_2" @selected(($data->style ?? '') == 'style_2')>Style 2</option>
                                </select>
                            </div>

                            <div class="category_slider_style_images category_card_style_box">
                                <img src="{{ app(MediaService::class)->getImageUrl('system_images/category_slider_style_1.png') }}"
                                    alt="" class="style_1" />
                                <img src="{{ app(MediaService::class)->getImageUrl('system_images/category_slider_style_2.png') }}"
                                    alt="" class="style_2" />
                            </div>

                            <div class="row">
                                <label for=""
                                    class="form-label">{{ labels('admin_labels.banner_image', 'Banner Image') }}<span
                                        class="text-asterisks text-sm">*</span></label>
                                <div class="col-md-12">
                                    <div class="row form-group">
                                        <div class="col-md-6 file_upload_box border file_upload_border mt-2">
                                            <div class="mt-2">
                                                <div class="col-md-12  text-center">
                                                    <div>
                                                        <a class="media_link" data-input="banner_image"
                                                            data-isremovable="0"
                                                            data-is-multiple-uploads-allowed="0"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#media-upload-modal"
                                                            value="Upload Photo">
                                                            <h4><i class='bx bx-upload'></i> Upload
                                                        </a></h4>
                                                        <p class="image_recommendation">Recommended Size: 180 x 180
                                                            pixels</p>
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

                            <div class="d-flex justify-content-end">
                                <button type="reset"
                                    class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                <button type="submit"
                                    class="btn btn-primary submit_button">{{ labels('admin_labels.update_slider', 'Update Slider') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
