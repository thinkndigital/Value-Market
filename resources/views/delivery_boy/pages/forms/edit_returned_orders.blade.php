@extends('delivery_boy/layout')
@section('title')
    {{ labels('admin_labels.returned_order_details', 'Returned Order Details') }}
@endsection
@section('content')
    <section class="main-content">
        <x-delivery_boy.breadcrumb :title="labels('admin_labels.returned_order_details', 'Returned Order Details')" :subtitle="labels(
            'admin_labels.review_and_process_this_returned_order',
            'Review and Process this Returned Order',
        )" :breadcrumbs="[
            ['label' => labels('admin_labels.returned_orders', 'Returned Orders'), 'url' => route('delivery_boy.cash.returned_order')],
            ['label' => labels('admin_labels.returned_order_details', 'Returned Order Details')],
        ]" />

        <div class="row">
            <div class="col-md-4">
                <div class="card p-4">
                    <h6 class="mb-3">{{ labels('admin_labels.order_details', 'Order Details') }}</h6>
                    <p class="mb-1"><strong>{{ labels('admin_labels.order_id', 'Order ID') }}:</strong>
                        {{ $order_details->order_id ?? ($order_details->id ?? '-') }}</p>
                    @if (!empty($mobile_data) && isset($mobile_data[0]->mobile))
                        <p class="mb-1"><strong>{{ labels('admin_labels.mobile', 'Mobile') }}:</strong>
                            {{ $mobile_data[0]->mobile }}</p>
                    @endif
                    @if (!empty($address))
                        <p class="mb-1"><strong>{{ labels('admin_labels.address', 'Address') }}:</strong>
                            {{ $address }}</p>
                    @endif
                </div>

                @if (!empty($sellers))
                    <div class="card p-4 mt-3">
                        <h6 class="mb-3">{{ labels('admin_labels.seller', 'Seller') }}</h6>
                        @foreach ($sellers as $seller)
                            <p class="mb-1"><strong>{{ $seller['store_name'] ?? '' }}</strong></p>
                            <p class="mb-1">{{ $seller['seller_name'] ?? '' }} - {{ $seller['seller_mobile'] ?? '' }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                <div class="card p-4">
                    <h6 class="mb-3">{{ labels('admin_labels.items', 'Items') }}</h6>
                    @foreach ($items ?? [] as $item)
                        <div class="row align-items-center border-bottom py-3">
                            <div class="col-2">
                                @if (!empty($item['product_image']))
                                    <img src="{{ app(\App\Services\MediaService::class)->getMediaImageUrl($item['product_image']) }}"
                                        class="img-fluid rounded" alt="">
                                @endif
                            </div>
                            <div class="col-6">
                                <p class="mb-0">{{ $item['pname'] ?? '' }}</p>
                                <small class="text-muted">{{ labels('admin_labels.quantity', 'Quantity') }}:
                                    {{ $item['quantity'] ?? '' }}</small>
                            </div>
                            <div class="col-2">
                                {{ $currency ?? '' }}{{ $item['price'] ?? '' }}
                            </div>
                            <div class="col-2">
                                <span
                                    class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $item['active_status'] ?? '')) }}</span>
                            </div>
                        </div>
                        @if (($item['active_status'] ?? '') !== 'return_pickedup' && ($item['active_status'] ?? '') !== 'returned')
                            <div class="text-end mt-2 mb-3">
                                <button type="button" class="btn btn-primary btn-sm mark_picked_up"
                                    data-order-item-id="{{ $item['id'] }}">
                                    {{ labels('admin_labels.mark_as_picked_up', 'Mark as Picked Up') }}
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <script>
        $(document).on('click', '.mark_picked_up', function() {
            var $btn = $(this);
            $.ajax({
                url: "{{ url('delivery_boy/orders/update_return_order_item_status') }}",
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    order_item_id: $btn.data('order-item-id'),
                    status: 'return_pickedup'
                },
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
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
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
@endsection
