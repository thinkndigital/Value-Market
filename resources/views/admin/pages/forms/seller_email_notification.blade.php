@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.seller_email_notification', 'Seller Email Notification') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.seller_email_notification', 'Seller Email Notification')" :subtitle="labels(
        'admin_labels.send_email_updates_directly_to_your_sellers',
        'Send Email Updates Directly to Your Sellers',
    )" :breadcrumbs="[['label' => labels('admin_labels.seller_email_notification', 'Seller Email Notification')]]" />

    <div class="row">
        <div class="col-md-12 col-lg-6">
            <div class="card card-info">
                <form class="form-horizontal submit_form" action="{{ route('email_notifications.store') }}"
                    method="POST" id="">
                    @csrf
                    <div class="card-body">
                        <h5 class="mb-3">
                            {{ labels('admin_labels.seller_email_notification', 'Seller Email Notification') }}
                        </h5>
                        <div class="form-group">
                            <label for="send_to"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.send_to', 'Send to') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <select name="send_to" id="send_seller_notification"
                                class="form-control form-select type_event_trigger" required>
                                <option value="all_sellers">All Sellers</option>
                                <option value="specific_seller">Specific Seller</option>
                            </select>
                        </div>
                        <div class="form-group row notification-sellers d-none">
                            <label for="select_user_id"
                                class="col-md-12 control-label">{{ labels('admin_labels.sellers', 'Sellers') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <div class="col-md-12">
                                <select name="select_user_id[]" class="search_seller w-100" multiple
                                    data-placeholder="Type to search and select sellers">
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="subject"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.subject', 'Subject') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <input type="text" class="form-control" name="subject" id="subject" value="">
                        </div>
                        <div class="form-group">
                            <label for="message"
                                class="control-label mb-2 mt-2">{{ labels('admin_labels.message', 'Message') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <textarea name='message' class="form-control"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="reset"
                                class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                            <button type="submit"
                                class="btn btn-primary submit_button">{{ labels('admin_labels.send', 'Send') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
