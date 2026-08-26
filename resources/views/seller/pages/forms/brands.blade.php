@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.brands', 'Brands') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('admin_labels.brands', 'Brands')" :subtitle="labels(
        'admin_labels.elevate_your_store_with_seamless_brand_management',
        'Elevate Your Store with Seamless Brand Management',
    )" :breadcrumbs="[['label' => labels('admin_labels.brands', 'Brands')]]" />

    <div class="col-md-12">
        <div class="row">
            <div class="col-md-12 col-xl-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">
                            {{ labels('admin_labels.add_brand', 'Add Brand') }}
                            <i class="mx-2 fa fa-info-circle text-secondary" data-bs-toggle="popover" data-bs-placement="right"
                                data-bs-content="You can request a brand to the admin, and the admin can approve it. You cannot add your own brand."></i>
                        </h5>
                    </div>
                    <form id="" action="{{ route('seller.brands.store') }}" class="submit_form"
                        enctype="multipart/form-data" method="POST">
                        @csrf
                        <div class="card-body pt-0">
                            <ul class="nav nav-tabs" id="brandTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="language-nav-link nav-link active" id="tab-en" data-bs-toggle="tab"
                                        data-bs-target="#content-en" type="button" role="tab"
                                        aria-controls="content-en" aria-selected="true">
                                        {{ labels('admin_labels.default', 'Default') }}
                                    </button>
                                </li>
                                <x-language.multi_language_tabs :languages="$languages" />
                            </ul>

                            <div class="tab-content mt-3" id="brandTabsContent">
                                <!-- Default 'en' tab content -->
                                <div class="tab-pane fade show active" id="content-en" role="tabpanel"
                                    aria-labelledby="tab-en">
                                    <div class="mb-3">
                                        <label for="brand_name"
                                            class="form-label">{{ labels('admin_labels.name', 'Name') }}<span
                                                class="text-asterisks text-sm">*</span></label>
                                        <input type="text" name="brand_name" class="form-control"
                                            placeholder="Brand Name" value="">
                                    </div>
                                </div>
                                <x-language.multi_language_inputs :languages="$languages" nameKey="admin_labels.name"
                                    nameValue="Name" inputName="translated_brand_name" />

                            </div>

                            <label for="" class="form-label">{{ labels('admin_labels.image', 'Image') }}<span
                                    class="text-asterisks text-sm">*</span></label>
                            <div class="col-md-12">
                                <div class="row form-group">
                                    <div class="col-md-6 file_upload_box border file_upload_border mt-4">
                                        <div class="mt-2">
                                            <div class="col-md-12  text-center">
                                                <div>
                                                    <a class="media_link" data-input="image" data-isremovable="0"
                                                        data-is-multiple-uploads-allowed="0" data-bs-toggle="modal"
                                                        data-bs-target="#media-upload-modal" value="Upload Photo">
                                                        <h4><i class='bx bx-upload'></i> Upload
                                                    </a></h4>
                                                    <p class="image_recommendation">Recommended Size: 180 x 180 pixels</p>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 container-fluid row mt-3 image-upload-section">
                                        <div
                                            class="col-md-12 col-sm-12 p-3 mb-5 bg-white rounded m-4 text-center grow image d-none">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="reset"
                                        class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                    <button type="submit"
                                        class="btn btn-primary submit_button">{{ labels('admin_labels.add_brand', 'Add Brand') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
