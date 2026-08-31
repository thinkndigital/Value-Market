@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.my_buyers', 'My Buyers') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.my_buyers', 'My Buyers')" :subtitle="labels(
        'wholesaler_labels.my_buyers_subtitle',
        'Sellers who have ordered from your catalog',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.my_buyers', 'My Buyers')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="table-responsive">
                    <table class="table" id="wholesaler_clients_table" data-toggle="table"
                        data-url="{{ route('wholesaler.clients.list') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50, 100]" data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="seller">{{ labels('admin_labels.seller', 'Seller') }}</th>
                                <th data-field="mobile">{{ labels('admin_labels.mobile', 'Mobile') }}</th>
                                <th data-field="orders_count">{{ labels('wholesaler_labels.orders', 'Orders') }}</th>
                                <th data-field="total_spent">{{ labels('wholesaler_labels.total_spent', 'Total Spent') }}</th>
                                <th data-field="last_order_at">{{ labels('wholesaler_labels.last_order', 'Last Order') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
