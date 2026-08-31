@extends('wholesaler.layout')
@section('title')
    {{ labels('admin_labels.dashboard', 'Dashboard') }}
@endsection
@section('content')
    <div class="col-md-12">
        <h3>{{ labels('wholesaler_labels.welcome', 'Welcome') }}, {{ $wholesaler->business_name }}</h3>
        <p class="text-muted">{{ labels('wholesaler_labels.dashboard_subtitle', 'Manage your wholesale catalog, orders, and sellers.') }}</p>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.total_products', 'Total Products') }}</h6>
                    <h3>{{ $totalProducts }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.active_products', 'Active Products') }}</h6>
                    <h3>{{ $activeProducts }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.pending_approval', 'Pending Approval') }}</h6>
                    <h3>{{ $pendingApproval }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.sellers_importing', 'Sellers Buying From You') }}</h6>
                    <h3>{{ $sellersImporting }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.pending_orders', 'Pending Orders') }}</h6>
                    <h3>{{ $pendingOrders }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.total_revenue', 'Total Revenue') }}</h6>
                    <h3>{{ number_format($totalRevenue, 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12 d-flex gap-2 flex-wrap">
            <a href="{{ route('wholesaler.products.index') }}" class="btn btn-primary">
                <i class="bx bx-package"></i> {{ labels('wholesaler_labels.manage_products', 'Manage My Products') }}
            </a>
            <a href="{{ route('wholesaler.orders.index') }}" class="btn btn-outline-dark">
                <i class="bx bx-cart"></i> {{ labels('wholesaler_labels.orders', 'Orders') }}
            </a>
            <a href="{{ route('wholesaler.stock.index') }}" class="btn btn-outline-dark">
                <i class="bx bx-box"></i> {{ labels('wholesaler_labels.stock', 'Stock') }}
            </a>
            <a href="{{ route('wholesaler.reports.sales') }}" class="btn btn-outline-dark">
                <i class="bx bx-line-chart"></i> {{ labels('wholesaler_labels.sales', 'Sales') }}
            </a>
            <a href="{{ route('wholesaler.clients.index') }}" class="btn btn-outline-dark">
                <i class="bx bx-group"></i> {{ labels('wholesaler_labels.my_buyers', 'My Buyers') }}
            </a>
        </div>
    </div>
@endsection
