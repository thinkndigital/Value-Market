@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.affiliate_links', 'Affiliate Links') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.affiliate_links', 'Affiliate Links')" :subtitle="labels(
        'admin_labels.see_who_is_promoting_your_store_and_what_they_have_earned',
        'See Who Is Promoting Your Store and What They Have Earned',
    )" :breadcrumbs="[['label' => labels('admin_labels.affiliate_links', 'Affiliate Links')]]" />

    <section class="overview-data">
        <div class="card content-area p-4 ">
            <div class="row align-items-center d-flex heading mb-5">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-sm-12">
                            <h4>{{ labels('admin_labels.affiliate_links', 'Affiliate Links') }}
                            </h4>
                        </div>
                        <div class="col-sm-12 d-flex justify-content-end mt-md-2 mt-sm-2">
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_affiliate_links_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="admin_affiliate_links_table"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_affiliate_links_table" data-toggle="table"
                                data-loading-template="loadingTemplate" data-url="{{ route('admin.affiliate.links.list') }}"
                                data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-maintain-selected="true" data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        </th>
                                        <th data-field="affiliate_name" data-disabled="1" data-sortable="false">
                                            {{ labels('admin_labels.affiliate', 'Affiliate') }}
                                        </th>
                                        <th data-field="code" data-sortable="false">
                                            {{ labels('admin_labels.code', 'Code') }}
                                        </th>
                                        <th data-field="target_type" data-sortable="false">
                                            {{ labels('admin_labels.target', 'Target') }}
                                        </th>
                                        <th data-field="clicks_count" data-sortable="true">
                                            {{ labels('admin_labels.clicks', 'Clicks') }}
                                        </th>
                                        <th data-field="conversions_count" data-sortable="true">
                                            {{ labels('admin_labels.conversions', 'Conversions') }}
                                        </th>
                                        <th data-field="approved_commission" data-sortable="false">
                                            {{ labels('admin_labels.approved_commission', 'Approved Commission') }}
                                        </th>
                                        <th data-field="pending_commission" data-sortable="false">
                                            {{ labels('admin_labels.pending_commission', 'Pending Commission') }}
                                        </th>
                                        <th data-field="status" data-sortable="false">
                                            {{ labels('admin_labels.status', 'Status') }}
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
@endsection
