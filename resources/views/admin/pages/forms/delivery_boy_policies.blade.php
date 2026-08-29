@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.delivery_boy_policies', 'Delivery Boy Policies') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.delivery_boy_policies', 'Delivery Boy Policies')" :subtitle="labels(
        'admin_labels.effortlessly_manage_and_enforce_system_policies',
        'Effortlessly Manage and Enforce System Policies',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.settings', 'Settings'), 'url' => route('settings.index')],
        ['label' => labels('admin_labels.delivery_boy_policies', 'Delivery Boy Policies')],
    ]" />
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 col-xxl-6">
                            <div class="form-group">
                                <form id="" action="{{ route('delivery_boy_privacy_policy.store') }}" class="submit_form"
                                    enctype="multipart/form-data" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <div class="card-body p-0">
                                            <div class="form-group">
                                                <h5 class="mb-4">
                                                    {{ labels('admin_labels.delivery_boy_privacy_policy', 'Delivery Boy Privacy Policy') }}
                                                </h5>
                                                <a href="{{ route('delivery_boy_privacy_policy.view') }}" target="_blank"
                                                    class="p-2 badge bg-gradient-info" title="View Delivery Boy Privacy Policy"><i
                                                        class="fa fa-eye"></i></a>
                                                <textarea class="form-control addr_editor" name="delivery_boy_privacy_policy" placeholder="Delivery Boy Privacy Policy" rows="5">{{ isset($delivery_boy_privacy_policy['delivery_boy_privacy_policy']) ? $delivery_boy_privacy_policy['delivery_boy_privacy_policy'] : '' }}</textarea>
                                            </div>
                                        </div>
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
                        <div class="col-md-12 col-xxl-6">
                            <div class="form-group">
                                <form id="" action="{{ route('delivery_boy_terms_and_conditions.store') }}" class="submit_form"
                                    enctype="multipart/form-data" method="POST">
                                    @csrf
                                    <div class="form-group">
                                        <div class="card-body p-0">
                                            <div class="form-group">
                                                <h5 class="mb-4">
                                                    {{ labels('admin_labels.delivery_boy_terms_and_conditions', 'Delivery Boy Terms & Conditions') }}
                                                </h5>
                                                <a href="{{ route('delivery_boy_terms_and_conditions.view') }}" target="_blank"
                                                    class="p-2 badge bg-gradient-info" title="View Delivery Boy Terms & Conditions"><i
                                                        class="fa fa-eye"></i></a>
                                                <textarea class="form-control addr_editor" name="delivery_boy_terms_and_conditions" placeholder="Delivery Boy Terms and Conditions" rows="5">{{ isset($delivery_boy_terms_and_conditions['delivery_boy_terms_and_conditions']) ? $delivery_boy_terms_and_conditions['delivery_boy_terms_and_conditions'] : '' }}</textarea>
                                            </div>
                                        </div>
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
        </div>
    </div>
@endsection
