@extends('delivery_boy/layout')
@section('title')
    {{ labels('admin_labels.fund_transfer', 'Fund Transfer') }}
@endsection
@section('content')
    <section class="main-content">
        <x-delivery_boy.breadcrumb :title="labels('admin_labels.fund_transfer', 'Fund Transfer')" :subtitle="labels(
            'admin_labels.track_your_cash_fund_transfers',
            'Track Your Cash Fund Transfers',
        )" :breadcrumbs="[['label' => labels('admin_labels.fund_transfer', 'Fund Transfer')]]" />

        <section class="overview-data">
            <div class="card content-area p-4 ">
                <div class="row align-items-center d-flex heading mb-5">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>{{ labels('admin_labels.fund_transfer', 'Fund Transfer') }}
                                </h4>
                            </div>
                            <div class="col-md-6 d-flex justify-content-end ">
                                <div class="input-group me-2 search-input-grp ">
                                    <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                    <input type="text" data-table="delivery_boy_fund_transfer_table"
                                        class="form-control searchInput" placeholder="Search...">
                                    <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                                </div>
                                <a class="btn me-2" id="tableRefresh" data-table="delivery_boy_fund_transfer_table"><i
                                        class='bx bx-refresh'></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="pt-0">
                            <div class="table-responsive">
                                <table class='table' id="delivery_boy_fund_transfer_table" data-toggle="table"
                                    data-loading-template="loadingTemplate"
                                    data-url="{{ route('delivery_boy.fund.transfers.list') }}"
                                    data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false"
                                    data-show-columns="false" data-show-refresh="false" data-trim-on-search="false"
                                    data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                                    data-toolbar="" data-show-export="false" data-maintain-selected="true"
                                    data-query-params="queryParams">
                                    <thead>
                                        <tr>
                                            <th data-field="id" data-sortable="true">
                                                {{ labels('admin_labels.id', 'ID') }}
                                            <th data-field="name" data-disabled="1" data-sortable="false">
                                                {{ labels('admin_labels.name', 'Name') }}
                                            </th>
                                            <th data-field="mobile" data-sortable="false">
                                                {{ labels('admin_labels.mobile', 'Mobile') }}
                                            </th>
                                            <th data-field="opening_balance" data-sortable="false">
                                                {{ labels('admin_labels.opening_balance', 'Opening Balance') }}
                                            </th>
                                            <th data-field="closing_balance" data-sortable="false">
                                                {{ labels('admin_labels.closing_balance', 'Closing Balance') }}
                                            </th>
                                            <th data-field="amount" data-sortable="false">
                                                {{ labels('admin_labels.amount', 'Amount') }}
                                            </th>
                                            <th data-field="status" data-sortable="false">
                                                {{ labels('admin_labels.status', 'Status') }}
                                            </th>
                                            <th data-field="message" data-sortable="false">
                                                {{ labels('admin_labels.message', 'Message') }}
                                            </th>
                                            <th data-field="date" data-sortable="false">
                                                {{ labels('admin_labels.date', 'Date') }}
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
