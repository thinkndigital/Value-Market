@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.create_order', 'Create Order') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.create_order', 'Create Order')" :subtitle="labels(
        'wholesaler_labels.create_order_subtitle',
        'Log an order on behalf of a seller (phone or in-person) - it is created already accepted',
    )" :breadcrumbs="[
        ['label' => labels('wholesaler_labels.orders', 'Orders'), 'url' => route('wholesaler.orders.index')],
        ['label' => labels('wholesaler_labels.create_order', 'Create Order')],
    ]" />

    <div class="row mt-3">
        <div class="col-md-8">
            <div class="card content-area p-4">
                <form id="createWholesaleOrderForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ labels('admin_labels.product', 'Product') }}<span class='text-asterisks'>*</span></label>
                        <select name="wholesaler_product_id" id="co_product" class="form-select" required>
                            <option value="">{{ labels('wholesaler_labels.select_product', 'Select Product') }}</option>
                            @foreach ($products as $p)
                                @php($n = json_decode($p->name, true))
                                <option value="{{ $p->id }}" data-price="{{ $p->wholesale_price }}" data-min-qty="{{ $p->min_order_qty }}">
                                    {{ $n['en'] ?? '' }} ({{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}: {{ $p->wholesale_price }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ labels('wholesaler_labels.seller_store', 'Seller / Store') }}<span class='text-asterisks'>*</span></label>
                        <select name="seller_store_id" id="co_seller_store" class="form-select" required>
                            <option value="">{{ labels('wholesaler_labels.select_seller', 'Select Seller') }}</option>
                            @foreach ($sellerStores as $ss)
                                <option value="{{ $ss->id }}">{{ optional($ss->user)->username }} - {{ $ss->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.quantity', 'Quantity') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="quantity" id="co_quantity" class="form-control" min="1" required>
                            <small class="text-muted" id="co_min_qty_hint"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.retail_price', "Seller's Retail Price") }}<span class='text-asterisks'>*</span></label>
                            <input type="number" step="0.01" name="retail_price" id="co_retail_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ labels('admin_labels.note', 'Note') }}</label>
                        <textarea name="wholesaler_note" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="co_total" class="mb-3 fw-bold"></div>
                    <button type="submit" class="btn btn-primary">{{ labels('wholesaler_labels.create_order', 'Create Order') }}</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function coUpdateTotal() {
            var opt = $('#co_product option:selected');
            var price = parseFloat(opt.data('price')) || 0;
            var qty = parseInt($('#co_quantity').val()) || 0;
            $('#co_total').text(price && qty ? "{{ labels('wholesaler_labels.total_amount', 'Total Amount') }}: " + (price * qty).toFixed(2) : '');
        }

        $(document).on('change', '#co_product', function () {
            var opt = $(this).find('option:selected');
            var minQty = parseInt(opt.data('min-qty')) || 1;
            $('#co_quantity').attr('min', minQty).val(minQty);
            $('#co_min_qty_hint').text("{{ labels('wholesaler_labels.min_order_qty', 'Min Order Qty') }}: " + minQty);
            coUpdateTotal();
        });
        $(document).on('input', '#co_quantity', coUpdateTotal);

        $('#createWholesaleOrderForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('wholesaler.orders.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    setTimeout(function () { window.location.href = "{{ route('wholesaler.orders.index') }}"; }, 1500);
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'An error occurred.';
                    iziToast.error({ title: 'Error', message: message, position: 'topRight' });
                }
            });
        });
    </script>
@endsection
