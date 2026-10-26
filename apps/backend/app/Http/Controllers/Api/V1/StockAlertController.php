<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockAlert;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockAlertRequest;
use Illuminate\Http\JsonResponse;

class StockAlertController extends Controller
{
    /** "Tell me when it's back" for an out-of-stock product or variant; asking twice keeps one request. */
    public function store(StoreStockAlertRequest $request, string $slug): JsonResponse
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();
        $variantId = $request->validated('variant_id');
        if ($variantId !== null) {
            abort_unless($product->variants()->whereKey($variantId)->exists(), 422, 'এই ভ্যারিয়েন্টটি পাওয়া যায়নি।');
        }

        StockAlert::query()->pending()->firstOrCreate([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'phone' => $request->validated('phone'),
        ]);

        return response()->json(['message' => 'স্টকে এলেই SMS-এ জানাব।'], 201);
    }
}
