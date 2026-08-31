<html lang="en">
@php
use App\Services\MediaService;
@endphp
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ app(MediaService::class)->getMediaImageUrl($system_settings['favicon']) }}">
    <title>{{ labels('wholesaler_labels.wholesaler_login', 'Wholesaler Login') }} | {{ $system_settings['app_name'] }}</title>
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('assets/admin/css/iziToast.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap-table.min.css') }}">
    <link id="pagestyle" href="{{ asset('/assets/css/argon-dashboard.css?v=2.0.4') }}" rel="stylesheet" />
    <link id="pagestyle" href="{{ asset('/assets/admin/css/sweetalert2.min.css') }}" rel="stylesheet" />
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
                                <img src="{{ config('app.url') }}storage/{{ $system_settings['logo'] }}"
                                    alt="logo" class="img-fluid">
                            </div>
                            <h1 class="font-weight-bolder">
                                {{ labels('wholesaler_labels.wholesaler_login', 'Wholesaler Login') }}
                            </h1>
                            <p class="mb-4 order_page_title">{{ labels('wholesaler_labels.login_subtitle', 'Sign in to manage your wholesale catalog') }}</p>
                        </div>

                        <form class="form_authentication" action="{{ route('wholesaler.authenticate') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label class="form-label" for="">{{ labels('admin_labels.mobile', 'Mobile') }}<span class='text-asterisks text-sm'>*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class='bx bx-mobile-alt fs-4'></i></span>
                                    <input type="text" maxlength="16" class="form-control copied_mobile" name="mobile" placeholder="Enter Your Mobile Number">
                                </div>
                            </div>
                            <label class="form-label" for="">{{ labels('admin_labels.password', 'Password') }}<span class='text-asterisks text-sm'>*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class='bx bx-lock fs-4'></i></span>
                                <input type="password" class="form-control copied_password" name="password" id="show_password" placeholder="Enter Your Password">
                                <span class="input-group-text password_show" onclick="show_password()"><i class='bx bx-show fs-4'></i></span>
                                <span class="input-group-text low_vision" onclick="show_password()"><i class='bx bx-low-vision fs-4'></i></span>
                            </div>

                            <button type="submit" class="btn btn-lg btn-primary login_button w-100 mt-4 mb-0">{{ labels('admin_labels.sign_in', 'Sign In') }}</button>

                            <div class="text-center mt-3">
                                <a href="{{ route('wholesaler.register') }}">{{ labels('wholesaler_labels.no_account_register', "Don't have an account? Register as a wholesaler") }}</a>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="copyright mt-4">
                    Copyright © {{ date('Y') }} <a href="{{ config('app.url') }}">{{ $system_settings['app_name'] }}.</a> All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
    <script src="{{ asset('/assets/admin/js/jquery.js') }}"></script>
    <script src="{{ asset('/assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/iziToast.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/bootstrap-table.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('/assets/js/boxicons.js') }}"></script>
    <script src="{{ asset('/assets/js/argon-dashboard.min.js?v=2.0.4') }}"></script>
    <script src="{{ asset('assets/admin/custom/custom.js') }}"></script>
</body>

</html>
