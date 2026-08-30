@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.my_subscription', 'My Subscription') }}
@endsection
@section('content')
    <section class="main-content">
        <div class="row">
            <x-seller.breadcrumb :title="labels('admin_labels.my_subscription', 'My Subscription')" :subtitle="labels(
                'admin_labels.your_current_plan_and_limits',
                'Your Current Plan And Limits',
            )" :breadcrumbs="[['label' => labels('admin_labels.my_subscription', 'My Subscription')]]" />

            <section class="overview-data">
                @if ($plan)
                    <div class="card content-area p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">{{ $plan->name }}</h4>
                            <span class="badge bg-primary">{{ ucfirst($plan->billing_cycle) }}</span>
                        </div>
                        <p class="text-muted">{{ $plan->description }}</p>

                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="text-muted small">{{ labels('admin_labels.products_used', 'Products Used') }}</div>
                                <div class="h5">{{ $productCount }} / {{ $plan->max_products ?? labels('admin_labels.unlimited', 'Unlimited') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">{{ labels('admin_labels.commission_rate', 'Commission Rate') }}</div>
                                <div class="h5">{{ $plan->commission_rate !== null ? $plan->commission_rate . '%' : labels('admin_labels.platform_default', 'Platform Default') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">{{ labels('admin_labels.renews_on', 'Renews / Expires On') }}</div>
                                <div class="h5">{{ $seller->subscription_expires_at ? \Illuminate\Support\Carbon::parse($seller->subscription_expires_at)->format('Y-m-d') : '-' }}</div>
                            </div>
                        </div>

                        @if (!empty($plan->features))
                            <hr>
                            <h6>{{ labels('admin_labels.features', 'Features') }}</h6>
                            <ul>
                                @foreach ($plan->features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    <div class="card content-area p-4">
                        <p class="mb-0">{{ labels('admin_labels.no_subscription_plan_assigned', 'No subscription plan is currently assigned to your account. Contact support to choose a plan.') }}</p>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
