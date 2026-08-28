<!DOCTYPE html>
<html lang="en">


@include('seller.include_css')

<body>
    {{-- Bug fix (same as resources/views/admin/layout.blade.php): seller/home @include('Chatify::layouts.
    headLinks') partway through @yield('content'), which loads jQuery from an external CDN - the only jQuery
    available at that point, since this layout's own local copy (seller.include_script) only loads at the end
    of @yield('content'). If that CDN request is slow, blocked, or unreachable, every script assuming jQuery
    is already loaded breaks, including the dashboard's own rendering. Loading the local copy here
    guarantees $ / jQuery are always available first. --}}
    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>
    <div id="db-wrapper">
        <!-- navbar vertical -->
        <x-seller.side-bar />
        <!-- Page content -->
        <div id="page-content">
            <x-seller.header />
            <div class="container-fluid mt-5 px-6" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
                @yield('content')
            </div>
        </div>
    </div>
    <x-seller.footer />
    <!-- Scripts -->
    @include('seller.include_script')
</body>

</html>
