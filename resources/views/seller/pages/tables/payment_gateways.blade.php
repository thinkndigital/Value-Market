@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.payment_gateways', 'Payment Gateways') }}
@endsection
@section('content')
    <section class="main-content">
        <div class="row">
            <x-seller.breadcrumb :title="labels('admin_labels.payment_gateways', 'Payment Gateways')" :subtitle="labels(
                'admin_labels.payment_gateways_explainer',
                'Add your own gateway credentials to receive customer payments directly. Leave a gateway unconfigured to keep using the platform default.',
            )" :breadcrumbs="[['label' => labels('admin_labels.payment_gateways', 'Payment Gateways')]]" />

            <div id="payment_gateway_alert"></div>

            <section class="overview-data">
                @foreach ($fields as $gateway => $gatewayFields)
                    @php
                        $row = $gateways->get($gateway);
                        $isEnabled = $row['is_enabled'] ?? false;
                        $configured = $row['configured_fields'] ?? [];
                    @endphp
                    <div class="card content-area p-4 mb-4" data-gateway-card="{{ $gateway }}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-capitalize">{{ $gateway }}</h5>
                            <span class="badge {{ $isEnabled ? 'bg-success' : 'bg-secondary' }}" data-gateway-status>
                                {{ $isEnabled ? labels('admin_labels.enabled', 'Enabled') : labels('admin_labels.disabled', 'Disabled') }}
                            </span>
                        </div>

                        @foreach ($gatewayFields as $fieldKey => $fieldLabel)
                            <div class="mb-3">
                                <label class="form-label">{{ $fieldLabel }}</label>
                                <input type="text" class="form-control" data-field="{{ $fieldKey }}"
                                    placeholder="{{ in_array($fieldKey, $configured) ? labels('admin_labels.saved_leave_blank_to_keep', '•••• saved - re-enter to change') : '' }}">
                            </div>
                        @endforeach

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" data-save-gateway="{{ $gateway }}" data-enable="1">
                                {{ labels('admin_labels.save_and_enable', 'Save & Enable') }}
                            </button>
                            @if ($isEnabled)
                                <button type="button" class="btn btn-outline-secondary" data-disable-gateway="{{ $gateway }}">
                                    {{ labels('admin_labels.disable', 'Disable') }}
                                </button>
                            @endif
                            @if (!empty($configured))
                                <button type="button" class="btn btn-outline-danger" data-remove-gateway="{{ $gateway }}">
                                    {{ labels('admin_labels.remove', 'Remove') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        </div>
    </section>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '{{ csrf_token() }}';

        function gatewayAlert(type, message) {
            document.getElementById('payment_gateway_alert').innerHTML =
                '<div class="alert alert-' + type + ' py-2">' + message + '</div>';
        }

        function saveGateway(gateway, enabled) {
            var card = document.querySelector('[data-gateway-card="' + gateway + '"]');
            var body = { gateway: gateway, enabled: enabled ? 1 : 0 };
            card.querySelectorAll('[data-field]').forEach(function(input) {
                body[input.getAttribute('data-field')] = input.value;
            });

            fetch('{{ route('seller.payment_gateways.update') }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                })
                .then(res => res.json())
                .then(res => {
                    gatewayAlert(res.error ? 'danger' : 'success', res.message);
                    if (!res.error) {
                        setTimeout(() => window.location.reload(), 600);
                    }
                });
        }

        document.querySelectorAll('[data-save-gateway]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                saveGateway(btn.getAttribute('data-save-gateway'), true);
            });
        });

        document.querySelectorAll('[data-disable-gateway]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                saveGateway(btn.getAttribute('data-disable-gateway'), false);
            });
        });

        document.querySelectorAll('[data-remove-gateway]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                fetch('{{ route('seller.payment_gateways.destroy') }}', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ gateway: btn.getAttribute('data-remove-gateway') }),
                    })
                    .then(res => res.json())
                    .then(res => {
                        gatewayAlert(res.error ? 'danger' : 'success', res.message);
                        if (!res.error) {
                            setTimeout(() => window.location.reload(), 600);
                        }
                    });
            });
        });
    </script>
@endsection
