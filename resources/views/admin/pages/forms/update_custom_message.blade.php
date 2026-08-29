@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.update_custom_message', 'Update Custom Message') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.update_custom_message', 'Update Custom Message')" :subtitle="labels(
        'admin_labels.craft_personalized_messages_with_custom_message_management',
        'Craft Personalized Messages with Custom Message Management',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.custom_message', 'Custom Message'), 'url' => route('admin.custom_message.index')],
        ['label' => labels('admin_labels.update_custom_message', 'Update Custom Message')],
    ]" />

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">
                        {{ labels('admin_labels.update_custom_message', 'Update Custom Message') }}
                    </h5>
                    @php
                        $type = [
                            'place_order',
                            'settle_cashback_discount',
                            'settle_seller_commission',
                            'customer_order_received',
                            'customer_order_processed',
                            'customer_order_shipped',
                            'customer_order_delivered',
                            'customer_order_cancelled',
                            'customer_order_returned',
                            'customer_order_returned_request_decline',
                            'customer_order_returned_request_approved',
                            'delivery_boy_order_deliver',
                            'wallet_transaction',
                            'ticket_status',
                            'ticket_message',
                            'bank_transfer_receipt_status',
                            'bank_transfer_proof',
                        ];
                    @endphp
                    <form class="form-horizontal submit_form" action="{{ route('custom_message.update', $data->id) }}"
                        method="POST" enctype="multipart/form-data">
                        @method('PUT')
                        @csrf
                        <div class="form-group row">
                            <label for="type" class="form-label mb-2 mt-2">
                                {{ labels('admin_labels.type', 'Type') }}<span class="text-danger text-sm"> *</span>
                            </label>
                            <div class="col-sm-12">
                                <select name="type" class="form-control custom_message_type form-select">
                                    <option value=" ">{{ labels('admin_labels.select_type', 'Select Type') }}
                                    </option>
                                    @foreach ($type as $row)
                                        <option value="{{ $row }}" @selected($data->type == $row)>
                                            {{ ucwords(str_replace('_', ' ', $row)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="title"
                                class="form-label mb-2 mt-2">{{ labels('admin_labels.title', 'Title') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <div class="col-sm-12">
                                <input type="text" name="title" id="custom_message_title"
                                    class="form-control custom_message_title" placeholder="Title"
                                    value="{{ $data->title }}" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="message"
                                class="form-label mb-2 mt-2">{{ labels('admin_labels.message', 'Message') }}<span
                                    class='text-asterisks text-sm'>*</span></label>
                            <div class="col-sm-12">
                                <textarea name="message" id="text-box" class="form-control" placeholder="Place some text here">{{ $data->message }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="reset"
                                class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                            <button type="submit"
                                class="btn btn-primary submit_button">{{ labels('admin_labels.update_custom_message', 'Update Custom Message') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
