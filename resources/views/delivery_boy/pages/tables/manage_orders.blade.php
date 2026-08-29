@extends('delivery_boy/layout')
@section('title')
    {{ labels('admin_labels.manage_orders', 'Manage Orders') }}
@endsection
@section('content')
    <section class="main-content">
        <x-delivery_boy.breadcrumb :title="labels('admin_labels.manage_orders', 'Manage Orders')" :subtitle="labels('admin_labels.all_information_about_orders', 'All Information About Orders')" :breadcrumbs="[['label' => labels('admin_labels.orders', 'Orders')]]" />

        <section class="overview-data">
            <div class="card content-area p-4 ">
                <div class="row align-items-center d-flex heading mb-5">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>{{ labels('admin_labels.manage_orders', 'Manage Orders') }}
                                </h4>
                            </div>
                            <div class="col-md-6 d-flex justify-content-end ">
                                <div class="input-group me-2 search-input-grp ">
                                    <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                    <input type="text" data-table="delivery_boy_orders_table"
                                        class="form-control searchInput" placeholder="Search...">
                                    <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                                </div>
                                <a class="btn me-2" id="tableRefresh" data-table="delivery_boy_orders_table"><i
                                        class='bx bx-refresh'></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="pt-0">
                            <div class="table-responsive">
                                <table class='table' id="delivery_boy_orders_table" data-toggle="table"
                                    data-loading-template="loadingTemplate"
                                    data-url="{{ route('delivery_boy.view_parcels') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false"
                                    data-show-columns="false" data-show-refresh="false" data-trim-on-search="false"
                                    data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                                    data-toolbar="" data-show-export="false" data-maintain-selected="true"
                                    data-query-params="queryParams">
                                    <thead>
                                        <tr>
                                            <th data-field="order_link" data-sortable="false">
                                                {{ labels('admin_labels.order_id', 'Order ID') }}
                                            </th>
                                            <th data-field="username" data-disabled="1" data-sortable="false">
                                                {{ labels('admin_labels.user_name', 'User Name') }}
                                            </th>
                                            <th data-field="product_name" data-sortable="false">
                                                {{ labels('admin_labels.product_name', 'Product Name') }}
                                            </th>
                                            <th data-field="payment_method" data-sortable="false">
                                                {{ labels('admin_labels.payment_method', 'Payment Method') }}
                                            </th>
                                            <th data-field="status" data-sortable="false">
                                                {{ labels('admin_labels.status', 'Status') }}
                                            </th>
                                            <th data-field="operate" data-sortable="false">
                                                {{ labels('admin_labels.action', 'Action') }}
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </section>
@endsection
