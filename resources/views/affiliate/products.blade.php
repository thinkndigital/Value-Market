@extends('affiliate.layout')

@section('title', labels('admin_labels.available_products', 'Available Products'))

@section('content')
    <div class="panel-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">{{ labels('admin_labels.available_products', 'Available Products') }}</h6>
            <span class="text-muted small">{{ labels('admin_labels.links_ready_to_copy', 'Links are already generated - just copy, or open a product for details.') }}</span>
        </div>
        <input type="text" class="form-control form-control-sm mb-3" id="catalog_search_input"
            placeholder="{{ labels('admin_labels.search_products', 'Search products…') }}" autocomplete="off">
        <div class="table-responsive">
            <table class="affiliate-table">
                <thead>
                    <tr>
                        <th>{{ labels('admin_labels.product', 'Product') }}</th>
                        <th>{{ labels('admin_labels.store', 'Store') }}</th>
                        <th>{{ labels('admin_labels.commission', 'Commission') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="available_products_body">
                    <tr>
                        <td colspan="4" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var catalogSearchTimeout = null;

        function renderAvailableProducts(rows) {
            var body = document.getElementById('available_products_body');

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-muted small">' +
                    '{{ labels('admin_labels.no_products_available_yet', 'No products available yet - sellers add these from their own panel.') }}</td></tr>';
                return;
            }

            body.innerHTML = rows.map(function(row) {
                var rate = row.commission_rate_type === 'percentage'
                    ? Number(row.commission_rate_value) + '%'
                    : Number(row.commission_rate_value).toFixed(2);
                var detailUrl = '{{ url('/affiliate/products') }}/' + row.id;
                return '<tr>' +
                    '<td><a href="' + detailUrl + '" class="text-decoration-none text-dark">' +
                    '<img src="' + row.image + '" width="32" height="32" style="object-fit:cover;border-radius:4px;" class="me-2">' +
                    row.name + '</a></td>' +
                    '<td>' + (row.store_name || '') + '</td>' +
                    '<td>' + rate + '</td>' +
                    '<td>' +
                    '<a href="' + detailUrl + '" class="btn btn-sm btn-outline-secondary me-1">{{ labels('admin_labels.view', 'View') }}</a>' +
                    '<button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard(\'' + row.link_url + '\')">' +
                    '{{ labels('admin_labels.copy', 'Copy') }}</button></td>' +
                    '</tr>';
            }).join('');
        }

        function loadAvailableProducts(search) {
            var url = '{{ route('affiliate.available_products.list') }}';
            if (search) {
                url += '?search=' + encodeURIComponent(search);
            }
            fetch(url).then(res => res.json()).then(res => renderAvailableProducts(res.data || []));
        }

        document.getElementById('catalog_search_input').addEventListener('input', function() {
            var query = this.value.trim();
            clearTimeout(catalogSearchTimeout);
            catalogSearchTimeout = setTimeout(function() {
                loadAvailableProducts(query);
            }, 300);
        });

        loadAvailableProducts();
    </script>
@endsection
