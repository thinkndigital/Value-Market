<?php

namespace Tests\Feature\Phase21;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\ComboProductRating;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 21 (32-phase SaaS brief), batch 2: routes/api.php's 47 POST/PUT/DELETE routes (customer-facing
 * mobile API), the scope CustomerApiRouteSweepTest (batch 1, GET routes) deliberately deferred. Same
 * methodology: real HTTP kernel, real Sanctum bearer token, real seeded fixtures, real request bodies built
 * from each method's actual validation rules (not guessed) - see docs/PHASE_21_API_AUDIT.md for how those
 * rules were sourced. Destructive routes (delete_*, clear_cart) are hit last, each against its own
 * dedicated fixture, so nothing gets removed out from under a later check - same discipline as this
 * session's earlier Phase 2 param-route sweeps.
 */
class CustomerApiMutationRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private array $failures = [];

    /**
     * ipn/handle_paystack_callback-adjacent webhook-style endpoints and payment-gateway routes that need a
     * live external gateway session - same exclusion reasoning as the GET-route batch's SKIP_ROUTES.
     */
    private const SKIP_ROUTES = [
        'api/ipn', 'api/phonepe_app', 'api/razorpay_create_order', 'api/check_shiprocket_serviceability',
        'api/test-email',
    ];

    /**
     * Bearer token used for the authenticated calls below - set per-call (not via a global default
     * header) deliberately. Laravel's test client keeps ONE application/AuthManager instance alive across
     * every simulated request within a single test method (unlike real HTTP requests, each of which boots
     * a genuinely fresh app container) - a global default Authorization header therefore leaks Sanctum's
     * resolved guard state into the intentionally-unauthenticated routes below (verify_user, sign_up, ...)
     * in a way no two real, separate HTTP requests ever could. Passing the header explicitly per call, and
     * only for the routes that need it, keeps this sweep honest about what's a real route bug versus a
     * test-harness-only artifact.
     */
    private ?string $authToken = null;

    private function hitPost(string $uri, array $data = [], bool $authed = true): void
    {
        $this->hitVerb('post', $uri, $data, $authed);
    }

    private function hitPut(string $uri, array $data = [], bool $authed = true): void
    {
        $this->hitVerb('put', $uri, $data, $authed);
    }

    private function hitDelete(string $uri, array $data = [], bool $authed = true): void
    {
        $this->hitVerb('delete', $uri, $data, $authed);
    }

    private function hitVerb(string $verb, string $uri, array $data, bool $authed): void
    {
        if (in_array($uri, self::SKIP_ROUTES, true)) {
            return;
        }
        $headers = ['Accept' => 'application/json'];
        if ($authed && $this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        try {
            $response = $this->$verb($uri, $data, $headers);
            if ($response->getStatusCode() >= 500) {
                $body = json_decode($response->getContent(), true);
                $this->failures[$uri] = ($body['exception'] ?? 'Unknown') . ': ' . ($body['message'] ?? $response->getStatusCode())
                    . ' @ ' . ($body['file'] ?? '?') . ':' . ($body['line'] ?? '?');
            }
        } catch (\Throwable $e) {
            $this->failures[$uri] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        }
    }

    public function test_customer_api_mutation_routes_render_without_a_server_error(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([])]);

        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $customer = User::forceCreate([
            'username' => 'api_mut_customer_' . uniqid(), 'password' => bcrypt('x'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9994440001',
            'email' => 'api_mut_' . uniqid() . '@example.com',
        ]);
        $this->authToken = $customer->createToken('sweep')->plainTextToken;

        $sellerUser = User::forceCreate([
            'username' => 'api_mut_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => 1, 'slug' => 'cat-mapi-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-mapi-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        $comboProduct = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo']), 'short_description' => json_encode(['en' => 'x']),
            'description' => json_encode(['en' => 'x']), 'seller_id' => $seller->id, 'product_type' => 'simple_product',
            'product_ids' => (string) $product->id, 'price' => 20, 'stock' => 5, 'availability' => 1,
            'status' => 1, 'store_id' => 1, 'slug' => 'combo-mapi-' . uniqid(),
        ]);

        $address = Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Home', 'type' => 'home', 'mobile' => '9994440001',
            'address' => 'Test street', 'city' => 'City', 'area' => 'Area', 'pincode' => '11937',
            'country_code' => '+1', 'state' => 'State', 'country' => 'Country', 'is_default' => 1,
        ]);

        $ticketType = TicketType::forceCreate(['title' => json_encode(['en' => 'General'])]);
        $ticket = Ticket::forceCreate([
            'ticket_type_id' => $ticketType->id, 'user_id' => $customer->id, 'subject' => 'Help',
            'email' => $customer->email, 'description' => 'I need help', 'status' => 'open',
        ]);
        TicketMessage::forceCreate([
            'user_type' => 'admin', 'user_id' => $sellerUser->id, 'ticket_id' => $ticket->id, 'message' => 'How can I help?',
        ]);

        $orderData = [
            'user_id' => $customer->id, 'mobile' => '9994440001', 'total' => 20, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ];
        $status = json_encode([['awaiting', now()->format('d-m-Y h:i:sa')]]);
        $order = Order::forceCreate($orderData);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);

        $productRating = ProductRating::forceCreate(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 4, 'comment' => 'Good']);
        $comboRating = ComboProductRating::forceCreate(['user_id' => $customer->id, 'product_id' => $comboProduct->id, 'rating' => 4, 'comment' => 'Good']);

        $cartItem = Cart::forceCreate(['user_id' => $customer->id, 'store_id' => 1, 'product_variant_id' => $variant->id, 'qty' => 1, 'is_saved_for_later' => 0, 'product_type' => 'regular']);

        // --- Non-destructive, auth-required ---
        $this->hitPut('api/update_fcm', ['fcm_id' => 'test-fcm-token']);
        $this->hitPut('api/update_user', ['username' => 'Updated Name']);
        $this->hitPost('api/add_to_favorites', ['is_seller' => 0, 'product_id' => $product->id, 'product_type' => 'regular']);
        $this->hitPost('api/remove_from_favorites', ['is_seller' => 0, 'product_id' => $product->id, 'product_type' => 'regular']);
        $this->hitPut('api/update_address', ['id' => $address->id, 'name' => 'Home Updated']);
        $this->hitPost('api/validate_promo_code', ['promo_code' => 'NOPE', 'final_total' => 50]);
        $this->hitPost('api/manage_cart', ['product_variant_id' => $variant->id, 'qty' => 2, 'store_id' => 1, 'product_type' => 'regular']);
        $this->hitPost('api/update_order_item_status', ['order_item_id' => $orderItem->id, 'status' => 'cancelled', 'reason' => 'test']);
        $this->hitPost('api/add_ticket', ['ticket_type_id' => $ticketType->id, 'subject' => 'New', 'email' => $customer->email, 'description' => 'desc']);
        $this->hitPut('api/edit_ticket', ['ticket_id' => $ticket->id, 'ticket_type_id' => $ticketType->id, 'subject' => 'Updated', 'email' => $customer->email, 'description' => 'desc', 'status' => 'open']);
        $this->hitPost('api/check_cart_products_delivarable', ['address_id' => $address->id, 'store_id' => 1]);
        $this->hitPost('api/add_product_faqs', ['product_id' => $product->id, 'question' => 'Does this work?', 'product_type' => 'regular']);
        $this->hitPost('api/send_message', ['user_type' => 'user', 'ticket_id' => $ticket->id, 'message' => 'Thanks']);
        $this->hitPost('api/add_transaction', [
            'user_id' => $customer->id, 'order_id' => $order->id, 'type' => 'wallet', 'txn_id' => 'txn-' . uniqid(),
            'amount' => 10, 'status' => 'success', 'message' => 'test', 'payment_method' => 'wallet',
        ]);
        $this->hitPost('api/set_product_rating', ['product_id' => $product->id, 'rating' => 5, 'title' => 'Great']);
        $this->hitPost('api/send_withdrawal_request', ['payment_address' => 'test@paypal.com', 'amount' => 5]);
        $this->hitPost('api/send_bank_transfer_proof', ['order_id' => $order->id]);
        $this->hitPost('api/download_link_hash', ['order_item_id' => $orderItem->id]);
        $this->hitPost('api/set_combo_product_rating', ['product_id' => $comboProduct->id, 'rating' => 5, 'title' => 'Great']);

        // --- No-auth routes (authed: false - see $authToken's docblock for why this matters here).
        // verify_user/verify_otp/resend_otp/sign_up call Auth::login() internally; forgetGuards() clears
        // the AuthManager's resolved-guard cache the earlier authenticated calls above left behind, so
        // Auth::guard()'s default-driver resolution here matches what a genuinely separate, fresh HTTP
        // request would see in production (each of which boots its own AuthManager) rather than whatever
        // guard instance happened to be resolved last within this one shared test-process app container.
        \Illuminate\Support\Facades\Auth::forgetGuards();
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $this->hitPost('api/register_user', [
            'name' => 'Sweep User', 'email' => 'sweep_' . uniqid() . '@example.com', 'mobile' => '9994440099',
            'country_code' => '+1', 'password' => 'password123',
        ], false);
        $this->hitPost('api/verify_user', ['mobile' => '9994440001'], false);
        $this->hitPost('api/verify_otp', ['mobile' => '9994440001', 'otp' => '123456'], false);
        $this->hitPost('api/resend_otp', ['mobile' => '9994440001'], false);
        $this->hitPost('api/reset_password', ['mobile_no' => '9994440001'], false);
        $this->hitPost('api/sign_up', ['mobile' => '9994440001', 'type' => 'firebase'], false);
        $this->hitPost('api/validate_refer_code', ['referral_code' => 'FRESHCODE' . uniqid()], false);
        $this->hitPost('api/is_product_delivarable', ['product_id' => $product->id, 'product_type' => 'regular', 'zipcode' => '11937'], false);
        $this->hitPost('api/is_seller_delivarable', ['seller_id' => $seller->id, 'store_id' => 1, 'zipcode' => '11937'], false);
        $this->hitPost('api/search_products', ['store_id' => 1, 'search' => 'Product'], false);
        $this->hitPost('api/get_most_searched_history', ['store_id' => 1, 'search' => 'Product'], false);

        // --- place_order: full checkout ---
        $this->hitPost('api/place_order', [
            'is_wallet_used' => 0, 'store_id' => 1, 'order_payment_currency_code' => 'USD', 'status' => 'awaiting',
            'address_id' => $address->id, 'payment_method' => 'cod',
        ]);

        // --- Destructive, each against its own dedicated fixture, ordered last ---
        $ratingForDelete = ProductRating::forceCreate(['user_id' => $customer->id, 'product_id' => $product->id, 'rating' => 3, 'comment' => 'x']);
        $this->hitDelete('api/delete_product_rating', ['rating_id' => $ratingForDelete->id]);

        $comboRatingForDelete = ComboProductRating::forceCreate(['user_id' => $customer->id, 'product_id' => $comboProduct->id, 'rating' => 3, 'comment' => 'x']);
        $this->hitDelete('api/delete_combo_product_rating', ['rating_id' => $comboRatingForDelete->id]);

        $orderForStatusUpdate = Order::forceCreate($orderData);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $orderForStatusUpdate->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);
        $this->hitPut('api/update_order_status', ['order_id' => $orderForStatusUpdate->id, 'status' => 'cancelled']);

        $orderForDelete = Order::forceCreate($orderData);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $orderForDelete->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);
        $this->hitDelete('api/delete_order', ['order_id' => $orderForDelete->id]);

        $addressForDelete = Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Work', 'type' => 'work', 'mobile' => '9994440001',
            'address' => 'Other street', 'city' => 'City', 'area' => 'Area', 'pincode' => '11937',
            'country_code' => '+1', 'state' => 'State', 'country' => 'Country', 'is_default' => 0,
        ]);
        $this->hitDelete('api/delete_address', ['id' => $addressForDelete->id]);

        $cartItemForRemove = Cart::forceCreate(['user_id' => $customer->id, 'store_id' => 1, 'product_variant_id' => $variant->id, 'qty' => 1, 'is_saved_for_later' => 0, 'product_type' => 'regular']);
        $this->hitDelete('api/remove_from_cart', ['product_variant_id' => $variant->id, 'store_id' => 1, 'product_type' => 'regular', 'is_saved_for_later' => 0]);

        $this->hitPost('api/clear_cart');

        // delete_user / delete_social_account end the account - genuinely last, on their own throwaway users.
        $userForDelete = User::forceCreate([
            'username' => 'api_mut_del_' . uniqid(), 'password' => bcrypt('DeleteMe123'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9994440077',
        ]);
        $this->authToken = $userForDelete->createToken('sweep-delete')->plainTextToken;
        $this->hitPost('api/delete_user', ['mobile' => '9994440077', 'password' => 'DeleteMe123']);

        $userForSocialDelete = User::forceCreate([
            'username' => 'api_mut_soc_del_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9994440078',
        ]);
        $this->authToken = $userForSocialDelete->createToken('sweep-social-delete')->plainTextToken;
        $this->hitPost('api/delete_social_account');

        $this->assertEmpty($this->failures, "Customer API mutation route sweep breakage (route => status/error):\n" . json_encode($this->failures, JSON_PRETTY_PRINT));
    }
}
