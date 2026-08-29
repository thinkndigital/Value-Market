<html lang="en">
@php
    use App\Services\MediaService;
    use App\Services\CurrencyService;
@endphp

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($system_settings != null)
        <link rel="icon" type="image/png"
            href="{{ app(MediaService::class)->getMediaImageUrl($system_settings['favicon']) }}">
    @endif
    <title>{{ labels('admin_labels.my_affiliate_account', 'My Affiliate Account') }} | {{ $system_settings['app_name'] }}</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/admin/css/iziToast.css') }}">
    <link id="pagestyle" href="{{ asset('/assets/css/argon-dashboard.css?v=2.0.4') }}" rel="stylesheet" />
    <link id="pagestyle" href="{{ asset('/assets/admin/css/style.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/boxicons/css/boxicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/custom/custom.css') }}">
</head>

<body class="">
    <div class="page-header min-vh-100">
        <div class="col-md-12">
            <div class="d-flex flex-column justify-content-center align-items-center py-5">
                <div class="card" style="max-width: 700px; width: 100%;">
                    <div class="card-body p-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="login-img-box mb-2" style="max-width: 120px;">
                                    @php
                                        $store_logo =
                                            !empty($system_settings['logo']) &&
                                            file_exists(
                                                public_path(config('constants.MEDIA_PATH') . $system_settings['logo']),
                                            )
                                                ? app(MediaService::class)->getMediaImageUrl($system_settings['logo'])
                                                : asset('assets/img/default_full_logo.png');
                                    @endphp
                                    <img src="{{ $store_logo }}" alt="logo" class="img-fluid">
                                </div>
                                <h2 class="font-weight-bolder mb-0">
                                    {{ labels('admin_labels.my_affiliate_account', 'My Affiliate Account') }}</h2>
                                <p class="text-muted mb-0">{{ auth()->user()->username }}</p>
                            </div>
                            <a href="{{ route('affiliate.logout') }}"
                                class="btn btn-outline-secondary">{{ labels('admin_labels.logout', 'Logout') }}</a>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">{{ labels('admin_labels.your_affiliate_link', 'Your Affiliate Link') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="affiliate_link_input"
                                    value="{{ $shareUrl }}" readonly>
                                <button class="btn btn-primary" type="button" onclick="copyAffiliateLink()">
                                    <i class='bx bx-copy'></i> {{ labels('admin_labels.copy', 'Copy') }}
                                </button>
                            </div>
                            <small class="text-muted">{{ labels('admin_labels.affiliate_code', 'Affiliate Code') }}:
                                {{ $link->code }}</small>
                        </div>

                        <div class="row text-center g-3">
                            <div class="col-6 col-md-3">
                                <div class="p-3 border rounded">
                                    <div class="h4 mb-0">{{ $link->clicks_count }}</div>
                                    <small class="text-muted">{{ labels('admin_labels.clicks', 'Clicks') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 border rounded">
                                    <div class="h4 mb-0">{{ $link->conversions_count }}</div>
                                    <small class="text-muted">{{ labels('admin_labels.conversions', 'Conversions') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 border rounded">
                                    <div class="h4 mb-0 text-success">
                                        {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($approvedCommission)) }}
                                    </div>
                                    <small class="text-muted">{{ labels('admin_labels.approved_commission', 'Approved Commission') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 border rounded">
                                    <div class="h4 mb-0 text-warning">
                                        {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($pendingCommission)) }}
                                    </div>
                                    <small class="text-muted">{{ labels('admin_labels.pending_commission', 'Pending Commission') }}</small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.generate_a_product_link', 'Generate a Product Link') }}</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="product_search_input"
                                    placeholder="{{ labels('admin_labels.search_a_product', 'Search a product…') }}"
                                    autocomplete="off">
                                <div id="product_search_results" class="list-group position-absolute w-100"
                                    style="z-index: 1000; max-height: 240px; overflow-y: auto; display: none;">
                                </div>
                            </div>
                            <input type="hidden" id="selected_product_id">
                            <div id="selected_product_preview" class="mt-2" style="display: none;">
                                <span class="badge bg-light text-dark border p-2" id="selected_product_name"></span>
                                <button class="btn btn-sm btn-primary ms-2" type="button" id="generate_product_link_btn">
                                    {{ labels('admin_labels.generate_link', 'Generate Link') }}
                                </button>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">{{ labels('admin_labels.your_product_links', 'Your Product Links') }}</label>
                            <div id="product_links_list"></div>
                        </div>
                    </div>
                </div>
                <div class="copyright mt-4">
                    Copyright © {{ date('Y') }} {{ $system_settings['app_name'] }}. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/iziToast.min.js') }}"></script>
    <script>
        function copyAffiliateLink() {
            var input = document.getElementById('affiliate_link_input');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(function() {
                iziToast.success({
                    title: 'Copied',
                    message: 'Affiliate link copied to clipboard',
                    position: 'topRight'
                });
            });
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                iziToast.success({ title: 'Copied', message: 'Link copied to clipboard', position: 'topRight' });
            });
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var searchTimeout = null;

        document.getElementById('product_search_input').addEventListener('input', function() {
            var query = this.value.trim();
            var resultsBox = document.getElementById('product_search_results');
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(function() {
                fetch('{{ route('affiliate.products.search') }}?search=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(res => {
                        resultsBox.innerHTML = '';
                        if (res.error || !res.data.length) {
                            resultsBox.style.display = 'none';
                            return;
                        }
                        res.data.forEach(function(product) {
                            var item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action';
                            item.textContent = product.name;
                            item.onclick = function(e) {
                                e.preventDefault();
                                document.getElementById('selected_product_id').value = product.id;
                                document.getElementById('selected_product_name').textContent = product.name;
                                document.getElementById('selected_product_preview').style.display = 'block';
                                document.getElementById('product_search_input').value = product.name;
                                resultsBox.style.display = 'none';
                            };
                            resultsBox.appendChild(item);
                        });
                        resultsBox.style.display = 'block';
                    });
            }, 300);
        });

        document.getElementById('generate_product_link_btn').addEventListener('click', function() {
            var productId = document.getElementById('selected_product_id').value;
            if (!productId) {
                return;
            }

            fetch('{{ route('affiliate.links.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ target_type: 'product', target_id: productId }),
                })
                .then(res => res.json())
                .then(res => {
                    if (res.error) {
                        iziToast.error({ title: 'Error', message: res.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    document.getElementById('product_search_input').value = '';
                    document.getElementById('selected_product_preview').style.display = 'none';
                    loadProductLinks();
                });
        });

        function loadProductLinks() {
            fetch('{{ route('affiliate.links.list') }}')
                .then(res => res.json())
                .then(res => {
                    var container = document.getElementById('product_links_list');
                    var productLinks = (res.data || []).filter(l => l.target_type === 'product');

                    if (!productLinks.length) {
                        container.innerHTML = '<p class="text-muted small mb-0">{{ labels('admin_labels.no_product_links_yet', 'No product links generated yet.') }}</p>';
                        return;
                    }

                    var baseUrl = '{{ url('/r') }}/';
                    container.innerHTML = productLinks.map(function(link) {
                        var url = baseUrl + link.code;
                        return '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">' +
                            '<span class="small text-truncate me-2">' + url + '</span>' +
                            '<button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard(\'' + url + '\')">' +
                            '{{ labels('admin_labels.copy', 'Copy') }}</button>' +
                            '</div>';
                    }).join('');
                });
        }

        loadProductLinks();
    </script>
</body>

</html>
