@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.manage_combo_products', 'Manage Combo Products') }}
@endsection
@section('content')
    <section class="main-content">
        <div class="row">
            <x-seller.breadcrumb :title="labels('admin_labels.manage_combo_products', 'Manage Combo Products')" :subtitle="labels(
                'admin_labels.track_and_manage_combo_products',
                'Track And Manage Combo Products',
            )" :breadcrumbs="[
                ['label' => labels('admin_labels.combo_products', 'Combo Products'), 'url' => route('seller.combo_products.index')],
                ['label' => labels('admin_labels.manage_products', 'Manage Products')],
            ]" />

            <section class="overview-data">
                <div class="card content-area p-4 ">
                    <div class="row align-items-center d-flex heading mb-5">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>{{ labels('admin_labels.manage_combo_products', 'Manage Combo Products') }}
                                    </h4>
                                </div>
                                <div class="col-md-6 d-flex justify-content-end ">
                                    <div class="input-group me-2 search-input-grp ">
                                        <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                        <input type="text" data-table="seller_combo_product_table"
                                            class="form-control searchInput" placeholder="Search...">
                                        <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                                    </div>
                                    <a class="btn me-2" id="tableFilter" data-bs-toggle="offcanvas"
                                        data-bs-target="#columnFilterOffcanvas" data-table="seller_combo_product_table"
                                        StatusFilter='true'><i class='bx bx-filter-alt'></i></a>
                                    <a class="btn me-2" id="tableRefresh" data-table="seller_combo_product_table"><i
                                            class='bx bx-refresh'></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="pt-0">
                                <div class="table-responsive">
                                    <table class='table' id="seller_combo_product_table" data-toggle="table"
                                        data-loading-template="loadingTemplate"
                                        data-url="{{ route('seller.combo_products.list') }}" data-click-to-select="true"
                                        data-side-pagination="server" data-pagination="true"
                                        data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false"
                                        data-show-columns="false" data-show-refresh="false" data-trim-on-search="false"
                                        data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                                        data-toolbar="" data-show-export="false" data-maintain-selected="true"
                                        data-query-params="queryParams">
                                        <thead>
                                            <tr>
                                                <th data-field="id" data-sortable="true">
                                                    {{ labels('admin_labels.id', 'ID') }}
                                                <th data-field="image" data-sortable="false">
                                                    {{ labels('admin_labels.image', 'Image') }}
                                                </th>
                                                <th data-field="title" data-disabled="1" data-sortable="false">
                                                    {{ labels('admin_labels.title', 'Title') }}
                                                </th>
                                                <th data-field="status" data-sortable="false">
                                                    {{ labels('admin_labels.status', 'Status') }}
                                                </th>
                                                <th data-field="operate" data-sortable='false'>
                                                    {{ labels('admin_labels.action', 'Action') }}</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection
