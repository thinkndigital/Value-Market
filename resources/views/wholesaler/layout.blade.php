    <!DOCTYPE html>
    <html lang="en">


    @include('wholesaler.include_css')

    <body>
        <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
        <div id="db-wrapper" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>

            <x-wholesaler.side-bar />
            <!-- Page content -->
            <div id="page-content">
                <x-wholesaler.header />
                <!-- Container fluid -->

                <div class="container-fluid mt-5 px-6" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
                    @yield('content')
                </div>
            </div>
        </div>
        <x-wholesaler.footer />
        <!-- Scripts -->
    </body>
    @include('wholesaler.include_script')
    @stack('scripts')

    </html>
