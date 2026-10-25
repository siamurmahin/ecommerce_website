<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Product;
use App\Domain\Review\Enums\QuestionStatus;
use App\Domain\Review\Models\ProductQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuestionRequest;
use App\Http\Resources\V1\ProductQuestionResource;
use App\Support\ClientIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductQuestionController extends Controller
{
    /** Answered questions, newest first. */
    public function index(string $slug): AnonymousResourceCollection
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();
        $questions = ProductQuestion::query()->where('product_id', $product->id)->published()->latest('id')->paginate(5);

        return ProductQuestionResource::collection($questions);
    }

    /** Anyone can ask; it shows up once staff answer it. */
    public function store(StoreQuestionRequest $request, string $slug): JsonResponse
    {
        $product = Product::visible()->where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        ProductQuestion::create([
            'product_id' => $product->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'question' => $data['question'],
            'status' => QuestionStatus::Pending,
            'ip' => ClientIp::of($request),
        ]);

        return response()->json(['message' => 'প্রশ্ন পাঠানো হয়েছে। উত্তর দেওয়া হলে এখানে দেখাবে।'], 201);
    }
}
