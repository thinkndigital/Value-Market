<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Category;
use App\Models\StorageType;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Services\MediaService;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/** Wholesaler's own catalog CRUD (the listings a seller can later browse/import - see
 * Seller\WholesalerMarketplaceController). A new listing starts status=0 (pending admin approval),
 * matching Admin\WholesalerController's moderation queue. */
class ProductController extends Controller
{
    use HandlesValidation;

    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $categories = Category::where('status', 1)->get();
        return view('wholesaler.pages.views.products.index', compact('categories'));
    }

    public function list(Request $request)
    {
        $wholesaler = $this->currentWholesaler();
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = trim((string) $request->input('search', ''));

        $query = WholesalerProduct::where('wholesaler_id', $wholesaler->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%$search%")
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) like ?", ["%$search%"]);
                });
            });

        $total = $query->count();
        $products = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $statusLabels = [
            0 => '<span class="badge bg-warning">' . labels('wholesaler_labels.pending_approval', 'Pending Approval') . '</span>',
            1 => '<span class="badge bg-success">' . labels('wholesaler_labels.active', 'Active') . '</span>',
            2 => '<span class="badge bg-danger">' . labels('wholesaler_labels.rejected', 'Rejected') . '</span>',
        ];

        $rows = $products->map(function ($p) use ($statusLabels) {
            $name = json_decode($p->name, true);
            return [
                'id' => $p->id,
                'image' => '<img src="' . app(MediaService::class)->getMediaImageUrl($p->image) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">',
                'name' => $name['en'] ?? '',
                'wholesale_price' => $p->wholesale_price,
                'min_order_qty' => $p->min_order_qty,
                'stock' => $p->stock,
                'status' => $statusLabels[(int) $p->status] ?? $p->status,
                'operate' => '<button type="button" class="btn btn-sm btn-primary edit-wholesaler-product" data-id="' . $p->id . '"><i class="bx bx-edit"></i></button> '
                    . '<button type="button" class="btn btn-sm btn-danger delete-wholesaler-product" data-id="' . $p->id . '"><i class="bx bx-trash"></i></button>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function store(Request $request, $id = null)
    {
        $wholesaler = $this->currentWholesaler();

        $rules = [
            'name' => 'required|string|max:255',
            // The seller-owned `products` row an import creates has a NOT NULL category_id (every product
            // in this app belongs to a category) - required here too so an import can never violate that.
            'category_id' => 'required|exists:categories,id',
            'wholesale_price' => 'required|numeric|min:0',
            'min_order_qty' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => $id ? 'nullable|image' : 'required|image',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $product = $id ? WholesalerProduct::where('wholesaler_id', $wholesaler->id)->find($id) : new WholesalerProduct();
        if ($id && !$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        if ($request->hasFile('image')) {
            $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
            $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->id : 1;
            $disk = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
            $media = StorageType::find($mediaStorageType);

            $uploaded = $media->addMedia($request->file('image'))
                ->sanitizingFileName(function ($fileName) {
                    $sanitized = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));
                    $uniqueId = time() . '_' . mt_rand(1000, 9999);
                    $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
                    $baseName = pathinfo($sanitized, PATHINFO_FILENAME);
                    return "{$baseName}-{$uniqueId}.{$extension}";
                })
                ->toMediaCollection('wholesaler_products', $disk);

            // Live QA finding: App\Services\CustomPathGenerator stores every media collection's files under
            // a `{collection_name}/` subfolder on disk - MediaService::getMediaImageUrl() checks for the
            // exact stored string under public_path('storage/'), so it must include that subfolder too, or
            // the file 404s and silently falls back to the generic "no image" placeholder.
            $product->image = $disk === 's3' ? $uploaded->getUrl() : '/wholesaler_products/' . $uploaded->file_name;
        }

        $product->wholesaler_id = $wholesaler->id;
        $product->category_id = $request->category_id ?: null;
        $product->name = json_encode(['en' => $request->name], JSON_UNESCAPED_UNICODE);
        $product->description = $request->description;
        $product->wholesale_price = $request->wholesale_price;
        $product->min_order_qty = $request->min_order_qty;
        $product->stock = $request->stock;
        $product->affiliate_enabled = $request->boolean('affiliate_enabled');
        $product->affiliate_commission_rate = $request->affiliate_commission_rate ?: null;
        if (!$id) {
            $product->slug = generateSlug($request->name, 'wholesaler_products');
            // Every new/edited listing goes back through admin moderation before it's marketplace-visible.
            $product->status = 0;
        } elseif ($product->isDirty(['name', 'wholesale_price', 'category_id', 'image'])) {
            $product->status = 0;
        }
        $product->save();

        return response()->json(['message' => $id
            ? labels('wholesaler_labels.product_updated', 'Product updated successfully. Re-submitted for admin approval.')
            : labels('wholesaler_labels.product_created', 'Product added successfully. Waiting for admin approval.')]);
    }

    public function edit($id)
    {
        $wholesaler = $this->currentWholesaler();
        $product = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->findOrFail($id);
        $name = json_decode($product->name, true);

        return response()->json([
            'id' => $product->id,
            'name' => $name['en'] ?? '',
            'category_id' => $product->category_id,
            'description' => $product->description,
            'wholesale_price' => $product->wholesale_price,
            'min_order_qty' => $product->min_order_qty,
            'stock' => $product->stock,
            'affiliate_enabled' => (bool) $product->affiliate_enabled,
            'affiliate_commission_rate' => $product->affiliate_commission_rate,
            'image' => app(MediaService::class)->getMediaImageUrl($product->image),
        ]);
    }

    public function destroy($id)
    {
        $wholesaler = $this->currentWholesaler();
        $product = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->find($id);

        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        // A listing already imported by sellers stays as the source-of-record for their Product rows
        // (Product.wholesaler_product_id) - deleting it out from under them would orphan that traceability
        // link for no real benefit, so it's deactivated instead, same as products elsewhere in this app.
        if ($product->imports()->exists()) {
            $product->status = 2;
            $product->save();
            return response()->json(['message' => labels('wholesaler_labels.product_deactivated', 'Product deactivated (it has already been imported by one or more sellers, so it cannot be fully deleted).')]);
        }

        $product->delete();
        return response()->json(['message' => labels('wholesaler_labels.product_deleted', 'Product deleted successfully.')]);
    }
}
