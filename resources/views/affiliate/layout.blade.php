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
    <title>@yield('title') | {{ $system_settings['app_name'] }}</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <link href="{{ asset('/assets/css/nucleo-icons.css') }}" rel="stylesheet" />
    <link href="{{ asset('/assets/css/nucleo-svg.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/admin/css/iziToast.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fontawesome/css/all.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/boxicons/css/boxicons.min.css') }}">
    {{-- style.min.css's fixed-sidebar layout (#db-wrapper/.navbar-vertical/#page-content) builds on
    Bootstrap's own grid/flex classes - without bootstrap.min.css loaded first, the sidebar renders as a
    plain block element instead of a fixed column, pushing all page content down below it. Same include
    order as seller/include_css.blade.php, which is the proven-working reference. --}}
    <link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap.min.css') }}">
    <link id="pagestyle" href="{{ asset('/assets/css/argon-dashboard.css?v=2.0.4') }}" rel="stylesheet" />
    <link id="pagestyle" href="{{ asset('/assets/admin/css/style.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('/assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/custom/custom.css') }}">
    <style>
        .affiliate-content-wrap {
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

        .affiliate-table tbody tr:hover {
            background: var(--body-background);
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
    @yield('head')
</head>

<body>
    <div id="db-wrapper" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
        <x-affiliate.side-bar />
        <div id="page-content">
            <div class="d-flex justify-content-end align-items-center p-3 border-bottom bg-white">
                <span class="text-muted small me-3">
                    {{ labels('admin_labels.wallet_balance', 'Wallet Balance') }}:
                    <b>{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal(auth()->user()->balance ?? 0)) }}</b>
                </span>
                <span class="small me-3">{{ auth()->user()->username }}</span>
                <a href="{{ route('affiliate.logout') }}" class="btn btn-sm btn-outline-secondary">
                    {{ labels('admin_labels.logout', 'Logout') }}</a>
            </div>
            <div class="affiliate-content-wrap">
                @yield('content')
            </div>
        </div>
    </div>

    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/iziToast.min.js') }}"></script>
    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                iziToast.success({ title: 'Copied', message: 'Link copied to clipboard', position: 'topRight' });
            });
        }
    </script>
    @yield('scripts')
</body>

</html>
