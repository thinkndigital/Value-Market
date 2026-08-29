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
    </script>
</body>

</html>
