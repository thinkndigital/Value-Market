@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.edit_orders', 'Edit Orders') }}
@endsection
@section('content')

    <x-admin.breadcrumb :title="labels('admin_labels.order_details', 'Order Details')" :subtitle="labels('admin_labels.see_every_detail_steer_every_step', 'See Every Detail Steer Every Step')" :breadcrumbs="[
        ['label' => labels('admin_labels.manage_orders', 'Manage Orders')],
        ['label' => labels('admin_labels.orders', 'Orders')],
    ]" />

    @php
        use App\Models\OrderCharges;
        use App\Services\MediaService;
        use App\Services\ShiprocketService;
        use App\Services\CurrencyService;
        use App\Services\OrderService;
    @endphp
    <section>

        <div class="card content-area p-3">
            <div class="align-items-center d-flex justify-content-between">
                <div>
                    <span class="body-default text-muted">{{ labels('admin_labels.order_number', 'Order Number') }}</span>
                    <p class="lead">#{{ $order_detls[0]->id }}</p>
                </div>
                <div class="align-items-center d-flex">
                    <span class="body-default text-muted">{{ labels('admin_labels.order_date', 'Order Date') }} :</span>
                    <span class="body-default me-3"><?= date('d M, Y', strtotime($order_detls[0]->created_at)) ?></span>

                    <a href="{{ route('admin.orders.generatInvoicePDF', $order_detls[0]->order_id) }}"
                        class="btn btn-primary btn-sm instructions_files"><i
                            class='bx bx-download me-1'></i>{{ labels('admin_labels.invoice', 'Invoice') }}
                    </a>



                </div>
            </div>
        </div>
        <div class="row mt-5 order-info">
            <div class="col-md-4">
                <div class="card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>{{ labels('admin_labels.customer_info', 'Customer Info') }}</h6>
                            <div class="d-flex mt-3 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.name', 'Name') }}:</span>
                                <span class="caption text-muted">{{ $order_detls[0]->user_name }}</span>
                            </div>

                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.mobile', 'Contact') }}:</span>
                                @if ($order_detls[0]->mobile != '' && isset($order_detls[0]->mobile))
                                    <span class="caption text-muted">{{ $order_detls[0]->mobile }}</span>
                                @else
                                    <span
                                        class="caption text-muted">{{ isset($mobile_data) ? $mobile_data[0]->mobile : '' }}</span>
                                @endif
                            </div>
                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.email', 'Email') }}:</span>
                                <span class="caption text-muted">{{ $order_detls[0]->email }}</span>
                            </div>
                        </div>
                        <div>
                            <img alt="" src="{{ $items[0]['user_profile'] }}" class="customer-img-box">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>{{ labels('admin_labels.shipping_info', 'Shipping Info') }}</h6>
                            <div class="d-flex mt-3 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.name', 'Name') }}:</span>
                                <span class="caption text-muted">{{ $order_detls[0]->user_name }}</span>
                            </div>

                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.mobile', 'Contact') }}:</span>
                                @if ($order_detls[0]->mobile != '' && isset($order_detls[0]->mobile))
                                    <span class="caption text-muted">{{ $order_detls[0]->mobile }}</span>
                                @else
                                    <span
                                        class="caption text-muted">{{ isset($mobile_data) ? $mobile_data[0]->mobile : '' }}</span>
                                @endif
                            </div>
                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.address', 'Address') }}:</span>
                                <span class="caption text-muted">{{ $order_detls[0]->address }}</span>
                            </div>
                        </div>
                        <div>
                            <img alt="" src="{{ $items[0]['user_profile'] }}" class="customer-img-box">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6>{{ labels('admin_labels.seller_info', 'Seller Info') }}</h6>
                            <div class="d-flex mt-3 align-items-center">
                                <span
                                    class="body-default me-1">{{ labels('admin_labels.seller_name', 'Seller Name') }}:</span>
                                <span class="caption text-muted">{{ $sellers[0]['seller_name'] }}</span>
                            </div>

                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.mobile', 'Contact') }}:</span>
                                <span class="caption text-muted">{{ $sellers[0]['seller_mobile'] }}</span>
                            </div>
                            <div class="d-flex mt-2 align-items-center">
                                <span class="body-default me-1">{{ labels('admin_labels.email', 'Email') }}:</span>
                                <span class="caption text-muted">{{ $sellers[0]['seller_email'] }}</span>
                            </div>
                        </div>
                        <div>
                            <img alt=""
                                src="{{ !empty($sellers[0]['shop_logo']) ? app(MediaService::class)->getMediaImageUrl($sellers[0]['shop_logo']) : '' }}"
                                class="customer-img-box">
                        </div>

                    </div>
                </div>
            </div>

        </div>
        <div class="row mt-5 order-detail">
            <div class="col-lg-8 col-xl-9">

                <form id="update_form">
                    @for ($i = 0; $i < count($sellers); $i++)
                        @php
                            $seller_data = fetchDetails(
                                \App\Models\User::class,
                                ['id' => $sellers[$i]['user_id']],
                                ['username', 'fcm_id'],
                            );
                            $seller_otp = fetchDetails(
                                \App\Models\OrderItems::class,
                                ['order_id' => $order_detls[0]->order_id, 'seller_id' => $sellers[$i]['id']],
                                'otp',
                            )[0]->otp;
                            $order_charges_data = fetchDetails(OrderCharges::class, [
                                'order_id' => $order_detls[0]->order_id,
                                'seller_id' => $sellers[$i]['id'],
                            ]);
                            $seller_order = app(OrderService::class)->getOrderDetails(
                                ['o.id' => $order_detls[0]->order_id, 'oi.seller_id' => $sellers[$i]['id']],
                                '',
                                '',
                                $store_id,
                            );
                            $pickup_location = collect($seller_order)
                                ->pluck('pickup_location')
                                ->unique()
                                ->values()
                                ->all();
                        @endphp
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span
                                            class="caption text-muted">{{ labels('admin_labels.seller', 'Seller') }}</span>

                                        <p>{{ $seller_data[0]->username }}</p>
                                    </div>
                                    <div>
                                        <span>{{ labels('admin_labels.otp', 'OTP') }}</span>
                                        <p class="btn-primary btn-sm">
                                            {{ isset($order_charges_data[0]->otp) ? $order_charges_data[0]->otp : $seller_otp }}
                                        </p>

                                    </div>
                                </div>

                                @for ($j = 0; $j < count($pickup_location); $j++)
                                    @php
                                        // --------------------------------------- code for shiprocket
                                        //-----------------------------------------------

                                        $ids = '';
                                        foreach ($seller_order as $row) {
                                            if ($row->pickup_location == $pickup_location[$j]) {
                                                $ids .= $row->order_item_id . ',';
                                            }
                                        }
                                    @endphp
                                    <input type="hidden" name="edit_order_id" value="{{ $order_detls[0]->order_id }}">
                                    @php
                                        $total = 0;
                                        $tax_amount = 0;
                                    @endphp

                                    <div class="table-responsive mt-4">
                                        <table
                                            class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 edit-order-table">
                                            <thead class="thead-light thead-50 text-capitalize">
                                                <tr>

                                                    <th class="w-40">
                                                        {{ labels('admin_labels.product_items', 'Product Items') }}
                                                    </th>
                                                    <th>{{ labels('admin_labels.variations', 'Variation') }}</th>
                                                    <th>{{ labels('admin_labels.discount', 'Discount') }}</th>
                                                    <th>{{ labels('admin_labels.price', 'Price') }}</th>
                                                    <th>{{ labels('admin_labels.quantity', 'Qty') }}</th>
                                                    <th>{{ labels('admin_labels.deliver_by', 'Deliver By') }}</th>
                                                    <th>{{ labels('admin_labels.active_status', 'Active Status') }}</th>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $item_subtotal = 0;
                                                    $total = 0;
                                                    $tax_amount = 0;
                                                @endphp

                                                @foreach ($items as $item)
                                                    @php
                                                        $selected = '';
                                                        $item['discounted_price'] =
                                                            $item['discounted_price'] == ''
                                                                ? 0
                                                                : $item['discounted_price'];
                                                        $total += $subtotal =
                                                            $item['quantity'] != 0 &&
                                                            ($item['discounted_price'] != '' &&
                                                                $item['discounted_price'] > 0) &&
                                                            $item['price'] > $item['discounted_price']
                                                                ? $item['price'] - $item['discounted_price']
                                                                : $item['price'] * $item['quantity'];
                                                        $tax_amount += $item['tax_amount'];
                                                        $total += $subtotal = $tax_amount;
                                                        $item_subtotal += $item['item_subtotal'];
                                                    @endphp

                                                    @if ($sellers[$i]['id'] == $item['seller_id'])
                                                        @if ($pickup_location[$j] == $item['pickup_location'])
                                                            @php
                                                                $order_tracking_data = app(
                                                                    ShiprocketService::class,
                                                                )->getShipmentId($item['id'], $order_detls[0]->id);
                                                                $product_name = json_decode($row->pname, true);
                                                                $product_name = $product_name['en'] ?? '';
                                                                // dd($item)
                                                            @endphp
                                                            <tr>
                                                                <td class="align-items-center d-flex">
                                                                    <img alt=""
                                                                        class="avatar avatar-60 rounded ms-3"
                                                                        src="{{ app(MediaService::class)->getMediaImageUrl($item['product_image']) }}"
                                                                        alt="Image Description">
                                                                    <div class="ms-2">
                                                                        <h6 class="title-color">{{ $product_name }}</h6>
                                                                    </div>
                                                                </td>
                                                                <td>{{ isset($item['product_variants']) && !empty($item['product_variants']) ? str_replace(',', ' | ', $item['product_variants'][0]['variant_values']) : '-' }}
                                                                </td>
                                                                <td>{{ $item['discounted_price'] }}</td>
                                                                <td>{{ $item['price'] }}</td>
                                                                <td>{{ $item['quantity'] }}</td>
                                                                <td>{{ $item['deliver_by'] }}</td>

                                                                @php
                                                                    $badges = [
                                                                        'awaiting' => 'secondary',
                                                                        'received' => 'primary',
                                                                        'processed' => 'info',
                                                                        'shipped' => 'warning',
                                                                        'delivered' => 'success',
                                                                        'returned' => 'danger',
                                                                        'cancelled' => 'danger',
                                                                        'return_request_approved' => 'success',
                                                                        'return_request_decline' => 'danger',
                                                                        'return_request_pending' => 'warning',
                                                                        'return_pickedup' => 'success',
                                                                    ];

                                                                    if (
                                                                        $item['active_status'] ==
                                                                        'return_request_pending'
                                                                    ) {
                                                                        $status = 'Return Requested';
                                                                    } elseif (
                                                                        $item['active_status'] ==
                                                                        'return_request_approved'
                                                                    ) {
                                                                        $status = 'Return Approved';
                                                                    } elseif (
                                                                        $item['active_status'] ==
                                                                        'return_request_decline'
                                                                    ) {
                                                                        $status = 'Return Declined';
                                                                    } else {
                                                                        $status = $item['active_status'];
                                                                    }
                                                                @endphp

                                                                <td>
                                                                    <small><span
                                                                            class="mt-1 badge badge-sm bg-{{ $badges[$item['active_status']] }}">{{ $status }}</span></small>
                                                                </td>

                                                                @if ($item['product_type'] == 'digital_product' && $item['download_allowed'] == 0 && $item['is_sent'] == 0)
                                                                    <td>
                                                                        <a href="javascript:void(0)"
                                                                            class="btn reset-btn ml-3"
                                                                            id="sendDigitalProductMail"
                                                                            data-bs-target="#sendMailModal"
                                                                            data-bs-toggle="modal" title="Edit"
                                                                            data-id="{{ $item['id'] }}">
                                                                            <i class="fas fa-paper-plane"></i>
                                                                        </a>
                                                                        <a href="https://mail.google.com/mail/?view=cm&fs=1&tf=1&to={{ $item['user_email'] }}"
                                                                            class="btn btn-danger ml-3" target="_blank">
                                                                            <i class="fab fa-google"></i>
                                                                        </a>
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endif
                                                    @endif
                                                @endforeach

                                            </tbody>
                                        </table>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endfor
                </form>
            </div>
            <div class="col-lg-4 col-xl-3">


                <div class="card">
                    <h6 class="mb-3">{{ labels('admin_labels.payment_info', 'Payment Info') }}</h6>
                    @if (isset($order_detls[0]->txn_id) && !empty($order_detls[0]->txn_id))
                        <div class="d-flex ">
                            <span>{{ labels('admin_labels.id', 'ID') }}:</span>
                            <span class="text-muted ms-1">#{{ $order_detls[0]->txn_id }}</span>
                        </div>
                    @endif
                    <div class="d-flex mt-2 align-items-center">
                        <span>{{ labels('admin_labels.payment_method', 'Payment Method') }}:</span>
                        <span
                            class="text-muted ms-1">{{ ucfirst(str_replace('_', ' ', $order_detls[0]->payment_method)) }}</span>
                        @if (isset($transaction_search_res) && !empty($transaction_search_res))
                            <a href="javascript:void(0)" class="edit_transaction btn active ms-5"
                                title="Update bank transfer receipt status"
                                data-id="{{ $transaction_search_res[0]->id }}"
                                data-txn_id="{{ $transaction_search_res[0]->txn_id }}"
                                data-status="{{ $transaction_search_res[0]->status }}"
                                data-message="{{ $transaction_search_res[0]->message }}"
                                data-bs-target="#payment_transaction_modal" data-bs-toggle="modal">
                                <i class='bx bxs-pencil me-1'></i>{{ labels('admin_labels.edit', 'Edit') }}
                            </a>
                        @endif

                    </div>
                    @if (!empty($bank_transfer))
                        <table class="table">
                            <th></th>
                            <tbody>
                                <tr>
                                    <td>
                                        @php
                                            $status = ['history', 'ban', 'check'];
                                        @endphp

                                        <div class="row">
                                            @php $i = 1; @endphp
                                            @foreach ($bank_transfer as $row1)
                                                @php
                                                    $imagePath = asset('/storage/' . $row1->attachments);
                                                @endphp
                                                <div
                                                    class="col-md-12 align-items-center d-flex justify-content-between mb-2 mt-2">
                                                    <small>[<a href="{{ $imagePath }}"
                                                            target="_blank">Attachment{{ $i }}
                                                        </a>]</small>
                                                    @if ($row1->status == 0)
                                                        <label for=""
                                                            class="badge bg-warning ms-1">Pending</label>
                                                    @elseif ($row1->status == 1)
                                                        <label for=""
                                                            class="badge bg-danger ms-1">Rejected</label>
                                                    @elseif ($row1->status == 2)
                                                        <label for=""
                                                            class="badge bg-primary ms-1">Accepted</label>
                                                    @else
                                                        <label for="" class="badge bg-danger ms-1">Invalid
                                                            Value</label>
                                                    @endif
                                                    <button class="btn btn-primary btn-xs ms-1 mb-1 delete-data"
                                                        title="Delete"
                                                        data-url="{{ route('admin.orders.delete_receipt', $row1->id) }}"
                                                        data-id="{{ $row1->id }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </div>
                                                @php $i++; @endphp
                                            @endforeach
                                        </div>

                                        <div class="col-md-12">
                                            <select name="update_receipt_status" id="update_receipt_status"
                                                class="form-select status" data-id="{{ $order_detls[0]->id }}"
                                                data-user_id="{{ $order_detls[0]->user_id }}">
                                                <option value=''>
                                                    {{ labels('admin_labels.select', 'Select Status') }}
                                                </option>
                                                <option value="1"
                                                    {{ isset($bank_transfer[0]->status) && $bank_transfer[0]->status == 1 ? 'selected' : '' }}>
                                                    Rejected
                                                </option>
                                                <option value="2"
                                                    {{ isset($bank_transfer[0]->status) && $bank_transfer[0]->status == 2 ? 'selected' : '' }}>
                                                    Accepted
                                                </option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="card mt-5">
                    <h6>{{ labels('admin_labels.total_order_amount', 'Total Order Amount') }}</h6>
                    <div class="mt-3">
                        <span class="text-muted float-start">{{ labels('admin_labels.sub_total', 'Sub Total') }}</span>
                        <span
                            class="float-end">{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($item_subtotal)) }}</span>
                    </div>
                    <div class="mt-3">
                        <span
                            class="text-muted float-start">{{ labels('admin_labels.delivery_charges', 'Shipping Charges') }}</span>
                        <span
                            class="float-end">{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($items[0]['seller_delivery_charge'])) }}</span>
                    </div>
                    <div class="mt-3">
                        <span
                            class="text-muted float-start">{{ labels('admin_labels.wallet_balance', 'Wallet Balance') }}</span>
                        <span
                            class="float-end">{{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($items[0]['wallet_balance'])) }}</span>
                    </div>
                    <div class="mt-3">
                        <span
                            class="text-muted float-start">{{ labels('admin_labels.discount_amount', 'Discount Amount') }}</span>
                        <span class="float-end">
                            {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($items[0]['seller_promo_discount'])) }}
                        </span>

                    </div>
                    <hr class="mt-3">
                    {{-- @dd($order_detls[0]); --}}
                    <div>
                        {{-- @php
                            $total =
                                $item_subtotal +
                                (float) ('' . $order_detls[0]->delivery_charge) -
                                $order_detls[0]->promo_discount -
                                $items[0]['wallet_balance'];
                        @endphp --}}
                        {{-- apply seller delivery charge because if order return than it update delivery charge as 0  --}}
                        @php
                            $deliveryCharge = (float) ('' . $order_detls[0]->delivery_charge);

                            // If delivery_charge is 0, use seller_delivery_charge
                            if ($deliveryCharge == 0) {
                                $deliveryCharge = (float) $items[0]['seller_delivery_charge'];
                            }

                            $total =
                                $item_subtotal +
                                $deliveryCharge -
                                $order_detls[0]->promo_discount -
                                $items[0]['wallet_balance'];
                        @endphp

                        <span class="float-start">{{ labels('admin_labels.total_amount', 'Total Amount') }}</span>
                        <h6 class="float-end">
                            {{ app(CurrencyService::class)->formateCurrency(formatePriceDecimal($total)) }}</h6>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- model for update bank transfer recipt  -->
    <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="payment_transaction_modal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="user_name"></h5>
                    <div class="d-flex justify-content-end"><button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button></div>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-info">
                                <!-- form start -->
                                <form class="form-horizontal " id="edit_transaction_form"
                                    action="{{ route('admin.customers.edit_transactions') }}" method="POST"
                                    enctype="multipart/form-data">
                                    <input type="hidden" name="id" id="id">
                                    <div class="modal-body">
                                        <div class="col-md-12">
                                            <label for="transaction" class="mb-2 mt-2">
                                                {{ labels('admin_labels.update_transaction', 'Update Transaction') }}
                                            </label>
                                            <select class="form-control form-select" name="status" id="t_status">
                                                <option value="awaiting"> Awaiting </option>
                                                <option value="success"> Success </option>
                                                <option value="failed"> Failed </option>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label for="txn_id"
                                                class="mb-2 mt-2">{{ labels('admin_labels.transaction_id', 'Transaction ID') }}</label>
                                            <input type="text" class="form-control" name="txn_id" id="txn_id"
                                                placeholder="txn_id" />
                                        </div>
                                        <div class="col-md-12">
                                            <label for="message"
                                                class="mb-2 mt-2">{{ labels('admin_labels.message', 'Message') }}</label>
                                            <input type="text" class="form-control" name="message"
                                                id="transaction_message" placeholder="Message" />
                                        </div>

                                    </div>
                                    <div class="modal-footer">
                                        <div class="d-flex justify-content-end mt-4">
                                            <button type="reset"
                                                class="btn mx-2 reset_button">{{ labels('admin_labels.reset', 'Reset') }}</button>
                                            <button type="submit" class="btn btn-primary submit_button"
                                                id="">{{ labels('admin_labels.update_transaction', 'Update Transaction') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div>

                    </div>


                </div>
                </form>
            </div>
        </div>
    </div>
@endsection
