<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerPaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * 32-phase SaaS brief, Phase 6: lets a seller store their own gateway credentials, used in place of the
 * platform-global default when configured and enabled (app/Services/SellerPaymentGatewayService.php).
 * Only Razorpay is wired end to end this pass (app/Libraries/Razorpay.php, the two real order-aware
 * checkout entry points) - see docs/PHASE_6_PAYMENT_GATEWAYS.md for why Paystack/Stripe/PayPal/PhonePe
 * aren't. The credential fields per gateway are intentionally limited to what those entry points
 * actually read (this app's webhook receivers stay platform-global - a single fixed inbound URL per
 * gateway can't be routed to a seller without its own, larger project).
 */
class PaymentGatewayController extends Controller
{
    /** Field keys match exactly what app/Libraries/Razorpay.php's constructor reads from an override. */
    public const FIELDS = [
        'razorpay' => ['razorpay_key_id' => 'Key ID', 'razorpay_secret_key' => 'Key Secret'],
    ];

    private function sellerId(): ?int
    {
        return Seller::where('user_id', Auth::id())->value('id');
    }

    public function index()
    {
        $sellerId = $this->sellerId();
        $rows = SellerPaymentGateway::where('seller_id', $sellerId)->get()->keyBy('gateway');

        // Never echo a decrypted secret back into the page - the view only ever learns whether a field
        // is already configured (to render a "•••• saved" placeholder), never its value.
        $gateways = $rows->map(function (SellerPaymentGateway $row) {
            return [
                'is_enabled' => $row->is_enabled,
                'configured_fields' => array_keys(array_filter($row->credentials ?? [])),
            ];
        });

        return view('seller.pages.tables.payment_gateways', [
            'gateways' => $gateways,
            'fields' => self::FIELDS,
        ]);
    }

    public function update(Request $request)
    {
        $gateway = $request->input('gateway');
        if (!array_key_exists($gateway, self::FIELDS)) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.invalid_gateway', 'Invalid gateway.')]);
        }

        $rules = ['enabled' => 'required|boolean'];
        foreach (array_keys(self::FIELDS[$gateway]) as $field) {
            $rules[$field] = 'required_if:enabled,1|string';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $sellerId = $this->sellerId();
        if (!$sellerId) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.seller_not_found', 'Seller not found.')]);
        }

        $enabled = (bool) $request->input('enabled');
        $attributes = ['is_enabled' => $enabled];

        // Disabling (no re-typed fields required by the validation above) must not wipe out already-saved
        // credentials - only an enable submission, which requires every field, replaces them.
        if ($enabled) {
            $credentials = [];
            foreach (array_keys(self::FIELDS[$gateway]) as $field) {
                $credentials[$field] = $request->input($field);
            }
            $attributes['credentials'] = $credentials;
        }

        $existing = SellerPaymentGateway::where('seller_id', $sellerId)->where('gateway', $gateway)->first();
        if (!$existing && !$enabled) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.nothing_to_disable', 'Nothing configured for this gateway yet.')]);
        }

        SellerPaymentGateway::updateOrCreate(
            ['seller_id' => $sellerId, 'gateway' => $gateway],
            $attributes
        );

        return response()->json(['error' => false, 'message' => labels('admin_labels.payment_gateway_updated', 'Payment gateway updated.')]);
    }

    public function destroy(Request $request)
    {
        $sellerId = $this->sellerId();
        $gateway = $request->input('gateway');

        SellerPaymentGateway::where('seller_id', $sellerId)->where('gateway', $gateway)->delete();

        return response()->json(['error' => false, 'message' => labels('admin_labels.payment_gateway_removed', 'Payment gateway removed.')]);
    }
}
