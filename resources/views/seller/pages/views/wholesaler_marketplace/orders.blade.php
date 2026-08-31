@extends('seller.layout')
@section('title')
    {{ labels('wholesaler_labels.my_orders', 'My Orders') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('wholesaler_labels.my_orders', 'My Wholesale Orders')" :subtitle="labels(
        'wholesaler_labels.my_orders_subtitle',
        'Track the orders you have placed with wholesalers',
    )" :breadcrumbs="[
        ['label' => labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace'), 'url' => route('seller.wholesaler_marketplace.index')],
        ['label' => labels('wholesaler_labels.my_orders', 'My Orders')],
    ]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="table-responsive">
                    <table class="table" id="my_wholesale_orders_table" data-toggle="table"
                        data-url="{{ route('seller.wholesaler_marketplace.orders.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50]" data-sort-name="id"
                        data-sort-order="desc" data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="product">{{ labels('admin_labels.product', 'Product') }}</th>
                                <th data-field="wholesaler">{{ labels('wholesaler_labels.wholesaler', 'Wholesaler') }}</th>
                                <th data-field="quantity">{{ labels('wholesaler_labels.quantity', 'Quantity') }}</th>
                                <th data-field="total_amount">{{ labels('wholesaler_labels.total_amount', 'Total Amount') }}</th>
                                <th data-field="status">{{ labels('admin_labels.status', 'Status') }}</th>
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
        $(document).on('click', '.cancel-wholesale-order', function () {
            if (!confirm("{{ labels('admin_labels.are_you_sure', 'Are you sure?') }}")) return;
            var id = $(this).data('id');
            $.get("{{ url('seller/wholesaler_marketplace/orders') }}/" + id + "/cancel", function (res) {
                iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                $('#my_wholesale_orders_table').bootstrapTable('refresh');
            });
        });
    </script>
@endsection
