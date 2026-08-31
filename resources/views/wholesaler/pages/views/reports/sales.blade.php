@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.sales', 'Sales') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.sales', 'Sales')" :subtitle="labels(
        'wholesaler_labels.sales_subtitle',
        'Revenue and performance based on delivered orders',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.sales', 'Sales')]]" />

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.total_revenue', 'Total Revenue') }}</h6>
                    <h3>{{ number_format($totalRevenue, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.delivered_orders', 'Delivered Orders') }}</h6>
                    <h3>{{ $deliveredCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.pending_orders', 'Pending Orders') }}</h6>
                    <h3>{{ $pendingCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">{{ labels('wholesaler_labels.unpaid_amount', 'Unpaid Amount') }}</h6>
                    <h3>{{ number_format($unpaidAmount, 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card content-area p-4">
                <h5>{{ labels('wholesaler_labels.top_products', 'Top Products') }}</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ labels('admin_labels.name', 'Name') }}</th>
                            <th>{{ labels('wholesaler_labels.quantity', 'Quantity') }}</th>
                            <th>{{ labels('wholesaler_labels.revenue', 'Revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topProducts as $p)
                            <tr>
                                <td>{{ $p['name'] }}</td>
                                <td>{{ $p['qty'] }}</td>
                                <td>{{ number_format($p['revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">{{ labels('admin_labels.no_data_found', 'No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card content-area p-4">
                <h5>{{ labels('wholesaler_labels.top_buyers', 'Top Buyers') }}</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ labels('admin_labels.seller', 'Seller') }}</th>
                            <th>{{ labels('wholesaler_labels.orders', 'Orders') }}</th>
                            <th>{{ labels('wholesaler_labels.revenue', 'Revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topBuyers as $b)
                            <tr>
                                <td>{{ $b['name'] }}</td>
                                <td>{{ $b['orders_count'] }}</td>
                                <td>{{ number_format($b['revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">{{ labels('admin_labels.no_data_found', 'No data found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
