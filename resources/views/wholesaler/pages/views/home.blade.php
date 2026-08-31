@extends('wholesaler.layout')
@section('title')
    {{ labels('admin_labels.dashboard', 'Dashboard') }}
@endsection
@section('content')
    <div class="col-md-12">
        <h3>{{ labels('wholesaler_labels.welcome', 'Welcome') }}, {{ $wholesaler->business_name }}</h3>
        <p class="text-muted">{{ labels('wholesaler_labels.dashboard_subtitle', 'Manage your wholesale catalog and see who is importing it.') }}</p>
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
                    <h6 class="text-muted">{{ labels('wholesaler_labels.sellers_importing', 'Sellers Importing') }}</h6>
                    <h3>{{ $sellersImporting }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <a href="{{ route('wholesaler.products.index') }}" class="btn btn-primary">
                <i class="bx bx-package"></i> {{ labels('wholesaler_labels.manage_products', 'Manage My Products') }}
            </a>
        </div>
    </div>
@endsection
