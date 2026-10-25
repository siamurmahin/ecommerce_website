<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Resources\V1\ProductCardResource;
use App\Http\Resources\V1\ReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /** Approved reviews of a product, newest first. */
    public function index(string $slug): AnonymousResourceCollection
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();

        $reviews = Review::query()->where('product_id', $product->id)->approved()->latest('id')->paginate(6);

        return ReviewResource::collection($reviews);
    }

    /** Products from delivered orders the customer hasn't reviewed yet. */
    public function reviewable(Request $request): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $bought = $this->deliveredOrders($customer)
            ->with('items:id,order_id,product_id')->get()
            ->flatMap(fn (Order $o) => $o->items->pluck('product_id'))->filter()->unique();

        $products = Product::visible()
            ->whereIn('id', $bought->diff($customer->reviews()->pluck('product_id')))
            ->with(ProductController::CARD_RELATIONS)
            ->get();

        return ProductCardResource::collection($products);
    }

    /**
     * Saves a review for staff to approve. Only for products the customer received.
     *
     * @response 201 array{data: ReviewResource, message: string}
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $data = $request->validated();

        $order = $this->deliveredOrders($customer)
            ->whereHas('items', fn ($q) => $q->where('product_id', $data['product_id']))
            ->latest('id')->first();

        if (! $order) {
            throw ValidationException::withMessages(['product_id' => 'ডেলিভারি পাওয়া পণ্যেরই শুধু রিভিউ দেওয়া যায়।']);
        }
        if ($customer->reviews()->where('product_id', $data['product_id'])->exists()) {
            throw ValidationException::withMessages(['product_id' => 'এই পণ্যের রিভিউ আপনি আগেই দিয়েছেন।']);
        }

        $images = collect($request->file('images', []))->map(fn ($file) => $file->store('reviews', 'public'))->values()->all();

        $review = Review::create([
            'product_id' => $data['product_id'],
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'name' => $customer->name ?: $order->customer_name,
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
            'images' => $images ?: null,
            'status' => ReviewStatus::Pending,
            'is_verified' => true,
        ]);

        return response()->json([
            'data' => new ReviewResource($review),
            'message' => 'ধন্যবাদ! যাচাইয়ের পর রিভিউটি দেখানো হবে।',
        ], 201);
    }

    private function deliveredOrders(Customer $customer)
    {
        return $customer->orders()->where('status', OrderStatus::Delivered);
    }
}
