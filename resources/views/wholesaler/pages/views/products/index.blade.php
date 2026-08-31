@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.my_products', 'My Products') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.my_products', 'My Products')" :subtitle="labels(
        'wholesaler_labels.my_products_subtitle',
        'Every listing goes through admin approval before sellers can import it',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.my_products', 'My Products')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">{{ labels('wholesaler_labels.my_products', 'My Products') }}</h5>
                    <button type="button" class="btn btn-primary" id="addWholesalerProductBtn" data-bs-toggle="modal" data-bs-target="#wholesalerProductModal">
                        <i class="bx bx-plus"></i> {{ labels('wholesaler_labels.add_product', 'Add Product') }}
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table" id="wholesaler_products_table" data-toggle="table"
                        data-url="{{ route('wholesaler.products.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50, 100]" data-search="true"
                        data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="image">{{ labels('admin_labels.image', 'Image') }}</th>
                                <th data-field="name" data-sortable="true">{{ labels('admin_labels.name', 'Name') }}</th>
                                <th data-field="wholesale_price">{{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}</th>
                                <th data-field="min_order_qty">{{ labels('wholesaler_labels.min_order_qty', 'Min Order Qty') }}</th>
                                <th data-field="stock">{{ labels('admin_labels.stock', 'Stock') }}</th>
                                <th data-field="status">{{ labels('admin_labels.status', 'Status') }}</th>
                                <th data-field="operate">{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="wholesalerProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="wholesalerProductModalTitle">{{ labels('wholesaler_labels.add_product', 'Add Product') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="wholesalerProductForm" class="submit_form" action="{{ route('wholesaler.products.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_wholesaler_product_id" id="_wholesaler_product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.name', 'Name') }}<span class='text-asterisks'>*</span></label>
                            <input type="text" name="name" id="wp_name" class="form-control" required>
                            <div class="text-danger"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.category', 'Category') }}<span class='text-asterisks'>*</span></label>
                            <select name="category_id" id="wp_category_id" class="form-select" required>
                                <option value="">{{ labels('admin_labels.select_category', 'Select Category') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ data_get(json_decode($category->name, true), 'en', $category->slug) }}</option>
                                @endforeach
                            </select>
                            <div class="text-danger"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('wholesaler_labels.wholesale_price', 'Wholesale Price') }}<span class='text-asterisks'>*</span></label>
                                <input type="number" step="0.01" name="wholesale_price" id="wp_price" class="form-control" required>
                                <div class="text-danger"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ labels('wholesaler_labels.min_order_qty', 'Min Order Qty') }}<span class='text-asterisks'>*</span></label>
                                <input type="number" name="min_order_qty" id="wp_min_order_qty" class="form-control" value="1" required>
                                <div class="text-danger"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.stock', 'Stock') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="stock" id="wp_stock" class="form-control" required>
                            <div class="text-danger"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.description', 'Description') }}</label>
                            <textarea name="description" id="wp_description" class="form-control" rows="3"></textarea>
                            <div class="text-danger"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.image', 'Image') }}<span class='text-asterisks' id="wp_image_required">*</span></label>
                            <input type="file" name="image" id="wp_image" class="form-control" accept="image/*">
                            <img id="wp_image_preview" src="" class="mt-2 d-none" style="max-height:80px;">
                            <div class="text-danger"></div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="affiliate_enabled" value="1" id="wp_affiliate_enabled" class="form-check-input">
                            <label class="form-check-label" for="wp_affiliate_enabled">{{ labels('wholesaler_labels.affiliate_enabled', 'Allow affiliate promotion (coming soon)') }}</label>
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
            $(document).on('click', '#addWholesalerProductBtn', function () {
                $('#wholesalerProductForm')[0].reset();
                $('#_wholesaler_product_id').val('');
                $('#wholesalerProductForm').attr('action', "{{ route('wholesaler.products.store') }}");
                $('#wholesalerProductModalTitle').text("{{ labels('wholesaler_labels.add_product', 'Add Product') }}");
                $('#wp_image_preview').addClass('d-none');
                $('#wp_image_required').removeClass('d-none');
            });

            $(document).on('click', '.edit-wholesaler-product', function () {
                var id = $(this).data('id');
                $.get("{{ url('wholesaler/products') }}/" + id + "/edit", function (res) {
                    $('#_wholesaler_product_id').val(res.id);
                    $('#wp_name').val(res.name);
                    $('#wp_category_id').val(res.category_id);
                    $('#wp_price').val(res.wholesale_price);
                    $('#wp_min_order_qty').val(res.min_order_qty);
                    $('#wp_stock').val(res.stock);
                    $('#wp_description').val(res.description);
                    $('#wp_affiliate_enabled').prop('checked', res.affiliate_enabled);
                    $('#wp_image_preview').attr('src', res.image).removeClass('d-none');
                    $('#wp_image_required').addClass('d-none');
                    $('#wholesalerProductForm').attr('action', "{{ url('wholesaler/products') }}/" + id);
                    $('#wholesalerProductModalTitle').text("{{ labels('admin_labels.edit', 'Edit') }}");
                    $('#wholesalerProductModal').modal('show');
                });
            });

            $(document).on('click', '.delete-wholesaler-product', function () {
                var id = $(this).data('id');
                if (!confirm("{{ labels('admin_labels.are_you_sure', 'Are you sure?') }}")) return;
                $.ajax({
                    url: "{{ url('wholesaler/products') }}/" + id,
                    type: 'DELETE',
                    data: { _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function (res) {
                        iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                        $('#wholesaler_products_table').bootstrapTable('refresh');
                    }
                });
            });
        </script>
@endsection
