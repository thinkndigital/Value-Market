@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.stock', 'Stock') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.stock', 'Stock')" :subtitle="labels(
        'wholesaler_labels.stock_subtitle',
        'Quickly adjust stock levels across your catalog',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.stock', 'Stock')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="table-responsive">
                    <table class="table" id="wholesaler_stock_table" data-toggle="table"
                        data-url="{{ route('wholesaler.stock.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                        data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="image">{{ labels('admin_labels.image', 'Image') }}</th>
                                <th data-field="name">{{ labels('admin_labels.name', 'Name') }}</th>
                                <th data-field="stock">{{ labels('admin_labels.stock', 'Stock') }}</th>
                                <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ labels('wholesaler_labels.adjust_stock', 'Adjust Stock') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ labels('wholesaler_labels.current_stock', 'Current Stock') }}: <span id="as_current_stock" class="fw-bold"></span></p>
                    <label class="form-label">{{ labels('wholesaler_labels.adjustment', 'Adjustment (use a negative number to subtract)') }}</label>
                    <input type="number" id="as_delta" class="form-control" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ labels('admin_labels.cancel', 'Cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="as_save">{{ labels('admin_labels.save', 'Save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var currentStockProductId = null;

        $(document).on('click', '.adjust-stock-btn', function () {
            currentStockProductId = $(this).data('id');
            $('#as_current_stock').text($(this).data('stock'));
            $('#as_delta').val(0);
            $('#adjustStockModal').modal('show');
        });

        $('#as_save').on('click', function () {
            $.ajax({
                url: "{{ url('wholesaler/stock') }}/" + currentStockProductId + "/adjust",
                type: 'POST',
                data: { _token: $('meta[name="csrf-token"]').attr('content'), delta: $('#as_delta').val() },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    $('#adjustStockModal').modal('hide');
                    $('#wholesaler_stock_table').bootstrapTable('refresh');
                },
                error: function (xhr) {
                    iziToast.error({ title: 'Error', message: (xhr.responseJSON && xhr.responseJSON.message) || 'An error occurred.', position: 'topRight' });
                }
            });
        });
    </script>
@endsection
