@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.wholesale_pricing', 'Wholesale Pricing') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.wholesale_pricing', 'Wholesale Pricing')" :subtitle="labels(
        'wholesaler_labels.wholesale_pricing_subtitle',
        'Set quantity-break prices for a product - open to every seller, or negotiated for one seller only',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.wholesale_pricing', 'Wholesale Pricing')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="mb-3" style="max-width:420px;">
                    <label class="form-label">{{ labels('admin_labels.products', 'Products') }}</label>
                    <select id="pricing_product_id" class="form-select">
                        <option value="">{{ labels('wholesaler_labels.select_product', 'Select a product') }}</option>
                        @foreach ($products as $product)
                            @php($name = json_decode($product->name, true))
                            <option value="{{ $product->id }}">{{ $name['en'] ?? ('#' . $product->id) }} ({{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}: {{ $product->wholesale_price }})</option>
                        @endforeach
                    </select>
                </div>

                <div id="pricing_panel" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">{{ labels('wholesaler_labels.price_tiers', 'Pricing Tiers') }}</h5>
                        <button type="button" class="btn btn-primary" id="addPriceTierBtn" data-bs-toggle="modal" data-bs-target="#priceTierModal">
                            <i class="bx bx-plus"></i> {{ labels('wholesaler_labels.add_price_tier', 'Add Tier') }}
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table" id="price_tiers_table" data-toggle="table" data-pagination="false" data-mobile-responsive="true">
                            <thead>
                                <tr>
                                    <th data-field="id">{{ labels('admin_labels.id', 'ID') }}</th>
                                    <th data-field="seller">{{ labels('wholesaler_labels.applies_to', 'Applies To') }}</th>
                                    <th data-field="min_quantity">{{ labels('wholesaler_labels.min_quantity', 'Min Quantity') }}</th>
                                    <th data-field="unit_price">{{ labels('wholesaler_labels.unit_price', 'Unit Price') }}</th>
                                    <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="priceTierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ labels('wholesaler_labels.add_price_tier', 'Add Tier') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="priceTierForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.applies_to', 'Applies To') }}</label>
                            <select name="seller_id" id="pt_seller_id" class="form-select">
                                <option value="">{{ labels('wholesaler_labels.all_sellers', 'All Sellers') }}</option>
                                @foreach ($knownSellers as $seller)
                                    <option value="{{ $seller['id'] }}">{{ $seller['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.min_quantity', 'Min Quantity') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="min_quantity" id="pt_min_quantity" class="form-control" min="1" required>
                            <div class="text-danger"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('wholesaler_labels.unit_price', 'Unit Price') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" step="0.01" name="unit_price" id="pt_unit_price" class="form-control" required>
                            <div class="text-danger"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ labels('admin_labels.cancel', 'Cancel') }}</button>
                        <button type="submit" class="btn btn-primary submit_button">{{ labels('admin_labels.save', 'Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function tiersUrl(productId) {
            return "{{ url('wholesaler/pricing') }}/" + productId + "/tiers";
        }

        $(document).on('change', '#pricing_product_id', function () {
            var productId = $(this).val();
            if (!productId) {
                $('#pricing_panel').addClass('d-none');
                return;
            }
            $('#pricing_panel').removeClass('d-none');
            $('#price_tiers_table').bootstrapTable('destroy').bootstrapTable({ url: tiersUrl(productId) });
        });

        $(document).on('click', '#addPriceTierBtn', function () {
            $('#priceTierForm')[0].reset();
        });

        $('#priceTierForm').on('submit', function (e) {
            e.preventDefault();
            var productId = $('#pricing_product_id').val();
            if (!productId) return;
            $.ajax({
                url: tiersUrl(productId),
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    seller_id: $('#pt_seller_id').val(),
                    min_quantity: $('#pt_min_quantity').val(),
                    unit_price: $('#pt_unit_price').val(),
                },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    $('#priceTierModal').modal('hide');
                    $('#price_tiers_table').bootstrapTable('refresh');
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'An error occurred.';
                    iziToast.error({ title: 'Error', message: message, position: 'topRight' });
                }
            });
        });

        $(document).on('click', '.delete-price-tier', function () {
            var id = $(this).data('id');
            var productId = $('#pricing_product_id').val();
            if (!confirm("{{ labels('admin_labels.are_you_sure', 'Are you sure?') }}")) return;
            $.ajax({
                url: tiersUrl(productId) + '/' + id,
                type: 'DELETE',
                data: { _token: $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    $('#price_tiers_table').bootstrapTable('refresh');
                }
            });
        });
    </script>
@endsection
