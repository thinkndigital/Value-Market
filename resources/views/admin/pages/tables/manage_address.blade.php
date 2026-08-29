@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.customer_address', 'Customer Address') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.customer_address', 'Customer Address')" :subtitle="labels('admin_labels.track_and_manage_customer_addresses', 'Track and Manage Customer Addresses')" :breadcrumbs="[
        ['label' => labels('admin_labels.customers', 'Customers')],
        ['label' => labels('admin_labels.address', 'Address')],
    ]" />


    {{-- table  --}}
    <section
        class="overview-data {{ $user_role == 'super_admin' || $logged_in_user->hasPermissionTo('view address') ? '' : 'd-none' }}">
        <div class="card content-area p-4 ">
            <div class="row align-items-center d-flex heading mb-5">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>{{ labels('admin_labels.manage_customer_address', 'Manage Customer Address') }}
                            </h4>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end ">
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_customer_address_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableFilter" data-bs-toggle="offcanvas"
                                data-bs-target="#columnFilterOffcanvas" data-table="admin_customer_address_table"
                                dateFilter='false' orderStatusFilter='false' paymentMethodFilter='false'
                                orderTypeFilter='false'><i class='bx bx-filter-alt'></i></a>
                            <a class="btn me-2" id="tableRefresh"data-table="admin_customer_address_table"><i
                                    class='bx bx-refresh'></i></a>
                            <div class="dropdown">
                                <a class="btn dropdown-toggle export-btn" type="button" id="exportOptionsDropdown"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class='bx bx-download'></i>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="exportOptionsDropdown">
                                    <li><button class="dropdown-item" type="button"
                                            onclick="exportTableData('admin_customer_address_table','csv')">CSV</button>
                                    </li>
                                    <li><button class="dropdown-item" type="button"
                                            onclick="exportTableData('admin_customer_address_table','json')">JSON</button>
                                    </li>
                                    <li><button class="dropdown-item" type="button"
                                            onclick="exportTableData('admin_customer_address_table','sql')">SQL</button>
                                    </li>
                                    <li><button class="dropdown-item" type="button"
                                            onclick="exportTableData('admin_customer_address_table','excel')">Excel</button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_customer_address_table" data-toggle="table"
                                data-loading-template="loadingTemplate"
                                data-url="{{ route('admin.customers.getCustomersAddressesList') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-maintain-selected="true" data-export-types='["txt","excel"]'
                                data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        <th data-field="name" data-disabled="1" data-sortable="false">
                                            {{ labels('admin_labels.user_name', 'User Name') }}
                                        </th>
                                        <th data-field="type" data-sortable="false">
                                            {{ labels('admin_labels.type', 'Type') }}
                                        </th>
                                        <th data-field="mobile" data-sortable="false">
                                            {{ labels('admin_labels.mobile', 'Mobile') }}
                                        </th>
                                        <th data-field="alternate_mobile" data-sortable="false">
                                            {{ labels('admin_labels.alternate_mobile', 'Alternate Mobile') }}
                                        </th>
                                        <th data-field="address" data-sortable="false" data-visible="false">
                                            {{ labels('admin_labels.address', 'Address') }}
                                        </th>
                                        <th data-field="landmark" data-sortable="false">
                                            {{ labels('admin_labels.landmark', 'Landmark') }}
                                        </th>
                                        <th data-field="area" data-sortable="false">
                                            {{ labels('admin_labels.area', 'Area') }}
                                        </th>
                                        <th data-field="city" data-sortable="false">
                                            {{ labels('admin_labels.city', 'City') }}
                                        </th>
                                        <th data-field="state" data-sortable="false">
                                            {{ labels('admin_labels.state', 'State') }}
                                        </th>
                                        <th data-field="pincode" data-sortable="false">
                                            {{ labels('admin_labels.zipcodes', 'ZipCode') }}
                                        </th>
                                        <th data-field="country" data-sortable="false">
                                            {{ labels('admin_labels.country', 'Country') }}
                                        </th>
                                        <th data-field="operate" data-sortable="false">
                                            {{ labels('admin_labels.action', 'Action') }}
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Changelog v1.1.2 ("Interactive map added to address form"): this page had no edit UI at all before -
        the edit modal below, and the "Edit" row action added to getCustomersAddressesList()'s operate
        column, are both new. --}}
    <div class="modal fade" id="edit_address_modal" tabindex="-1" role="dialog" aria-labelledby="editAddressModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">
                        {{ labels('admin_labels.edit_address', 'Edit Address') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="edit_address_form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" id="edit_address_id">
                    <input type="hidden" name="user_id" id="edit_address_user_id">
                    <div class="modal-body">
                        <x-admin.address-map />
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.name', 'Name') }}</label>
                                <input type="text" class="form-control" name="name" id="edit_address_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.type', 'Type') }}</label>
                                <input type="text" class="form-control" name="type" id="edit_address_type">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.mobile', 'Mobile') }}</label>
                                <input type="text" class="form-control" name="mobile" id="edit_address_mobile">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.alternate_mobile', 'Alternate Mobile') }}</label>
                                <input type="text" class="form-control" name="alternate_mobile" id="edit_address_alternate_mobile">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ labels('admin_labels.address', 'Address') }}</label>
                                <textarea class="form-control" name="address" id="edit_address_address"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.landmark', 'Landmark') }}</label>
                                <input type="text" class="form-control" name="landmark" id="edit_address_landmark">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.city', 'City') }}</label>
                                <input type="text" class="form-control" name="other_city" id="edit_address_city">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.area', 'Area') }}</label>
                                <input type="text" class="form-control" name="other_areas" id="edit_address_area">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.state', 'State') }}</label>
                                <input type="text" class="form-control" name="state" id="edit_address_state">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.zipcodes', 'ZipCode') }}</label>
                                <input type="text" class="form-control" name="pincode_name" id="edit_address_pincode">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('admin_labels.country', 'Country') }}</label>
                                <input type="text" class="form-control" name="country" id="edit_address_country">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            {{ labels('admin_labels.close', 'Close') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            {{ labels('admin_labels.save_changes', 'Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).on('click', '.edit-address', function() {
            var $btn = $(this);
            $('#edit_address_id').val($btn.data('id'));
            $('#edit_address_user_id').val($btn.data('user_id'));
            $('#edit_address_name').val($btn.data('name'));
            $('#edit_address_type').val($btn.data('type'));
            $('#edit_address_mobile').val($btn.data('mobile'));
            $('#edit_address_alternate_mobile').val($btn.data('alternate_mobile'));
            $('#edit_address_address').val($btn.data('address'));
            $('#edit_address_landmark').val($btn.data('landmark'));
            $('#edit_address_city').val($btn.data('city'));
            $('#edit_address_area').val($btn.data('area'));
            $('#edit_address_state').val($btn.data('state'));
            $('#edit_address_pincode').val($btn.data('pincode'));
            $('#edit_address_country').val($btn.data('country'));
            window.pendingAddressLat = $btn.data('latitude');
            window.pendingAddressLng = $btn.data('longitude');
        });

        $('#edit_address_modal').on('shown.bs.modal', function() {
            window.initAddressMap(window.pendingAddressLat, window.pendingAddressLng);
        });

        $('#edit_address_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: "{{ route('admin.customers.address.update') }}",
                method: 'PUT',
                data: $form.serialize(),
                success: function(response) {
                    if (response.error) {
                        iziToast.error({
                            title: 'Error',
                            message: response.message,
                            position: 'topRight'
                        });
                        return;
                    }
                    iziToast.success({
                        title: 'Success',
                        message: response.message,
                        position: 'topRight'
                    });
                    $('#edit_address_modal').modal('hide');
                    $('#admin_customer_address_table').bootstrapTable('refresh');
                },
                error: function(xhr) {
                    iziToast.error({
                        title: 'Error',
                        message: (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0]))) ||
                            'Something went wrong! Try again.',
                        position: 'topRight'
                    });
                }
            });
        });
    </script>
@endsection
