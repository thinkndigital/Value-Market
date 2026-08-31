@extends('affiliate.layout')

@section('title', labels('admin_labels.my_products', 'My Products'))

@section('content')
    <div class="panel-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">{{ labels('admin_labels.my_products', 'My Products') }}</h6>
            <span class="text-muted small">{{ labels('admin_labels.my_products_explainer', 'Every product you have copied a link for or opened from the marketplace, with its clicks and conversions.') }}</span>
        </div>
        <div class="table-responsive">
            <table class="affiliate-table">
                <thead>
                    <tr>
                        <th>{{ labels('admin_labels.product', 'Product') }}</th>
                        <th>{{ labels('admin_labels.store', 'Store') }}</th>
                        <th>{{ labels('admin_labels.clicks', 'Clicks') }}</th>
                        <th>{{ labels('admin_labels.conversions', 'Conversions') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="my_products_body">
                    <tr>
                        <td colspan="5" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function renderMyProducts(rows) {
            var body = document.getElementById('my_products_body');

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="5" class="text-muted small">' +
                    '{{ labels('admin_labels.no_products_saved_yet', "You haven't added any products yet - copy a link from the Marketplace to add one here.") }}</td></tr>';
                return;
            }

            body.innerHTML = rows.map(function(row) {
                var detailUrl = '{{ url('/affiliate/products') }}/' + row.product_id;
                return '<tr>' +
                    '<td><a href="' + detailUrl + '" class="text-decoration-none text-dark">' +
                    (row.image ? '<img src="' + row.image + '" width="32" height="32" style="object-fit:cover;border-radius:4px;" class="me-2">' : '') +
                    row.name + '</a></td>' +
                    '<td>' + (row.store_name || '') + '</td>' +
                    '<td>' + row.clicks_count + '</td>' +
                    '<td>' + row.conversions_count + '</td>' +
                    '<td>' +
                    '<button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard(\'' + row.link_url + '\')">' +
                    '{{ labels('admin_labels.copy', 'Copy') }}</button></td>' +
                    '</tr>';
            }).join('');
        }

        fetch('{{ route('affiliate.my_products.list') }}').then(res => res.json()).then(res => renderMyProducts(res.data || []));
    </script>
@endsection
