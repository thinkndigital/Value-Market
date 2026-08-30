@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.subscription_plans', 'Subscription Plans') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.subscription_plans', 'Subscription Plans')" :subtitle="labels(
        'admin_labels.manage_seller_subscription_tiers',
        'Manage Seller Subscription Tiers',
    )" :breadcrumbs="[['label' => labels('admin_labels.subscription_plans', 'Subscription Plans')]]" />

    <div class="alert alert-info py-2 mb-4">
        {{ labels(
            'admin_labels.subscription_plans_placeholder_notice',
            'Basic/Pro/Premium below are placeholder defaults. Review and set real pricing, limits, and features before launch.',
        ) }}
    </div>

    {{-- Add modal --}}
    <div class="modal fade" id="add_subscription_plan_modal" tabindex="-1" role="dialog"
        aria-labelledby="addSubscriptionPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSubscriptionPlanModalLabel">
                        {{ labels('admin_labels.add_subscription_plan', 'Add Subscription Plan') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="add_subscription_plan_form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">{{ labels('admin_labels.name', 'Name') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.billing_cycle', 'Billing Cycle') }}</label>
                            <select class="form-select" name="billing_cycle">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.price', 'Price') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.commission_rate_override', 'Commission Rate Override %') }}</label>
                            <input type="number" class="form-control" name="commission_rate" min="0" max="100" step="0.01"
                                placeholder="Leave blank to use the platform default">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.max_products', 'Max Products') }}</label>
                            <input type="number" class="form-control" name="max_products" min="1"
                                placeholder="Leave blank for unlimited">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.description', 'Description') }}</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.features', 'Features (one per line)') }}</label>
                            <textarea class="form-control" name="features_text" rows="3"></textarea>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.sort_order', 'Sort Order') }}</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary"
                            id="add_subscription_plan_submit">{{ labels('admin_labels.submit', 'Submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div class="modal fade" id="edit_subscription_plan_modal" tabindex="-1" role="dialog"
        aria-labelledby="editSubscriptionPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSubscriptionPlanModalLabel">
                        {{ labels('admin_labels.edit_subscription_plan', 'Edit Subscription Plan') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="edit_subscription_plan_form">
                    @csrf
                    <input type="hidden" name="id" id="edit_plan_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">{{ labels('admin_labels.name', 'Name') }}</label>
                            <input type="text" class="form-control" name="name" id="edit_name">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.billing_cycle', 'Billing Cycle') }}</label>
                            <select class="form-select" name="billing_cycle" id="edit_billing_cycle">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.price', 'Price') }}</label>
                            <input type="number" class="form-control" name="price" id="edit_price" min="0" step="0.01">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.commission_rate_override', 'Commission Rate Override %') }}</label>
                            <input type="number" class="form-control" name="commission_rate" id="edit_commission_rate"
                                min="0" max="100" step="0.01">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.max_products', 'Max Products') }}</label>
                            <input type="number" class="form-control" name="max_products" id="edit_max_products" min="1">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.description', 'Description') }}</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.features', 'Features (one per line)') }}</label>
                            <textarea class="form-control" name="features_text" id="edit_features_text" rows="3"></textarea>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.sort_order', 'Sort Order') }}</label>
                            <input type="number" class="form-control" name="sort_order" id="edit_sort_order" min="0">
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">{{ labels('admin_labels.status', 'Status') }}</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary"
                            id="edit_subscription_plan_submit">{{ labels('admin_labels.update', 'Update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Assign to seller modal --}}
    <div class="modal fade" id="assign_subscription_modal" tabindex="-1" role="dialog"
        aria-labelledby="assignSubscriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignSubscriptionModalLabel">
                        {{ labels('admin_labels.assign_to_seller', 'Assign To Seller') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assign_subscription_form">
                    @csrf
                    <input type="hidden" name="plan_id" id="assign_plan_id">
                    <div class="modal-body">
                        <p class="text-muted small" id="assign_plan_name"></p>
                        <div class="form-group">
                            <label class="form-label">{{ labels('admin_labels.seller_id', 'Seller ID') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <input type="number" class="form-control" name="seller_id" min="1" required>
                            <small class="text-muted">{{ labels('admin_labels.find_seller_id_hint', 'Find the seller ID from the Sellers list.') }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary"
                            id="assign_subscription_submit">{{ labels('admin_labels.assign', 'Assign') }}</button>
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
                            <h4>{{ labels('admin_labels.subscription_plans', 'Subscription Plans') }}
                            </h4>
                        </div>
                        <div class="col-sm-12 d-flex justify-content-end mt-md-2 mt-sm-2">
                            <a href="#" class="btn btn-dark me-3" data-bs-toggle="modal"
                                data-bs-target="#add_subscription_plan_modal">{{ labels('admin_labels.add_subscription_plan', 'Add Subscription Plan') }}</a>
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_subscription_plans_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="admin_subscription_plans_table"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_subscription_plans_table" data-toggle="table"
                                data-loading-template="loadingTemplate" data-url="{{ route('admin.subscription_plans.list') }}"
                                data-side-pagination="client" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="sort_order"
                                data-sort-order="asc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-response-handler="subscriptionPlansResponseHandler">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        </th>
                                        <th data-field="name" data-sortable="true">
                                            {{ labels('admin_labels.name', 'Name') }}
                                        </th>
                                        <th data-field="billing_cycle" data-sortable="false">
                                            {{ labels('admin_labels.billing_cycle', 'Billing Cycle') }}
                                        </th>
                                        <th data-field="price" data-sortable="true">
                                            {{ labels('admin_labels.price', 'Price') }}
                                        </th>
                                        <th data-field="commission_rate" data-sortable="false">
                                            {{ labels('admin_labels.commission_rate_override', 'Commission Override') }}
                                        </th>
                                        <th data-field="max_products" data-sortable="false">
                                            {{ labels('admin_labels.max_products', 'Max Products') }}
                                        </th>
                                        <th data-field="status" data-sortable="false">
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

    {{-- This layout has no @stack('scripts') for @push to target (same convention as
        admin/pages/tables/commission_rules.blade.php). --}}
    <script>
        function subscriptionPlansResponseHandler(res) {
            return (res.data || []).map(function(row) {
                var featuresText = (row.features || []).join('\n');

                row.operate = '<a href="#" class="btn edit_subscription_plan" data-id="' + row.id +
                    '" data-name="' + escapeHtmlAttr(row.name) +
                    '" data-billing_cycle="' + row.billing_cycle +
                    '" data-price="' + row.price +
                    '" data-commission_rate="' + (row.commission_rate ?? '') +
                    '" data-max_products="' + (row.max_products ?? '') +
                    '" data-description="' + escapeHtmlAttr(row.description ?? '') +
                    '" data-features_text="' + escapeHtmlAttr(featuresText) +
                    '" data-sort_order="' + row.sort_order +
                    '" data-status="' + (row.status ? 1 : 0) +
                    '" data-bs-toggle="modal" data-bs-target="#edit_subscription_plan_modal"><i class="bx bx-pencil"></i></a>' +
                    ' <a href="#" class="btn assign_subscription_plan" data-id="' + row.id + '" data-name="' + escapeHtmlAttr(row.name) +
                    '" data-bs-toggle="modal" data-bs-target="#assign_subscription_modal"><i class="bx bx-user-plus"></i></a>' +
                    ' <a href="#" class="btn delete_subscription_plan text-danger" data-id="' + row.id + '"><i class="bx bx-trash"></i></a>';

                row.billing_cycle = row.billing_cycle.charAt(0).toUpperCase() + row.billing_cycle.slice(1);
                row.commission_rate = row.commission_rate ?? 'Platform default';
                row.max_products = row.max_products ?? 'Unlimited';
                row.status = row.status
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
                return row;
            });
        }

        function escapeHtmlAttr(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        $('#add_subscription_plan_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var data = $form.serializeArray().reduce(function(acc, field) {
                acc[field.name] = field.value;
                return acc;
            }, {});
            data.features = (data.features_text || '').split('\n').map(function(f) { return f.trim(); }).filter(Boolean);
            delete data.features_text;

            $.ajax({
                url: "{{ route('admin.subscription_plans.store') }}",
                method: 'POST',
                data: data,
                traditional: true,
                success: function(response) {
                    if (response.error) {
                        iziToast.error({ title: 'Error', message: response.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                    $('#add_subscription_plan_modal').modal('hide');
                    $form[0].reset();
                    $('#admin_subscription_plans_table').bootstrapTable('refresh');
                },
                error: function() {
                    iziToast.error({ title: 'Error', message: 'Something went wrong! Try again.', position: 'topRight' });
                }
            });
        });

        $(document).on('click', '.edit_subscription_plan', function() {
            $('#edit_plan_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_billing_cycle').val($(this).data('billing_cycle'));
            $('#edit_price').val($(this).data('price'));
            $('#edit_commission_rate').val($(this).data('commission_rate'));
            $('#edit_max_products').val($(this).data('max_products'));
            $('#edit_description').val($(this).data('description'));
            $('#edit_features_text').val($(this).data('features_text'));
            $('#edit_sort_order').val($(this).data('sort_order'));
            $('#edit_status').val($(this).data('status'));
        });

        $('#edit_subscription_plan_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var id = $('#edit_plan_id').val();
            var data = $form.serializeArray().reduce(function(acc, field) {
                acc[field.name] = field.value;
                return acc;
            }, {});
            data.features = (data.features_text || '').split('\n').map(function(f) { return f.trim(); }).filter(Boolean);
            delete data.features_text;

            $.ajax({
                url: "{{ url('admin/subscription_plans') }}/" + id,
                method: 'POST',
                data: data,
                traditional: true,
                success: function(response) {
                    if (response.error) {
                        iziToast.error({ title: 'Error', message: response.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                    $('#edit_subscription_plan_modal').modal('hide');
                    $('#admin_subscription_plans_table').bootstrapTable('refresh');
                },
                error: function() {
                    iziToast.error({ title: 'Error', message: 'Something went wrong! Try again.', position: 'topRight' });
                }
            });
        });

        $(document).on('click', '.assign_subscription_plan', function() {
            $('#assign_plan_id').val($(this).data('id'));
            $('#assign_plan_name').text('Plan: ' + $(this).data('name'));
        });

        $('#assign_subscription_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: "{{ route('admin.subscription_plans.assign') }}",
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.error) {
                        iziToast.error({ title: 'Error', message: response.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                    $('#assign_subscription_modal').modal('hide');
                    $form[0].reset();
                },
                error: function() {
                    iziToast.error({ title: 'Error', message: 'Something went wrong! Try again.', position: 'topRight' });
                }
            });
        });

        $(document).on('click', '.delete_subscription_plan', function() {
            var id = $(this).data('id');
            if (!confirm('Delete this subscription plan?')) {
                return;
            }
            $.ajax({
                url: "{{ url('admin/subscription_plans') }}/" + id,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.error) {
                        iziToast.error({ title: 'Error', message: response.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: response.message, position: 'topRight' });
                    $('#admin_subscription_plans_table').bootstrapTable('refresh');
                },
                error: function() {
                    iziToast.error({ title: 'Error', message: 'Something went wrong! Try again.', position: 'topRight' });
                }
            });
        });
    </script>
@endsection
