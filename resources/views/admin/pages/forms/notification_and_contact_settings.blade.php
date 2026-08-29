@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.notification_and_contact_settings', 'Notification & Contact Settings') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.notification_and_contact_settings', 'Notification & Contact Settings')" :subtitle="labels(
        'admin_labels.manage_push_notifications_and_contact_information',
        'Manage Push Notifications and Contact Information',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.settings', 'Settings'), 'url' => route('settings.index')],
        ['label' => labels('admin_labels.notification_and_contact_settings', 'Notification & Contact Settings')],
    ]" />
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-group">
                        <form id="" action="{{ route('notification_settings.store') }}" class="submit_form"
                            enctype="multipart/form-data" method="POST">
                            @csrf
                            <h5 class="mb-4">
                                {{ labels('admin_labels.firebase_notification_settings', 'Firebase Notification Settings') }}
                            </h5>
                            <div class="mb-3">
                                <label for="firebase_project_id"
                                    class="form-label">{{ labels('admin_labels.firebase_project_id', 'Firebase Project ID') }}<span
                                        class="text-asterisks text-sm">*</span></label>
                                <input type="text" class="form-control" id="firebase_project_id"
                                    name="firebase_project_id" placeholder="Firebase Project ID"
                                    value="{{ $firebase_project_id ?? '' }}">
                            </div>
                            <div class="mb-3">
                                <label for="service_account_file"
                                    class="form-label">{{ labels('admin_labels.service_account_file', 'Service Account JSON File') }}</label>
                                <input type="file" class="form-control" id="service_account_file"
                                    name="service_account_file" accept="application/json">
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset"
                                    class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                <button type="submit"
                                    class="btn btn-primary submit_button">{{ labels('admin_labels.update_settings', 'Update Settings') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-xxl-6">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-group">
                        <form id="" action="{{ route('contact_us.store') }}" class="submit_form"
                            enctype="multipart/form-data" method="POST">
                            @csrf
                            <h5 class="mb-4">
                                {{ labels('admin_labels.contact_us', 'Contact Us') }}
                            </h5>
                            <textarea class="form-control addr_editor" name="contact_us" placeholder="Contact Us" rows="5">{{ isset($contact_us['contact_us']) ? $contact_us['contact_us'] : '' }}</textarea>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset"
                                    class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                <button type="submit"
                                    class="btn btn-primary submit_button">{{ labels('admin_labels.update_settings', 'Update Settings') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-xxl-6">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-group">
                        <form id="" action="{{ route('about_us.store') }}" class="submit_form"
                            enctype="multipart/form-data" method="POST">
                            @csrf
                            <h5 class="mb-4">
                                {{ labels('admin_labels.about_us', 'About Us') }}
                            </h5>
                            <textarea class="form-control addr_editor" name="about_us" placeholder="About Us" rows="5">{{ isset($about_us['about_us']) ? $about_us['about_us'] : '' }}</textarea>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset"
                                    class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                <button type="submit"
                                    class="btn btn-primary submit_button">{{ labels('admin_labels.update_settings', 'Update Settings') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
