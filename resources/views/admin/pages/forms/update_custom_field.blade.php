@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.custom_field', 'Custom Fields') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.custom_fields', 'Custom Fields')" :subtitle="labels(
        'admin_labels.effortless_custom_fields_management_for_an_organized_ecommerce_universe',
        'Effortless Custom Fields Management for an Organized E-commerce Universe',
    )" :breadcrumbs="[['label' => labels('admin_labels.custom_fields', 'Custom Fields')]]" />
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('custom_fields.update', $customField->id) }}" class="submit_form">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.field_name', 'Field Name') }}</label><span
                                class='text-asterisks text-sm'>*</span>
                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $customField->name) }}">
                        </div>
                        <input type="hidden" name="type" value="{{ $customField->type }}">
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.field_type', 'Field Type') }}</label><span
                                class='text-asterisks text-sm'>*</span>
                            <select disabled name="type" class="form-select" id="fieldTypeSelect">
                                <option value="">{{ labels('admin_labels.select', 'Select') }}</option>
                                @foreach (['text', 'number', 'textarea', 'color', 'date', 'file', 'radio', 'dropdown', 'checkbox'] as $type)
                                    <option value="{{ $type }}" {{ $customField->type == $type ? 'selected' : '' }}>
                                        {{ labels("admin_labels.$type", ucfirst($type)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.field_length', 'Field Length') }}</label>
                            <input type="number" min="1" name="field_length" class="form-control"
                                value="{{ old('field_length', $customField->field_length) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.min', 'Min') }}</label>
                            <input type="number" min="1" name="min" class="form-control"
                                value="{{ old('min', $customField->min) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.max', 'Max') }}</label>
                            <input type="number" min="1" name="max" class="form-control"
                                value="{{ old('max', $customField->max) }}">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="required" value="1"
                                id="requiredCheck" {{ $customField->required ? 'checked' : '' }}>
                            <label class="form-check-label"
                                for="requiredCheck">{{ labels('admin_labels.required', 'Required') }}</label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck"
                                {{ $customField->active ? 'checked' : '' }}>
                            <label class="form-check-label"
                                for="activeCheck">{{ labels('admin_labels.active', 'Active') }}</label>
                        </div>

                        <div
                            class="mb-3 customOptionInput {{ in_array($customField->type, ['radio', 'dropdown', 'checkbox']) ? '' : 'd-none' }}">
                            <label class="form-label">{{ labels('admin_labels.options', 'Options') }}</label>
                            <input type="text" id="custom_options" name="options" class="form-control"
                                value="{{ is_array($customField->options) ? implode(', ', $customField->options) : '' }}"
                                placeholder="e.g. Small, Medium, Large">
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <a href="{{ route('admin.custom_fields.index') }}" class="btn reset-btn mx-2">
                                {{ labels('admin_labels.cancel', 'Cancel') }}
                            </a>
                            <button type="submit" class="submit_button btn btn-primary">
                                {{ labels('admin_labels.update_custom_field', 'Update Custom Field') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
