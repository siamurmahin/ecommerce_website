<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductCardResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The signed-in customer's saved products, so the wishlist follows them across devices. */
class WishlistController extends Controller
{
    public const LIMIT = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->list($request->user());
    }

    /** Merges a device's guest wishlist into the account and returns the combined list. */
    public function sync(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['product_ids' => ['present', 'array', 'max:'.self::LIMIT], 'product_ids.*' => ['integer']]);
        /** @var Customer $customer */
        $customer = $request->user();

        $ids = Product::visible()->whereIn('id', $data['product_ids'])->pluck('id');
        $customer->wishlist()->syncWithoutDetaching($ids->all());

        return $this->list($customer);
    }

    public function store(Request $request, int $product): AnonymousResourceCollection
    {
        $id = Product::visible()->whereKey($product)->value('id') ?? abort(404);
        /** @var Customer $customer */
        $customer = $request->user();
        $customer->wishlist()->syncWithoutDetaching([$id]);

        return $this->list($customer);
    }

    public function destroy(Request $request, int $product): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $customer->wishlist()->detach($product);

        return $this->list($customer);
    }

    private function list(Customer $customer): AnonymousResourceCollection
    {
        $products = $customer->wishlist()->visible()->with(ProductController::CARD_RELATIONS)->limit(self::LIMIT)->get();

        return ProductCardResource::collection($products);
    }
}
