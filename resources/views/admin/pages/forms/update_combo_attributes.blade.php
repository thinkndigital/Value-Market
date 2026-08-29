@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.update_attribute', 'Update Attribute') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.update_attribute', 'Update Attribute')" :subtitle="labels(
        'admin_labels.efficiently_manage_product_attributes_with_precision',
        'Efficiently Manage Product Attributes with Precision',
    )" :breadcrumbs="[
        [
            'label' => labels('admin_labels.combo_products', 'Combo Products'),
            'url' => route('admin.combo_products.index'),
        ],
        [
            'label' => labels('admin_labels.attributes', 'Attributes'),
            'url' => route('admin.combo_product_attributes.index'),
        ],
        ['label' => labels('admin_labels.update_attribute', 'Update Attribute')],
    ]" />

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">
                        {{ labels('admin_labels.update_attribute', 'Update Attribute') }}
                    </h5>
                    <form class="submit_form" action="{{ url('admin/combo_product_attributes/update/' . $attribute_data->id) }}"
                        method="POST" enctype="multipart/form-data">
                        @method('PUT')
                        @csrf
                        <div class="mb-3">
                            <label class="attribute_name" for="basic-default-fullname">
                                {{ labels('admin_labels.attribute_name', 'Attribute Name') }}
                            </label>
                            <input type="text" class="form-control" id="basic-default-fullname" placeholder="Size"
                                name="name" value="{{ $attribute_data->name }}">
                        </div>
                        <div class="mb-3">
                            <label class="attribute_value" for="attribute_values">
                                {{ labels('admin_labels.attribute_values', 'Attribute Values') }}
                                <span class='text-asterisks text-sm'>*</span>
                            </label>
                            <input type="text" class="form-control" id="attribute_values" placeholder="Small,Medium"
                                name="value" value="{{ $attribute_values }}">
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="reset"
                                class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                            <button type="submit"
                                class="btn btn-primary submit_button">{{ labels('admin_labels.update_attribute', 'Update Attribute') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
