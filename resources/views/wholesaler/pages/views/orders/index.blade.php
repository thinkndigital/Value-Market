@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.orders', 'Orders') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.orders', 'Orders')" :subtitle="labels(
        'wholesaler_labels.orders_subtitle',
        'Purchase orders sellers have placed against your catalog',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.orders', 'Orders')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <select id="statusFilter" class="form-select" style="max-width: 220px;">
                        <option value="">{{ labels('wholesaler_labels.all_statuses', 'All Statuses') }}</option>
                        <option value="0">{{ labels('wholesaler_labels.pending', 'Pending') }}</option>
                        <option value="1">{{ labels('wholesaler_labels.accepted', 'Accepted') }}</option>
                        <option value="2">{{ labels('wholesaler_labels.shipped', 'Shipped') }}</option>
                        <option value="3">{{ labels('wholesaler_labels.delivered', 'Delivered') }}</option>
                        <option value="4">{{ labels('wholesaler_labels.rejected', 'Rejected') }}</option>
                        <option value="5">{{ labels('wholesaler_labels.cancelled', 'Cancelled') }}</option>
                    </select>
                    <a href="{{ route('wholesaler.orders.create') }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> {{ labels('wholesaler_labels.create_order', 'Create Order') }}
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table" id="wholesale_orders_table" data-toggle="table"
                        data-url="{{ route('wholesaler.orders.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="false"
                        data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                        data-query-params="wholesaleOrdersQueryParams">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="product">{{ labels('admin_labels.product', 'Product') }}</th>
                                <th data-field="seller">{{ labels('admin_labels.seller', 'Seller') }}</th>
                                <th data-field="store">{{ labels('admin_labels.store', 'Store') }}</th>
                                <th data-field="quantity">{{ labels('wholesaler_labels.quantity', 'Quantity') }}</th>
                                <th data-field="total_amount">{{ labels('wholesaler_labels.total_amount', 'Total Amount') }}</th>
                                <th data-field="status">{{ labels('admin_labels.status', 'Status') }}</th>
                                <th data-field="payment_status">{{ labels('wholesaler_labels.payment', 'Payment') }}</th>
                                <th data-field="created_at">{{ labels('admin_labels.date', 'Date') }}</th>
                                <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function wholesaleOrdersQueryParams(params) {
            params.status_filter = $('#statusFilter').val();
            return params;
        }

        $(document).on('change', '#statusFilter', function () {
            $('#wholesale_orders_table').bootstrapTable('refresh');
        });

        $(document).on('click', '.wholesale-order-transition, .wholesale-order-mark-paid', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            $.get(url, function (res) {
                iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                $('#wholesale_orders_table').bootstrapTable('refresh');
            }).fail(function (xhr) {
                var message = (xhr.responseJSON && xhr.responseJSON.message) || 'An error occurred.';
                iziToast.error({ title: 'Error', message: message, position: 'topRight' });
            });
        });
    </script>
@endsection
