@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.product_deliverability', 'Product Deliverability') }}
@endsection
@section('content')
    <section class="main-content">
        <div class="row">
            <x-seller.breadcrumb :title="labels('admin_labels.product_deliverability', 'Product Deliverability')" :subtitle="labels(
                'admin_labels.manage_which_zones_your_products_deliver_to',
                'Manage Which Zones Your Products Deliver To',
            )" :breadcrumbs="[['label' => labels('admin_labels.product_deliverability', 'Product Deliverability')]]" />

            <input type="hidden" id="seller_id" value="{{ $seller_id ?? '' }}">

            {{-- Deliverability modal --}}
            <div class="modal fade" id="deliverabilityModal" tabindex="-1" role="dialog"
                aria-labelledby="deliverabilityModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deliverabilityModalLabel">
                                {{ labels('admin_labels.update_deliverability', 'Update Deliverability') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="deliverabilityForm">
                            @csrf
                            <input type="hidden" id="product_id" name="product_id">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="deliverable_type"
                                        class="form-label">{{ labels('admin_labels.deliverable_type', 'Deliverable Type') }}</label>
                                    <select class="form-select" name="deliverable_type" id="deliverable_type">
                                        <option value="0">None</option>
                                        <option value="1">All</option>
                                        <option value="2">Specific</option>
                                    </select>
                                </div>
                                <div class="form-group mt-3">
                                    <label for="deliverable_zones"
                                        class="form-label">{{ labels('admin_labels.deliverable_zones', 'Deliverable Zones') }}</label>
                                    <select name="deliverable_zones[]" class="form-select w-100" multiple
                                        id="deliverable_zones" disabled>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn reset-btn" data-bs-dismiss="modal"
                                    aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                                <button type="submit"
                                    class="btn btn-primary">{{ labels('admin_labels.update', 'Update') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <section class="overview-data">
                <div class="card content-area p-4 ">
                    <div class="row align-items-center d-flex heading mb-5">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>{{ labels('admin_labels.product_deliverability', 'Product Deliverability') }}
                                    </h4>
                                </div>
                                <div class="col-md-6 d-flex justify-content-end">
                                    <button type="button"
                                        class="btn btn-dark me-2 bulk_update_deliverability_data"
                                        data-table-id="seller_deliverability_table">{{ labels('admin_labels.update_deliverability', 'Update Deliverability') }}</button>
                                    <div class="input-group me-2 search-input-grp">
                                        <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                        <input type="text" data-table="seller_deliverability_table"
                                            class="form-control searchInput" placeholder="Search...">
                                        <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                                    </div>
                                    <a class="btn me-2" id="tableRefresh" data-table="seller_deliverability_table"><i
                                            class='bx bx-refresh'></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="pt-0">
                                <div class="table-responsive">
                                    <table class='table' id="seller_deliverability_table" data-toggle="table"
                                        data-loading-template="loadingTemplate"
                                        data-url="{{ route('seller.product.deliverability.list') }}"
                                        data-click-to-select="true" data-side-pagination="server"
                                        data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                                        data-search="false" data-show-columns="false" data-show-refresh="false"
                                        data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                                        data-mobile-responsive="true" data-toolbar="" data-show-export="false"
                                        data-maintain-selected="true" data-query-params="queryParams">
                                        <thead>
                                            <tr>
                                                <th data-checkbox="true" data-field="delete-checkbox">
                                                    <input name="select_all" type="checkbox">
                                                </th>
                                                <th data-field="id" data-sortable="true">
                                                    {{ labels('admin_labels.id', 'ID') }}
                                                </th>
                                                <th data-field="image" data-sortable="false">
                                                    {{ labels('admin_labels.image', 'Image') }}
                                                </th>
                                                <th data-field="name" data-disabled="1" data-sortable="false">
                                                    {{ labels('admin_labels.name', 'Name') }}
                                                </th>
                                                <th data-field="deliverable_type" data-sortable="false">
                                                    {{ labels('admin_labels.deliverable_type', 'Deliverable Type') }}
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
        </div>
    </section>
@endsection
