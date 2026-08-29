@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.commission_rules', 'Commission Rules') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.commission_rules', 'Commission Rules')" :subtitle="labels(
        'admin_labels.configure_affiliate_commission_rates',
        'Configure Affiliate Commission Rates',
    )" :breadcrumbs="[['label' => labels('admin_labels.commission_rules', 'Commission Rules')]]" />

    {{-- Add modal --}}
    <div class="modal fade" id="add_commission_rule_modal" tabindex="-1" role="dialog"
        aria-labelledby="addCommissionRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommissionRuleModalLabel">
                        {{ labels('admin_labels.add_commission_rule', 'Add Commission Rule') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="add_commission_rule_form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="scope" class="form-label">{{ labels('admin_labels.scope', 'Scope') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <select class="form-select" name="scope" id="scope">
                                <option value="platform">Platform (applies to every sale, lowest priority)</option>
                                <option value="vendor">Vendor (a specific seller)</option>
                                <option value="affiliate">Affiliate (a specific affiliate user)</option>
                                <option value="category">Category</option>
                                <option value="product">Product (highest priority)</option>
                            </select>
                        </div>
                        <div class="form-group mt-3" id="scope_id_group" style="display:none;">
                            <label for="scope_id" class="form-label">{{ labels('admin_labels.scope_id', 'Scope ID') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <input type="number" class="form-control" name="scope_id" id="scope_id" min="1">
                            <small class="text-muted">The seller ID, user ID, category ID, or product ID this rule
                                applies to (depending on the scope chosen above).</small>
                        </div>
                        <div class="form-group mt-3">
                            <label for="rate_type" class="form-label">{{ labels('admin_labels.rate_type', 'Rate Type') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <select class="form-select" name="rate_type" id="rate_type">
                                <option value="percentage">Percentage</option>
                                <option value="flat">Flat Amount</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label for="rate_value"
                                class="form-label">{{ labels('admin_labels.rate_value', 'Rate Value') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <input type="number" class="form-control" name="rate_value" id="rate_value" min="0"
                                step="0.01">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary"
                            id="add_commission_rule_submit">{{ labels('admin_labels.submit', 'Submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div class="modal fade" id="edit_commission_rule_modal" tabindex="-1" role="dialog"
        aria-labelledby="editCommissionRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCommissionRuleModalLabel">
                        {{ labels('admin_labels.edit_commission_rule', 'Edit Commission Rule') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="edit_commission_rule_form">
                    @csrf
                    <input type="hidden" name="id" id="edit_rule_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_rate_type"
                                class="form-label">{{ labels('admin_labels.rate_type', 'Rate Type') }}</label>
                            <select class="form-select" name="rate_type" id="edit_rate_type">
                                <option value="percentage">Percentage</option>
                                <option value="flat">Flat Amount</option>
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label for="edit_rate_value"
                                class="form-label">{{ labels('admin_labels.rate_value', 'Rate Value') }}</label>
                            <input type="number" class="form-control" name="rate_value" id="edit_rate_value"
                                min="0" step="0.01">
                        </div>
                        <div class="form-group mt-3">
                            <label for="edit_status" class="form-label">{{ labels('admin_labels.status', 'Status') }}</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary"
                            id="edit_commission_rule_submit">{{ labels('admin_labels.update', 'Update') }}</button>
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
                        <div class="col-sm-12">
                            <h4>{{ labels('admin_labels.commission_rules', 'Commission Rules') }}
                            </h4>
                        </div>
                        <div class="col-sm-12 d-flex justify-content-end mt-md-2 mt-sm-2">
                            <a href="#" class="btn btn-dark me-3" data-bs-toggle="modal"
                                data-bs-target="#add_commission_rule_modal">{{ labels('admin_labels.add_commission_rule', 'Add Commission Rule') }}</a>
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="admin_commission_rules_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="admin_commission_rules_table"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="admin_commission_rules_table" data-toggle="table"
                                data-loading-template="loadingTemplate" data-url="{{ route('admin.commission_rules.list') }}"
                                data-side-pagination="client" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-response-handler="commissionRulesResponseHandler">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        </th>
                                        <th data-field="scope" data-sortable="true">
                                            {{ labels('admin_labels.scope', 'Scope') }}
                                        </th>
                                        <th data-field="scope_id" data-sortable="false">
                                            {{ labels('admin_labels.scope_id', 'Scope ID') }}
                                        </th>
                                        <th data-field="rate_type" data-sortable="false">
                                            {{ labels('admin_labels.rate_type', 'Rate Type') }}
                                        </th>
                                        <th data-field="rate_value" data-sortable="false">
                                            {{ labels('admin_labels.rate_value', 'Rate Value') }}
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

    @push('scripts')
        <script>
            // The Phase 7 commission-rule endpoints (admin.commission_rules.store/update) return
            // {error: true|false, message} on a plain 200 response rather than this app's shared
            // .submit_form handler's expected {error_message} shape, so this page submits with its own
            // small handler instead of relying on that generic (mismatched, for this pair of endpoints)
            // convention.
            function commissionRulesResponseHandler(res) {
                return (res.data || []).map(function(row) {
                    var rawRateType = row.rate_type;
                    var rawRateValue = row.rate_value;
                    var rawStatus = row.status;

                    row.operate = '<a href="#" class="btn edit_commission_rule" data-id="' + row.id +
                        '" data-rate_type="' + rawRateType + '" data-rate_value="' + rawRateValue +
                        '" data-status="' + rawStatus +
                        '" data-bs-toggle="modal" data-bs-target="#edit_commission_rule_modal"><i class="bx bx-pencil"></i></a>';

                    row.scope = row.scope.charAt(0).toUpperCase() + row.scope.slice(1);
                    row.scope_id = row.scope_id ?? '-';
                    row.rate_value = rawRateType === 'percentage' ? rawRateValue + '%' : rawRateValue;
                    row.rate_type = rawRateType.charAt(0).toUpperCase() + rawRateType.slice(1);
                    row.status = rawStatus == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                    return row;
                });
            }

            $(document).on('change', '#scope', function() {
                $('#scope_id_group').toggle($(this).val() !== 'platform');
            });

            $('#add_commission_rule_form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                $.ajax({
                    url: "{{ route('admin.commission_rules.store') }}",
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.error) {
                            iziToast.error({
                                title: 'Error',
                                message: response.message,
                                position: 'topRight'
                            });
                            return;
                        }
                        iziToast.success({
                            title: 'Success',
                            message: response.message,
                            position: 'topRight'
                        });
                        $('#add_commission_rule_modal').modal('hide');
                        $form[0].reset();
                        $('#admin_commission_rules_table').bootstrapTable('refresh');
                    },
                    error: function() {
                        iziToast.error({
                            title: 'Error',
                            message: 'Something went wrong! Try again.',
                            position: 'topRight'
                        });
                    }
                });
            });

            $(document).on('click', '.edit_commission_rule', function() {
                $('#edit_rule_id').val($(this).data('id'));
                $('#edit_rate_type').val($(this).data('rate_type'));
                $('#edit_rate_value').val($(this).data('rate_value'));
                $('#edit_status').val($(this).data('status'));
            });

            $('#edit_commission_rule_form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var id = $('#edit_rule_id').val();
                $.ajax({
                    url: "{{ url('admin/commission_rules') }}/" + id,
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.error) {
                            iziToast.error({
                                title: 'Error',
                                message: response.message,
                                position: 'topRight'
                            });
                            return;
                        }
                        iziToast.success({
                            title: 'Success',
                            message: response.message,
                            position: 'topRight'
                        });
                        $('#edit_commission_rule_modal').modal('hide');
                        $('#admin_commission_rules_table').bootstrapTable('refresh');
                    },
                    error: function() {
                        iziToast.error({
                            title: 'Error',
                            message: 'Something went wrong! Try again.',
                            position: 'topRight'
                        });
                    }
                });
            });
        </script>
    @endpush
@endsection
