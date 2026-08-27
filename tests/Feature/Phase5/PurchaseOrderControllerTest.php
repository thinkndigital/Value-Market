<?php

namespace Tests\Feature\Phase5;

use App\Http\Controllers\Seller\PurchaseOrderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    private function makeVariant(Seller $seller): Product_variants
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);

        return Product_variants::forceCreate(['product_id' => $product->id, 'price' => 15, 'status' => 1, 'stock' => 0]);
    }

    public function test_a_seller_cannot_create_a_po_against_another_sellers_supplier(): void
    {
        $seller = $this->makeSeller();
        $stranger = $this->makeSeller();
        $strangerSupplier = Supplier::forceCreate(['seller_id' => $stranger->id, 'name' => 'Stranger Supplier', 'status' => Supplier::STATUS_ACTIVE]);
        $variant = $this->makeVariant($seller);

        Auth::login(User::find($seller->user_id));

        $response = app(PurchaseOrderController::class)->store(new Request([
            'supplier_id' => $strangerSupplier->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 5, 'unit_cost' => 3]],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, PurchaseOrder::where('seller_id', $seller->id)->count());
    }

    public function test_a_seller_cannot_receive_goods_against_another_sellers_purchase_order(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $supplier = Supplier::forceCreate(['seller_id' => $owner->id, 'name' => 'Supplier', 'status' => Supplier::STATUS_ACTIVE]);
        $variant = $this->makeVariant($owner);

        Auth::login(User::find($owner->user_id));
        $createResponse = app(PurchaseOrderController::class)->store(new Request([
            'supplier_id' => $supplier->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 5, 'unit_cost' => 3]],
        ]));
        $poId = json_decode($createResponse->getContent(), true)['data']['id'];
        $poItemId = json_decode($createResponse->getContent(), true)['data']['items'][0]['id'];

        Auth::login(User::find($stranger->user_id));
        $response = app(PurchaseOrderController::class)->receive(new Request([
            'items' => [['purchase_order_item_id' => $poItemId, 'quantity_received' => 5]],
        ]), $poId);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, (int) $variant->fresh()->stock);
    }

    public function test_the_owning_seller_can_create_and_receive_a_purchase_order_end_to_end(): void
    {
        $seller = $this->makeSeller();
        $supplier = Supplier::forceCreate(['seller_id' => $seller->id, 'name' => 'Supplier', 'status' => Supplier::STATUS_ACTIVE]);
        $branch = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Branch', 'status' => Branch::STATUS_ACTIVE]);
        $variant = $this->makeVariant($seller);

        Auth::login(User::find($seller->user_id));

        $createResponse = app(PurchaseOrderController::class)->store(new Request([
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 20, 'unit_cost' => 3.5]],
        ]));
        $created = json_decode($createResponse->getContent(), true);
        $this->assertFalse($created['error']);

        $receiveResponse = app(PurchaseOrderController::class)->receive(new Request([
            'branch_id' => $branch->id,
            'items' => [['purchase_order_item_id' => $created['data']['items'][0]['id'], 'quantity_received' => 20]],
        ]), $created['data']['id']);
        $received = json_decode($receiveResponse->getContent(), true);

        $this->assertFalse($received['error']);
        $this->assertSame(20, (int) $variant->fresh()->stock);
    }
}
