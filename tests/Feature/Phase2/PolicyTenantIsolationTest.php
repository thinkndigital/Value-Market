<?php

namespace Tests\Feature\Phase2;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): regression tests proving the new Policy classes
 * (Order, OrderItems, Transaction, Address, Seller, User) actually enforce tenant isolation - the same
 * "own resource only" boundary this phase's IDOR sweep (docs/PHASE_2_IDOR_AUDIT.md) is auditing at the
 * controller level, checked here directly against real Gate calls with real seeded rows.
 */
class PolicyTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $roleId): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => $roleId,
        ]);
    }

    private function makeSeller(User $user): Seller
    {
        return Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);
    }

    // --- AddressPolicy -------------------------------------------------------------------------------

    public function test_address_policy_grants_view_only_to_the_owner(): void
    {
        $owner = $this->makeUser(Role::CUSTOMER);
        $stranger = $this->makeUser(Role::CUSTOMER);
        $address = Address::forceCreate(['user_id' => $owner->id, 'is_default' => 0]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $address));
        $this->assertTrue(Gate::forUser($stranger)->denies('view', $address));
    }

    // --- SellerPolicy ----------------------------------------------------------------------------------

    public function test_seller_policy_grants_manage_only_to_the_owning_seller(): void
    {
        $owner = $this->makeUser(Role::SELLER);
        $other = $this->makeUser(Role::SELLER);
        $sellerRecord = $this->makeSeller($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $sellerRecord));
        $this->assertTrue(
            Gate::forUser($other)->denies('update', $sellerRecord),
            'A seller must never be authorized to manage another seller\'s store record.'
        );
    }

    // --- TransactionPolicy (wallet ledger) --------------------------------------------------------------

    public function test_transaction_policy_grants_view_only_to_the_owning_user(): void
    {
        $owner = $this->makeUser(Role::CUSTOMER);
        $stranger = $this->makeUser(Role::CUSTOMER);
        $transaction = Transaction::create([
            'transaction_type' => 'credit',
            'user_id' => $owner->id,
            'amount' => 100,
            'message' => 'test credit',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $transaction));
        $this->assertTrue(
            Gate::forUser($stranger)->denies('view', $transaction),
            'A user must never be able to view another user\'s wallet transaction.'
        );
    }

    // --- UserPolicy (self-service account access) -------------------------------------------------------

    public function test_user_policy_grants_view_of_self_but_not_of_another_customer(): void
    {
        $self = $this->makeUser(Role::CUSTOMER);
        $other = $this->makeUser(Role::CUSTOMER);

        $this->assertTrue(Gate::forUser($self)->allows('view', $self));
        $this->assertTrue(
            Gate::forUser($self)->denies('view', $other),
            'A customer must never be authorized to view another customer\'s account by guessing its id.'
        );
    }

    public function test_user_policy_grants_admin_view_of_any_customer(): void
    {
        $admin = $this->makeUser(Role::ADMIN);
        $customer = $this->makeUser(Role::CUSTOMER);

        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $customer),
            'Admin/editor staff manage user accounts as their actual job - UserPolicy must not block that.'
        );
    }

    // --- OrderPolicy / OrderItemsPolicy (multi-vendor order isolation) -----------------------------------

    private function makeOrderWithItem(User $customer, Seller $seller): array
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'mobile' => '9999999999',
            'total' => 100,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'product_variant_id' => 1,
            'quantity' => 1,
            'price' => 100,
            'sub_total' => 100,
            'status' => 'placed',
            'order_type' => 'delivery',
        ]);

        return [$order, $orderItem];
    }

    public function test_order_and_order_item_policies_grant_the_purchasing_customer(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [$order, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $this->assertTrue(Gate::forUser($customer)->allows('view', $order));
        $this->assertTrue(Gate::forUser($customer)->allows('view', $orderItem));
    }

    public function test_order_and_order_item_policies_grant_the_fulfilling_seller(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [$order, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $this->assertTrue(Gate::forUser($sellerOwner)->allows('view', $order));
        $this->assertTrue(Gate::forUser($sellerOwner)->allows('update', $orderItem));
    }

    public function test_order_and_order_item_policies_deny_an_unrelated_seller(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [$order, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $otherSellerOwner = $this->makeUser(Role::SELLER);
        $this->makeSeller($otherSellerOwner);

        $this->assertTrue(
            Gate::forUser($otherSellerOwner)->denies('view', $order),
            'A seller must never see an order they have no items in.'
        );
        $this->assertTrue(
            Gate::forUser($otherSellerOwner)->denies('update', $orderItem),
            'A seller must never be able to manage another seller\'s order item.'
        );
    }

    public function test_order_and_order_item_policies_deny_an_unrelated_customer(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [$order, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $otherCustomer = $this->makeUser(Role::CUSTOMER);

        $this->assertTrue(
            Gate::forUser($otherCustomer)->denies('view', $order),
            'A customer must never be able to view another customer\'s order by guessing its id.'
        );
        $this->assertTrue(Gate::forUser($otherCustomer)->denies('view', $orderItem));
    }

    public function test_order_item_policy_grants_the_assigned_delivery_boy(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $deliveryBoy = $this->makeUser(Role::DELIVERY_BOY);
        $orderItem->forceFill(['delivery_boy_id' => $deliveryBoy->id])->save();

        $this->assertTrue(Gate::forUser($deliveryBoy)->allows('view', $orderItem));
        $this->assertTrue(Gate::forUser($deliveryBoy)->allows('update', $orderItem));
    }

    public function test_order_item_policy_denies_an_unassigned_delivery_boy(): void
    {
        $customer = $this->makeUser(Role::CUSTOMER);
        $sellerOwner = $this->makeUser(Role::SELLER);
        $seller = $this->makeSeller($sellerOwner);
        [, $orderItem] = $this->makeOrderWithItem($customer, $seller);

        $otherDeliveryBoy = $this->makeUser(Role::DELIVERY_BOY);

        $this->assertTrue(
            Gate::forUser($otherDeliveryBoy)->denies('update', $orderItem),
            'A delivery boy must never be able to manage an order item they were not assigned.'
        );
    }

    public function test_super_admin_bypasses_every_new_policy(): void
    {
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN);
        $customer = $this->makeUser(Role::CUSTOMER);
        $address = Address::forceCreate(['user_id' => $customer->id, 'is_default' => 0]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $address));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $customer));
    }
}
