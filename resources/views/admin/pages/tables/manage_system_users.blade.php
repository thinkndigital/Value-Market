@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.manage_system_users', 'Manage System Users') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.manage_system_users', 'Manage System Users')" :subtitle="labels(
        'admin_labels.manage_admin_editor_and_super_admin_accounts',
        'Manage Admin, Editor and Super Admin accounts',
    )" :breadcrumbs="[['label' => labels('admin_labels.manage_system_users', 'Manage System Users')]]" />

    <section class="overview-data">
        <div class="card content-area p-4 ">
            <div class="row align-items-center d-flex heading mb-5">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-sm-12">
                            <h4>{{ labels('admin_labels.manage_system_users', 'Manage System Users') }}
                            </h4>
                        </div>
                        <div class="col-sm-12 d-flex justify-content-end mt-md-2 mt-sm-2">
                            <a href="{{ route('admin.system_users.index') }}" class="btn btn-dark me-3">{{ labels('admin_labels.add_system_user', 'Add System User') }}</a>
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_manage_system_users" class="form-control searchInput"
                                    placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="admin_manage_system_users"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-primary btn-sm delete_selected_data"
                        data-table-id="admin_manage_system_users"
                        data-delete-url="{{ route('system_users.delete') }}">{{ labels('admin_labels.delete_selected', 'Delete Selected') }}</button>
                </div>
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_manage_system_users" data-toggle="table"
                                data-loading-template="loadingTemplate" data-url="{{ route('system_users.list') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-maintain-selected="true"
                                data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-checkbox="true" data-field="delete-checkbox">
                                            <input name="select_all" type="checkbox">
                                        </th>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        </th>
                                        <th data-field="username" data-sortable="false">
                                            {{ labels('admin_labels.username', 'Username') }}
                                        </th>
                                        <th data-field="email" data-sortable="false">
                                            {{ labels('admin_labels.email', 'Email') }}
                                        </th>
                                        <th data-field="mobile" data-sortable="false">
                                            {{ labels('admin_labels.mobile', 'Mobile') }}
                                        </th>
                                        <th data-field="role" data-sortable="false">
                                            {{ labels('admin_labels.role', 'Role') }}
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
@endsection
