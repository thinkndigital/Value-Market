@extends('seller.layout')
@section('title')
    {{ labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace')" :subtitle="labels(
        'wholesaler_labels.wholesaler_marketplace_subtitle',
        'Browse products listed by wholesalers and import them into your own catalog',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="table-responsive">
                    <table class="table" id="wholesaler_marketplace_table" data-toggle="table"
                        data-url="{{ route('seller.wholesaler_marketplace.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[12, 24, 50]" data-search="true"
                        data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="image">{{ labels('admin_labels.image', 'Image') }}</th>
                                <th data-field="name">{{ labels('admin_labels.name', 'Name') }}</th>
                                <th data-field="wholesaler">{{ labels('wholesaler_labels.wholesaler', 'Wholesaler') }}</th>
                                <th data-field="wholesale_price">{{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}</th>
                                <th data-field="min_order_qty">{{ labels('wholesaler_labels.min_order_qty', 'Min Order Qty') }}</th>
                                <th data-field="stock">{{ labels('wholesaler_labels.wholesaler_stock', 'Wholesaler Stock') }}</th>
                                <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="wholesalerImportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ labels('wholesaler_labels.import_product', 'Import Product') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="wholesalerImportForm">
                    <div class="modal-body">
                        <p class="text-muted">{{ labels('wholesaler_labels.import_help', "Set your own retail price and starting stock. This will add a new product to your own catalog - the wholesaler's listing itself is not changed.") }}</p>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.retail_price', 'Your Retail Price') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" step="0.01" name="retail_price" id="import_retail_price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.stock', 'Stock') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="stock" id="import_stock" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ labels('admin_labels.cancel', 'Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ labels('wholesaler_labels.import', 'Import') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var currentImportId = null;

        $(document).on('click', '.import-wholesaler-product', function () {
            currentImportId = $(this).data('id');
            $('#wholesalerImportForm')[0].reset();
            $('#wholesalerImportModal').modal('show');
        });

        $('#wholesalerImportForm').on('submit', function (e) {
            e.preventDefault();
            if (!currentImportId) return;
            $.ajax({
                url: "{{ url('seller/wholesaler_marketplace') }}/" + currentImportId + "/import",
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    retail_price: $('#import_retail_price').val(),
                    stock: $('#import_stock').val(),
                },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    $('#wholesalerImportModal').modal('hide');
                    $('#wholesaler_marketplace_table').bootstrapTable('refresh');
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'An error occurred.';
                    iziToast.error({ title: 'Error', message: message, position: 'topRight' });
                }
            });
        });
    </script>
@endsection
