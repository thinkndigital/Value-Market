<html lang="en">
@php
    use App\Services\MediaService;
@endphp

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($system_settings != null)
        <link rel="icon" type="image/png"
            href="{{ app(MediaService::class)->getMediaImageUrl($system_settings['favicon']) }}">
    @endif
    <title>Affiliate Login | {{ $system_settings['app_name'] }}</title>
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
            <div class="d-flex flex-column justify-content-center align-items-center">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center text-center">
                            <div class="login-img-box mb-3">
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
                            <h1 class="font-weight-bolder">
                                {{ labels('admin_labels.affiliate_login', 'Affiliate Login') }}</h1>
                            <p class="mb-4 order_page_title">Sign in with your account to see your affiliate link and
                                earnings</p>
                        </div>

                        <form class="form_authentication" action="{{ route('affiliate.authenticate') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label class="form-label"
                                    for="">{{ labels('admin_labels.mobile', 'Mobile') }}<span
                                        class='text-asterisks text-sm'>*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class='bx bx-mobile-alt fs-4'></i>
                                    </span>
                                    <input type="text" maxlength="16" class="form-control" name="mobile"
                                        placeholder="Enter Your Mobile Number">
                                </div>
                            </div>
                            <label class="form-label"
                                for="">{{ labels('admin_labels.password', 'Password') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class='bx bx-lock fs-4'></i>
                                </span>
                                <input type="password" class="form-control" name="password" id="show_password"
                                    placeholder="Enter Your Password">
                                <span class="input-group-text password_show" onclick="show_password()">
                                    <i class='bx bx-show fs-4'></i>
                                </span>
                                <span class="input-group-text low_vision" onclick="show_password()">
                                    <i class='bx bx-low-vision fs-4'></i>
                                </span>
                            </div>

                            <button type="submit"
                                class="btn btn-lg btn-primary login_button w-100 mt-4 mb-0">{{ labels('admin_labels.sign_in', 'Sign In') }}</button>
                        </form>
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
    <script src="{{ asset('/assets/js/argon-dashboard.min.js?v=2.0.4') }}"></script>
    <script src="{{ asset('assets/admin/custom/custom.js') }}"></script>
</body>

</html>
