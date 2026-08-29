<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Zone;

/**
 * Changelog v1.0.9 ("Setup Progress Tracker" / "Setup completion tracking in admin dashboard"): confirmed
 * genuinely missing - no controller, model, or view existed under this or an equivalent name. Every step
 * here checks real, current configuration state (a row exists, a required Setting key is actually filled
 * in) rather than a stored/cached flag that could drift from reality, per this feature's own explicit "do
 * not use fake percentages" requirement.
 */
class SetupProgressService
{
    /**
     * @return array{percentage: int, completed_steps: int, total_steps: int, steps: array<int, array{key: string, label: string, completed: bool}>}
     */
    public function getProgress(): array
    {
        $steps = [
            [
                'key' => 'store',
                'label' => 'Store configured',
                'completed' => Store::where('status', 1)->exists(),
            ],
            [
                'key' => 'currency',
                'label' => 'Default currency set',
                'completed' => Currency::where('is_default', 1)->exists(),
            ],
            [
                'key' => 'payment_gateway',
                'label' => 'A payment method configured',
                'completed' => $this->anyPaymentGatewayConfigured(),
            ],
            [
                'key' => 'shipping',
                'label' => 'Delivery zones configured',
                'completed' => Zone::exists(),
            ],
            [
                'key' => 'language',
                'label' => 'At least one language configured',
                'completed' => Language::exists(),
            ],
            [
                'key' => 'pages',
                'label' => 'Privacy policy / terms & conditions content added',
                'completed' => $this->policyContentFilled(),
            ],
            [
                'key' => 'categories',
                'label' => 'At least one category added',
                'completed' => Category::where('status', 1)->exists(),
            ],
            [
                'key' => 'products',
                'label' => 'At least one product added',
                'completed' => Product::where('status', 1)->exists(),
            ],
            [
                'key' => 'brands',
                'label' => 'At least one brand added',
                'completed' => Brand::where('status', 1)->exists(),
            ],
        ];

        $completedCount = count(array_filter($steps, fn($step) => $step['completed']));
        $totalCount = count($steps);

        return [
            'percentage' => $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0,
            'completed_steps' => $completedCount,
            'total_steps' => $totalCount,
            'steps' => $steps,
        ];
    }

    private function anyPaymentGatewayConfigured(): bool
    {
        $settings = json_decode(app(SettingService::class)->getSettings('payment_method', true), true) ?? [];

        $gatewayKeys = [
            'razorpay_key_id', 'stripe_webhook_secret_key', 'paystack_key_id',
            'paypal_business_email', 'phonepe_merchant_id',
            // Bank Transfer needs no external credentials - it's still a real, admin-enabled payment
            // method (Admin\SettingController's payment-method save writes this same 'payment_method'
            // Setting row).
            'direct_bank_transfer_method',
        ];

        foreach ($gatewayKeys as $key) {
            if (!empty($settings[$key])) {
                return true;
            }
        }

        return false;
    }

    private function policyContentFilled(): bool
    {
        $variables = ['privacy_policy', 'terms_and_conditions'];
        $rows = Setting::whereIn('variable', $variables)->pluck('value', 'variable');

        foreach ($variables as $variable) {
            $decoded = json_decode($rows[$variable] ?? '', true) ?? [];
            if (!empty($decoded[$variable])) {
                return true;
            }
        }

        return false;
    }
}
