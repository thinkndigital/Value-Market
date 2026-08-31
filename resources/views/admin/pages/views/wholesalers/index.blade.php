@extends('admin.layout')
@section('title')
    {{ labels('wholesaler_labels.wholesalers', 'Wholesalers') }}
@endsection
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>{{ labels('wholesaler_labels.wholesalers', 'Wholesalers') }}</h3>
        <a href="{{ route('admin.wholesalers.products_queue') }}" class="btn btn-outline-primary">
            <i class="fa fa-check-circle"></i> {{ labels('wholesaler_labels.products_approval_queue', 'Products Approval Queue') }}
        </a>
    </div>

    <div class="card content-area p-4">
        <div class="table-responsive">
            <table class="table" id="wholesalers_table" data-toggle="table"
                data-url="{{ route('admin.wholesalers.list') }}" data-side-pagination="server"
                data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true">
                <thead>
                    <tr>
                        <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                        <th data-field="business_name">{{ labels('wholesaler_labels.business_name', 'Business Name') }}</th>
                        <th data-field="username">{{ labels('admin_labels.name', 'Name') }}</th>
                        <th data-field="mobile">{{ labels('admin_labels.mobile', 'Mobile') }}</th>
                        <th data-field="products_count">{{ labels('wholesaler_labels.products', 'Products') }}</th>
                        <th data-field="status">{{ labels('admin_labels.status', 'Status') }}</th>
                        <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <script>
        $(document).on('click', '.toggle-wholesaler-status', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            $.get(url, function (res) {
                iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                $('#wholesalers_table').bootstrapTable('refresh');
            });
        });
    </script>
@endsection
