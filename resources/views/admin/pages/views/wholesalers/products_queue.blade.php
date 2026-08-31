@extends('admin.layout')
@section('title')
    {{ labels('wholesaler_labels.products_approval_queue', 'Products Approval Queue') }}
@endsection
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>{{ labels('wholesaler_labels.products_approval_queue', 'Products Approval Queue') }}</h3>
        <a href="{{ route('admin.wholesalers.index') }}" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left"></i> {{ labels('wholesaler_labels.wholesalers', 'Wholesalers') }}
        </a>
    </div>

    <div class="card content-area p-4">
        <div class="mb-3">
            <select id="statusFilter" class="form-select" style="max-width: 260px;">
                <option value="0" selected>{{ labels('wholesaler_labels.pending_approval', 'Pending Approval') }}</option>
                <option value="1">{{ labels('wholesaler_labels.active', 'Active') }}</option>
                <option value="2">{{ labels('wholesaler_labels.rejected', 'Rejected') }}</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table" id="wholesaler_products_queue_table" data-toggle="table"
                data-url="{{ route('admin.wholesalers.products.list') }}" data-side-pagination="server"
                data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                data-query-params="wholesalerProductsQueryParams">
                <thead>
                    <tr>
                        <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                        <th data-field="image">{{ labels('admin_labels.image', 'Image') }}</th>
                        <th data-field="name">{{ labels('admin_labels.name', 'Name') }}</th>
                        <th data-field="wholesaler">{{ labels('wholesaler_labels.wholesaler', 'Wholesaler') }}</th>
                        <th data-field="wholesale_price">{{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}</th>
                        <th data-field="status">{{ labels('admin_labels.status', 'Status') }}</th>
                        <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <script>
        function wholesalerProductsQueryParams(params) {
            params.status_filter = $('#statusFilter').val();
            return params;
        }

        $(document).on('change', '#statusFilter', function () {
            $('#wholesaler_products_queue_table').bootstrapTable('refresh');
        });

        $(document).on('click', '.approve-wholesaler-product, .reject-wholesaler-product', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            $.get(url, function (res) {
                iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                $('#wholesaler_products_queue_table').bootstrapTable('refresh');
            });
        });
    </script>
@endsection
