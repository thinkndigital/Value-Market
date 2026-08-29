@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.pickup_locations', 'Pickup Locations') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.pickup_locations', 'Pickup Locations')" :subtitle="labels(
        'admin_labels.manage_pickup_locations_for_shipping',
        'Manage Pickup Locations for Shipping',
    )" :breadcrumbs="[['label' => labels('admin_labels.pickup_locations', 'Pickup Locations')]]" />

    {{-- Add modal --}}
    <div class="modal fade" id="add_modal" tabindex="-1" role="dialog" aria-labelledby="addModalCenterTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">
                        {{ labels('admin_labels.add_pickup_location', 'Add Pickup Location') }}
                    </h5>
                    <div class="d-flex justify-content-end"><button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button></div>
                </div>
                <form action="{{ route('pickup_location.store') }}" method="POST" class="add_pickup_location_form">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="pickup_location">{{ labels('admin_labels.pickup_location_name', 'Pickup Location Name') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="pickup_location" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="name">{{ labels('admin_labels.contact_name', 'Contact Name') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="email">{{ labels('admin_labels.email', 'Email') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="phone">{{ labels('admin_labels.phone', 'Phone') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="address">{{ labels('admin_labels.address', 'Address') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="address" required>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="address2">{{ labels('admin_labels.address_2', 'Address 2') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="address2" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="city">{{ labels('admin_labels.city', 'City') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="city" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="state">{{ labels('admin_labels.state', 'State') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="state" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="country">{{ labels('admin_labels.country', 'Country') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="country" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="pincode">{{ labels('admin_labels.pincode', 'Pincode') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="pincode" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="latitude">{{ labels('admin_labels.latitude', 'Latitude') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="latitude" required>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="longitude">{{ labels('admin_labels.longitude', 'Longitude') }}
                                    <span class='text-asterisks text-sm'>*</span></label>
                                <input type="text" class="form-control" name="longitude" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            {{ labels('admin_labels.close', 'Close') }}
                        </button>
                        <button type="submit"
                            class="btn btn-primary">{{ labels('admin_labels.submit', 'Submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div class="modal fade" id="edit_modal" tabindex="-1" role="dialog" aria-labelledby="editModalCenterTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        {{ labels('admin_labels.edit_pickup_location', 'Edit Pickup Location') }}
                    </h5>
                    <div class="d-flex justify-content-end"><button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button></div>
                </div>
                <form method="POST" class="submit_form">
                    @method('PUT')
                    @csrf
                    <input type="hidden" class="edit_id" name="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="edit_pickup_location">{{ labels('admin_labels.pickup_location_name', 'Pickup Location Name') }}</label>
                                <input type="text" class="form-control pickup_location" name="pickup_location">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="edit_name">{{ labels('admin_labels.contact_name', 'Contact Name') }}</label>
                                <input type="text" class="form-control name" name="name">
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="edit_email">{{ labels('admin_labels.email', 'Email') }}</label>
                                <input type="email" class="form-control email" name="email">
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="edit_phone">{{ labels('admin_labels.phone', 'Phone') }}</label>
                                <input type="text" class="form-control phone" name="phone">
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="edit_address">{{ labels('admin_labels.address', 'Address') }}</label>
                                <input type="text" class="form-control address" name="address">
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label for="edit_address2">{{ labels('admin_labels.address_2', 'Address 2') }}</label>
                                <input type="text" class="form-control address2" name="address2">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_city">{{ labels('admin_labels.city', 'City') }}</label>
                                <input type="text" class="form-control city" name="city">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_state">{{ labels('admin_labels.state', 'State') }}</label>
                                <input type="text" class="form-control state" name="state">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_country">{{ labels('admin_labels.country', 'Country') }}</label>
                                <input type="text" class="form-control country" name="country">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_pincode">{{ labels('admin_labels.pincode', 'Pincode') }}</label>
                                <input type="text" class="form-control pincode" name="pincode">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_latitude">{{ labels('admin_labels.latitude', 'Latitude') }}</label>
                                <input type="text" class="form-control latitude" name="latitude">
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label for="edit_longitude">{{ labels('admin_labels.longitude', 'Longitude') }}</label>
                                <input type="text" class="form-control longitude" name="longitude">
                            </div>
                            <input type="hidden" class="seller_id" name="seller_id">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            {{ labels('admin_labels.close', 'Close') }}
                        </button>
                        <button type="submit" class="btn btn-primary"
                            id="save_changes_btn">{{ labels('admin_labels.update', 'Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <section class="overview-data">
        <div class="card content-area p-4 ">
            <div class="row align-items-center d-flex heading mb-5">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-sm-12">
                            <h4>{{ labels('admin_labels.pickup_locations', 'Pickup Locations') }}
                            </h4>
                        </div>
                        <div class="col-sm-12 d-flex justify-content-end mt-md-2 mt-sm-2">
                            <a href="#" class="btn btn-dark me-3" data-bs-toggle="modal"
                                data-bs-target="#add_modal">{{ labels('admin_labels.add_pickup_location', 'Add Pickup Location') }}</a>
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_pickup_location_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="admin_pickup_location_table"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_pickup_location_table" data-toggle="table"
                                data-loading-template="loadingTemplate"
                                data-url="{{ route('admin.pickup_location.list') }}" data-click-to-select="true"
                                data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-maintain-selected="true"
                                data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        </th>
                                        <th data-field="pickup_location" data-sortable="false">
                                            {{ labels('admin_labels.pickup_location_name', 'Pickup Location Name') }}
                                        </th>
                                        <th data-field="name" data-sortable="false">
                                            {{ labels('admin_labels.contact_name', 'Contact Name') }}
                                        </th>
                                        <th data-field="email" data-sortable="false">
                                            {{ labels('admin_labels.email', 'Email') }}
                                        </th>
                                        <th data-field="phone" data-sortable="false">
                                            {{ labels('admin_labels.phone', 'Phone') }}
                                        </th>
                                        <th data-field="city" data-sortable="false">
                                            {{ labels('admin_labels.city', 'City') }}
                                        </th>
                                        <th data-field="verified" data-sortable="false">
                                            {{ labels('admin_labels.status', 'Status') }}
                                        </th>
                                        <th data-field="operate" data-sortable='false'>
                                            {{ labels('admin_labels.action', 'Action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
