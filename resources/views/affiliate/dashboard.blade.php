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

                    <h6>{{ labels('admin_labels.generate_a_product_link', 'Generate a Product Link') }}</h6>
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

                    <div class="mt-3">
                        <label class="form-label small text-muted mb-1">{{ labels('admin_labels.your_product_links', 'Your Product Links') }}</label>
                        <div id="product_links_list"></div>
                    </div>
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

        loadProductLinks();
        loadConversionsHistory();
        loadWithdrawalHistory();
    </script>
</body>

</html>
