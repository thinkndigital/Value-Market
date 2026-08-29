@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.update_category', 'Update Blog Category') }}
@endsection
@section('content')
    @php
        use App\Services\MediaService;
    @endphp

    <x-admin.breadcrumb :title="labels('admin_labels.update_category', 'Update Blog Category')" :subtitle="labels(
        'admin_labels.organize_and_navigate_blog_content_with_ease',
        'Organize and Navigate Blog Content with Ease',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.blogs', 'Blogs')],
        ['label' => labels('admin_labels.categories', 'Categories')],
    ]" />

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">
                        {{ labels('admin_labels.update_category', 'Update Blog Category') }}
                    </h5>
                    <div class="form-group">
                        <form id="" action="{{ url('blog_category/update/' . $data->id) }}" class="submit_form"
                            enctype="multipart/form-data" method="POST">
                            @method('PUT')
                            @csrf
                            <div class="row">
                                <ul class="nav nav-tabs" id="brandTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="language-nav-link nav-link active" id="tab-en"
                                            data-bs-toggle="tab" data-bs-target="#content-en" type="button"
                                            role="tab" aria-controls="content-en" aria-selected="true">
                                            {{ labels('admin_labels.default', 'Default') }}
                                        </button>
                                    </li>
                                    <x-language.multi_language_tabs :languages="$languages" />
                                </ul>

                                <div class="tab-content mt-3" id="UpdateCategoryTabsContent">
                                    <div class="tab-pane fade show active" id="content-en" role="tabpanel"
                                        aria-labelledby="tab-en">
                                        <div class="mb-3">
                                            <label for="category_name"
                                                class="form-label">{{ labels('admin_labels.name', 'Name') }}<span
                                                    class="text-asterisks text-sm">*</span></label>
                                            <input type="text" name="name" class="form-control" placeholder="Name"
                                                value="{{ isset($data->name) ? json_decode($data->name)->en : '' }}">
                                        </div>
                                    </div>
                                    <x-language.multi_language_updateable_inputs :languages="$languages" :data="$data->name"
                                        nameKey="admin_labels.name" nameValue="Name" inputName="translated_category_name" />
                                </div>
                                <div class="col-md-12">
                                    <label for=""
                                        class="form-label">{{ labels('admin_labels.image', 'Image') }}<span
                                            class="text-asterisks text-sm">*</span></label>
                                    <div class="col-md-12">
                                        <div class="row form-group">
                                            <div class="col-md-6 file_upload_box border file_upload_border mt-2">
                                                <div class="mt-2">
                                                    <div class="col-md-12  text-center">
                                                        <div>
                                                            <a class="media_link" data-input="image"
                                                                data-isremovable="0"
                                                                data-is-multiple-uploads-allowed="0"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#media-upload-modal"
                                                                value="Upload Photo">
                                                                <h4><i class='bx bx-upload'></i> Upload
                                                            </a></h4>
                                                            <p class="image_recommendation">Recommended Size: 180 x
                                                                180 pixels</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 container-fluid row image-upload-section">
                                                <div
                                                    class="col-md-12 col-sm-12 p-3 mb-5 bg-white rounded m-4 text-center grow image">
                                                    @if (!empty($data->image))
                                                        <img src="{{ app(MediaService::class)->getMediaImageUrl($data->image) }}"
                                                            class="img-fluid rounded" alt="">
                                                        <input type="hidden" name="image" value="{{ $data->image }}">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset"
                                    class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                <button type="submit"
                                    class="btn btn-primary submit_button">{{ labels('admin_labels.update_category', 'Update Category') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
