<html lang="en">
@php
use App\Services\MediaService;
@endphp
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ app(MediaService::class)->getMediaImageUrl($system_settings['favicon']) }}">
    <title>{{ labels('wholesaler_labels.wholesaler_register', 'Wholesaler Registration') }} | {{ $system_settings['app_name'] }}</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('assets/admin/css/iziToast.css') }}">
    <link id="pagestyle" href="{{ asset('/assets/css/argon-dashboard.css?v=2.0.4') }}" rel="stylesheet" />
    <link id="pagestyle" href="{{ asset('/assets/admin/css/sweetalert2.min.css') }}" rel="stylesheet" />
    <link id="pagestyle" href="{{ asset('/assets/admin/css/style.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/boxicons/css/boxicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/custom/custom.css') }}">
</head>

<body class="">
    <div class="page-header min-vh-100">
        <div class="col-md-12">
            <div class="d-flex flex-column justify-content-center align-items-center">
                <div class="card" style="max-width: 640px; width: 100%;">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center text-center">
                            <h1 class="font-weight-bolder">{{ labels('wholesaler_labels.wholesaler_register', 'Become a Wholesaler') }}</h1>
                            <p class="mb-4 order_page_title">{{ labels('wholesaler_labels.register_subtitle', 'List your wholesale catalog for sellers on the platform to browse and import') }}</p>
                        </div>

                        <form action="{{ route('wholesaler.register.store') }}" class="submit_form" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.name', 'Name') }}<span class='text-asterisks'>*</span></label>
                                    <input type="text" name="name" id="name" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('wholesaler_labels.business_name', 'Business Name') }}<span class='text-asterisks'>*</span></label>
                                    <input type="text" name="business_name" id="business_name" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.mobile', 'Mobile') }}<span class='text-asterisks'>*</span></label>
                                    <input type="text" name="mobile" id="mobile" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.email', 'Email') }}<span class='text-asterisks'>*</span></label>
                                    <input type="email" name="email" id="email" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.password', 'Password') }}<span class='text-asterisks'>*</span></label>
                                    <input type="password" name="password" id="password" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.confirm_password', 'Confirm Password') }}<span class='text-asterisks'>*</span></label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.address', 'Address') }}<span class='text-asterisks'>*</span></label>
                                    <input type="text" name="address" id="address" class="form-control" required>
                                    <div class="text-danger"></div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">{{ labels('admin_labels.description', 'Description') }}</label>
                                    <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                                    <div class="text-danger"></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary submit_button">{{ labels('admin_labels.register', 'Register') }}</button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="{{ route('wholesaler.login') }}">{{ labels('wholesaler_labels.already_have_account', 'Already have an account? Sign in') }}</a>
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
    <script src="{{ asset('/assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/iziToast.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('/assets/js/boxicons.js') }}"></script>
    <script>
        // custom.js's ".submit_form" handler calls tinymce.triggerSave() unconditionally - this page has no
        // tinymce editor on it, so stub it out rather than pulling in the whole tinymce bundle for that.
        var tinymce = { triggerSave: function () {} };
    </script>
    <script src="{{ asset('assets/admin/custom/custom.js') }}"></script>
</body>

</html>
