<!DOCTYPE html>
<html lang="en">

<meta name="csrf-token" content="{{ csrf_token() }}">

@include('admin.include_css')

<body>
    {{-- Bug fix: pages like admin/home @include('Chatify::layouts.headLinks') partway through @yield('content'),
    which loads jQuery from an external CDN (code.jquery.com) - the only jQuery available at that point, since
    this layout's own local copy (admin.include_script) only loads after </body>. If that CDN request is slow,
    blocked, or unreachable (ad-blockers, restrictive networks), every script on the page that assumes jQuery
    is already loaded - including the dashboard's own rendering - breaks. Loading the local copy here,
    immediately, guarantees $ / jQuery are always available before any page content runs, regardless of any
    external CDN's reachability. --}}
    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
    <div id="db-wrapper" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>

        <x-admin.side-bar />
        <div id="page-content">
            <x-admin.header />
            <div class="container-fluid mt-5 px-6" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
                @yield('content')
            </div>
        </div>
    </div>
    <x-admin.footer />
    <!-- Scripts -->
</body>
@include('admin.include_script')

</html>
