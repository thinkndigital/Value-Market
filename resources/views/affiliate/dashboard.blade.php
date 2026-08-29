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
    <style>
        body.affiliate-portal {
            background: var(--body-background);
        }

        .affiliate-topbar {
            background: var(--primary-theme-color);
            color: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
        }

        .affiliate-topbar .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .affiliate-topbar .brand img {
            height: 32px;
            width: auto;
        }

        .affiliate-topbar .brand span {
            font-weight: 600;
            letter-spacing: .3px;
        }

        .affiliate-topbar .user-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .affiliate-topbar .balance-chip {
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: var(--radius-lg);
            padding: 6px 14px;
            font-size: .85rem;
        }

        .affiliate-topbar .balance-chip b {
            color: var(--brand-gold);
        }

        .affiliate-wrap {
            max-width: 1080px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        .stat-card {
            background: #fff;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 18px;
            height: 100%;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-theme-color);
        }

        .stat-card .stat-value.success {
            color: var(--success-color);
        }

        .stat-card .stat-value.warning {
            color: var(--warning-color);
        }

        .panel-card {
            background: #fff;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 20px;
            margin-bottom: 20px;
        }

        .panel-card h6 {
            font-weight: 600;
            color: var(--primary-theme-color);
            margin-bottom: 14px;
        }

        .affiliate-table {
            width: 100%;
            font-size: .875rem;
        }

        .affiliate-table th {
            text-align: start;
            color: var(--Gray-600);
            font-weight: 600;
            padding: 8px 10px;
            border-bottom: 1px solid var(--Gray-200);
        }

        .affiliate-table td {
            padding: 10px;
            border-bottom: 1px solid var(--Gray-200);
        }

        .status-badge {
            border-radius: var(--radius-sm);
            padding: 3px 10px;
            font-size: .75rem;
            font-weight: 600;
        }

        .status-badge.approved {
            background: rgba(52, 168, 83, .12);
            color: var(--success-color);
        }

        .status-badge.pending {
            background: rgba(244, 137, 0, .12);
            color: var(--warning-color);
        }

        .status-badge.rejected,
        .status-badge.reversed {
            background: rgba(181, 32, 70, .12);
            color: var(--danger-color);
        }
    </style>
</head>

<body class="affiliate-portal">
    <div class="affiliate-topbar">
        <div class="brand">
            @php
                $store_logo =
                    !empty($system_settings['logo']) &&
                    file_exists(public_path(config('constants.MEDIA_PATH') . $system_settings['logo']))
                        ? app(MediaService::class)->getMediaImageUrl($system_settings['logo'])
                        : asset('assets/img/default_full_logo.png');
            @endphp
            <img src="{{ $store_logo }}" alt="logo">
            <span>{{ labels('admin_labels.my_affiliate_account', 'My Affiliate Account') }}</span>
        </div>
        <div class="user-box">
            <span class="balance-chip">
                {{ labels('admin_labels.wallet_balance', 'Wallet Balance') }}:
                <b id="wallet_balance_display">{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($balance)) }}</b>
            </span>
            <span class="small">{{ auth()->user()->username }}</span>
            <a href="{{ route('affiliate.logout') }}" class="btn btn-sm btn-outline-light">
                {{ labels('admin_labels.logout', 'Logout') }}</a>
        </div>
    </div>

    <div class="affiliate-wrap">
        <div class="row g-3 mb-1">
            <div class="col-6 col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value">{{ $link->clicks_count }}</div>
                    <small class="text-muted">{{ labels('admin_labels.clicks', 'Clicks') }}</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value">{{ $link->conversions_count }}</div>
                    <small class="text-muted">{{ labels('admin_labels.conversions', 'Conversions') }}</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value success">
                        {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($approvedCommission)) }}
                    </div>
                    <small class="text-muted">{{ labels('admin_labels.approved_commission', 'Approved Commission') }}</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-value warning">
                        {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($pendingCommission)) }}
                    </div>
                    <small class="text-muted">{{ labels('admin_labels.pending_commission', 'Pending Commission') }}</small>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-7">
                <div class="panel-card">
                    <h6>{{ labels('admin_labels.your_affiliate_link', 'Your Affiliate Link') }}</h6>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="affiliate_link_input" value="{{ $shareUrl }}" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyAffiliateLink()">
                            <i class='bx bx-copy'></i> {{ labels('admin_labels.copy', 'Copy') }}
                        </button>
                    </div>
                    <small class="text-muted">{{ labels('admin_labels.affiliate_code', 'Affiliate Code') }}:
                        {{ $link->code }}</small>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">{{ labels('admin_labels.available_products', 'Available Products') }}</h6>
                        <span class="text-muted small">{{ labels('admin_labels.links_ready_to_copy', 'Links are already generated - just copy.') }}</span>
                    </div>
                    <input type="text" class="form-control form-control-sm mb-2" id="catalog_search_input"
                        placeholder="{{ labels('admin_labels.search_products', 'Search products…') }}" autocomplete="off">
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
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

                    <hr class="my-3">

                    <h6>{{ labels('admin_labels.private_stores', 'Private Stores') }}</h6>
                    <p class="small text-muted mb-2">
                        {{ labels('admin_labels.private_stores_explainer', 'These sellers approve affiliates before their products show up above.') }}
                    </p>
                    <div id="private_stores_list"></div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="panel-card">
                    <h6>{{ labels('admin_labels.request_withdrawal', 'Request Withdrawal') }}</h6>
                    <p class="small text-muted mb-2">
                        {{ labels('admin_labels.available_balance', 'Available balance') }}:
                        <b id="withdrawal_available_balance">{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($balance)) }}</b>
                    </p>
                    <div class="mb-2">
                        <input type="number" min="1" step="0.01" class="form-control form-control-sm mb-2"
                            id="withdrawal_amount_input"
                            placeholder="{{ labels('admin_labels.amount', 'Amount') }}">
                        <input type="text" class="form-control form-control-sm mb-2" id="withdrawal_address_input"
                            placeholder="{{ labels('admin_labels.payment_address', 'Payment address (e.g. bank/IBAN or wallet)') }}">
                        <button class="btn btn-sm btn-primary w-100" type="button" id="withdrawal_submit_btn">
                            {{ labels('admin_labels.send_request', 'Send Request') }}
                        </button>
                    </div>
                </div>

                <div class="panel-card">
                    <h6>{{ labels('admin_labels.withdrawal_history', 'Withdrawal History') }}</h6>
                    <div class="table-responsive">
                        <table class="affiliate-table">
                            <thead>
                                <tr>
                                    <th>{{ labels('admin_labels.date', 'Date') }}</th>
                                    <th>{{ labels('admin_labels.amount', 'Amount') }}</th>
                                    <th>{{ labels('admin_labels.status', 'Status') }}</th>
                                </tr>
                            </thead>
                            <tbody id="withdrawal_history_body">
                                <tr>
                                    <td colspan="3" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <h6>{{ labels('admin_labels.commission_history', 'Commission History') }}</h6>
            <div class="table-responsive">
                <table class="affiliate-table">
                    <thead>
                        <tr>
                            <th>{{ labels('admin_labels.order', 'Order') }}</th>
                            <th>{{ labels('admin_labels.order_total', 'Order Total') }}</th>
                            <th>{{ labels('admin_labels.commission', 'Commission') }}</th>
                            <th>{{ labels('admin_labels.status', 'Status') }}</th>
                            <th>{{ labels('admin_labels.date', 'Date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="conversions_history_body">
                        <tr>
                            <td colspan="5" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="copyright text-center text-muted small mt-4">
            Copyright © {{ date('Y') }} {{ $system_settings['app_name'] }}. All rights reserved.
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
                return '<tr>' +
                    '<td><img src="' + row.image + '" width="32" height="32" style="object-fit:cover;border-radius:4px;" class="me-2">' + row.name + '</td>' +
                    '<td>' + (row.store_name || '') + '</td>' +
                    '<td>' + rate + '</td>' +
                    '<td><button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard(\'' + row.link_url + '\')">' +
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

        var privateStoreStatusLabels = {
            approved: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            pending: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            rejected: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
        };

        function requestStoreAccess(storeId, btn) {
            btn.disabled = true;
            fetch('{{ route('affiliate.stores.request') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ store_id: storeId }),
                })
                .then(res => res.json())
                .then(res => {
                    if (res.error) {
                        iziToast.error({ title: 'Error', message: res.message, position: 'topRight' });
                        btn.disabled = false;
                        return;
                    }
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    loadPrivateStores();
                });
        }

        function loadPrivateStores() {
            fetch('{{ route('affiliate.stores.list') }}')
                .then(res => res.json())
                .then(res => {
                    var container = document.getElementById('private_stores_list');
                    var stores = res.data || [];

                    if (!stores.length) {
                        container.innerHTML = '<p class="text-muted small mb-0">{{ labels('admin_labels.no_private_stores_yet', 'No private stores yet.') }}</p>';
                        return;
                    }

                    container.innerHTML = stores.map(function(store) {
                        var action;
                        if (!store.request_status) {
                            action = '<button class="btn btn-sm btn-outline-primary" onclick="requestStoreAccess(' + store.store_id + ', this)">' +
                                '{{ labels('admin_labels.request_to_join', 'Request to Join') }}</button>';
                        } else {
                            var status = privateStoreStatusLabels[store.request_status] || { text: store.request_status, cls: 'pending' };
                            action = '<span class="status-badge ' + status.cls + '">' + status.text + '</span>';
                        }
                        return '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">' +
                            '<span class="small">' + store.store_name + '</span>' + action + '</div>';
                    }).join('');
                });
        }

        var conversionStatusLabels = {
            approved: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            pending: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            rejected: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
            reversed: { text: '{{ labels('admin_labels.reversed', 'Reversed') }}', cls: 'reversed' },
        };

        function loadConversionsHistory() {
            fetch('{{ route('affiliate.conversions.list') }}')
                .then(res => res.json())
                .then(res => {
                    var body = document.getElementById('conversions_history_body');
                    var rows = res.data || [];

                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="5" class="text-muted small">' +
                            '{{ labels('admin_labels.no_commission_history_yet', 'No commission history yet.') }}</td></tr>';
                        return;
                    }

                    body.innerHTML = rows.map(function(row) {
                        var status = conversionStatusLabels[row.status] || { text: row.status, cls: 'pending' };
                        return '<tr>' +
                            '<td>#' + row.order_id + '</td>' +
                            '<td>' + Number(row.order_total).toFixed(2) + '</td>' +
                            '<td>' + Number(row.commission_amount).toFixed(2) + '</td>' +
                            '<td><span class="status-badge ' + status.cls + '">' + status.text + '</span></td>' +
                            '<td>' + (row.created_at || '').substring(0, 10) + '</td>' +
                            '</tr>';
                    }).join('');
                });
        }

        var withdrawalStatusLabels = {
            0: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            1: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            2: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
        };

        function loadWithdrawalHistory() {
            fetch('{{ route('affiliate.withdrawal.history') }}')
                .then(res => res.json())
                .then(res => {
                    var body = document.getElementById('withdrawal_history_body');
                    var rows = res.data || [];

                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="3" class="text-muted small">' +
                            '{{ labels('admin_labels.no_withdrawal_requests_yet', 'No withdrawal requests yet.') }}</td></tr>';
                        return;
                    }

                    body.innerHTML = rows.map(function(row) {
                        var status = withdrawalStatusLabels[row.status] || { text: row.status, cls: 'pending' };
                        return '<tr>' +
                            '<td>' + (row.created_at || '').substring(0, 10) + '</td>' +
                            '<td>' + Number(row.amount_requested).toFixed(2) + '</td>' +
                            '<td><span class="status-badge ' + status.cls + '">' + status.text + '</span></td>' +
                            '</tr>';
                    }).join('');
                });
        }

        document.getElementById('withdrawal_submit_btn').addEventListener('click', function() {
            var amount = document.getElementById('withdrawal_amount_input').value;
            var address = document.getElementById('withdrawal_address_input').value;

            if (!amount || Number(amount) <= 0 || !address.trim()) {
                iziToast.error({
                    title: 'Error',
                    message: '{{ labels('admin_labels.enter_amount_and_address', 'Enter a valid amount and payment address.') }}',
                    position: 'topRight'
                });
                return;
            }

            fetch('{{ route('affiliate.withdrawal.request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ amount: amount, payment_address: address }),
                })
                .then(res => res.json())
                .then(res => {
                    if (res.error) {
                        iziToast.error({ title: 'Error', message: res.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    document.getElementById('withdrawal_amount_input').value = '';
                    document.getElementById('withdrawal_address_input').value = '';
                    if (res.balance !== undefined) {
                        var formatted = Number(res.balance).toFixed(2);
                        document.getElementById('wallet_balance_display').textContent = formatted;
                        document.getElementById('withdrawal_available_balance').textContent = formatted;
                    }
                    loadWithdrawalHistory();
                });
        });

        loadAvailableProducts();
        loadPrivateStores();
        loadConversionsHistory();
        loadWithdrawalHistory();
    </script>
</body>

</html>
