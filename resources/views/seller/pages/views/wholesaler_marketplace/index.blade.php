@extends('seller.layout')
@section('title')
    {{ labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace')" :subtitle="labels(
        'wholesaler_labels.wholesaler_marketplace_subtitle',
        'Browse products listed by wholesalers and place an order to stock your store',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.wholesaler_marketplace', 'Wholesaler Marketplace')]]" />

    <div class="d-flex justify-content-end mb-2">
        <a href="{{ route('seller.wholesaler_marketplace.orders.index') }}" class="btn btn-outline-dark btn-sm">
            <i class="bx bx-list-ul"></i> {{ labels('wholesaler_labels.my_orders', 'My Orders') }}
        </a>
    </div>

    <div class="row mt-1">
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

    <div class="modal fade" id="wholesalerOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ labels('wholesaler_labels.place_order', 'Place Order') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="wholesalerOrderForm">
                    <div class="modal-body">
                        <p class="text-muted">{{ labels('wholesaler_labels.order_help', 'The wholesaler will review and confirm this order. Your product will be added to your catalog once it is delivered.') }}</p>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.quantity', 'Quantity') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="quantity" id="order_quantity" class="form-control" required>
                            <small class="text-muted" id="order_min_qty_hint"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.retail_price', 'Your Retail Price') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" step="0.01" name="retail_price" id="order_retail_price" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.note', 'Note') }} ({{ labels('admin_labels.optional', 'optional') }})</label>
                            <textarea name="seller_note" id="order_note" class="form-control" rows="2"></textarea>
                        </div>
                        <div id="order_total" class="fw-bold"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ labels('admin_labels.cancel', 'Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ labels('wholesaler_labels.place_order', 'Place Order') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var currentOrderId = null;
        var priceRequestSeq = 0;

        // Master architecture Phase 6 pricing tiers (WholesalerProduct::priceFor()) mean the real
        // per-unit price can depend on quantity/seller - so the total shown here is always fetched from
        // the server (never computed from a client-held flat price), same authority placeOrder() uses.
        function orderUpdateTotal() {
            if (!currentOrderId) return;
            var qty = parseInt($('#order_quantity').val()) || 0;
            if (!qty) { $('#order_total').text(''); return; }

            var seq = ++priceRequestSeq;
            $.get("{{ url('seller/wholesaler_marketplace') }}/" + currentOrderId + "/price", { quantity: qty }, function (res) {
                if (seq !== priceRequestSeq) return; // a newer request already superseded this one
                $('#order_total').text(
                    "{{ labels('wholesaler_labels.unit_price', 'Unit Price') }}: " + res.unit_price
                    + " | {{ labels('wholesaler_labels.total_amount', 'Total Amount') }}: " + res.total_amount
                );
            });
        }
        $(document).on('input', '#order_quantity', orderUpdateTotal);

        $(document).on('click', '.place-wholesale-order', function () {
            currentOrderId = $(this).data('id');
            var minQty = parseInt($(this).data('min-qty')) || 1;
            $('#wholesalerOrderForm')[0].reset();
            $('#order_quantity').attr('min', minQty).val(minQty);
            $('#order_min_qty_hint').text("{{ labels('wholesaler_labels.min_order_qty', 'Min Order Qty') }}: " + minQty);
            orderUpdateTotal();
            $('#wholesalerOrderModal').modal('show');
        });

        $('#wholesalerOrderForm').on('submit', function (e) {
            e.preventDefault();
            if (!currentOrderId) return;
            $.ajax({
                url: "{{ url('seller/wholesaler_marketplace') }}/" + currentOrderId + "/order",
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    quantity: $('#order_quantity').val(),
                    retail_price: $('#order_retail_price').val(),
                    seller_note: $('#order_note').val(),
                },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    $('#wholesalerOrderModal').modal('hide');
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
