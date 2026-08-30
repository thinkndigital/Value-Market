<!DOCTYPE html>
<html lang="en">

@include('seller.include_css')

<body class="pos-fullscreen-body">
    {{-- Same jQuery-availability-order fix as seller/layout.blade.php's own docblock explains. --}}
    <script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>

    {{--
        32-phase SaaS brief, Phase 9/10 (docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md): a dedicated
        full-screen shell for the POS page - no sidebar/header chrome eating into a cashier's screen space,
        a slim top bar instead (store name + exit + fullscreen toggle), and the page itself fills the
        viewport with no outer scroll (the two panels below scroll independently). seller/pos.blade.php's
        own content/JS is completely unchanged - this only changes the chrome it's rendered inside.
    --}}
    <style>
        html, body.pos-fullscreen-body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        .pos-fullscreen-topbar {
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
        }
        .pos-fullscreen-content {
            height: calc(100% - 56px);
            overflow-y: auto;
            padding: 16px;
        }
        /* Below lg (992px, real tablet/POS-hardware territory), the products grid and cart panel each get
           their own independently-scrolling column instead of the page stacking them full-height, which
           used to force a cashier to scroll past the whole product grid just to reach the cart/place-order
           button. Above lg, this matches the two-column layout the page already used at xxl and wider. */
        @media (min-width: 992px) {
            .pos-fullscreen-content {
                display: flex;
                gap: 16px;
                align-items: flex-start;
            }
            .pos-fullscreen-content > .pos-data {
                display: flex;
                flex-wrap: nowrap;
                width: 100%;
                gap: 16px;
            }
            .pos-fullscreen-content .pos-products-panel {
                flex: 1 1 62%;
                min-width: 0;
                max-height: calc(100vh - 56px - 32px);
                overflow-y: auto;
            }
            .pos-fullscreen-content .pos-product-cart-detail {
                flex: 1 1 38%;
                min-width: 320px;
                max-height: calc(100vh - 56px - 32px);
                overflow-y: auto;
            }
        }
    </style>

    <div class="pos-fullscreen-topbar">
        <div class="d-flex align-items-center gap-2">
            <strong>{{ $system_settings['app_name'] ?? 'POS' }}</strong>
            <span class="text-muted small ms-2">{{ labels('admin_labels.point_of_sale', 'Point Of Sale') }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pos_fullscreen_toggle">
                <i class='bx bx-fullscreen'></i> {{ labels('admin_labels.fullscreen', 'Fullscreen') }}
            </button>
            <a href="{{ route('seller.home') }}" class="btn btn-sm btn-outline-danger">
                <i class='bx bx-exit'></i> {{ labels('admin_labels.exit_pos', 'Exit POS') }}
            </a>
        </div>
    </div>

    <div class="pos-fullscreen-content" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
        @yield('content')
    </div>

    <!-- Scripts -->
    @include('seller.include_script')
    <script>
        document.getElementById('pos_fullscreen_toggle')?.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen?.();
            } else {
                document.exitFullscreen?.();
            }
        });
    </script>
</body>

</html>
