<?php

namespace App\Http\Resources\V1;

use App\Domain\Review\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rating' => $this->rating,
            'body' => $this->body,
            /** @var array<int, string> */
            'images' => collect($this->images ?? [])->map(fn (string $path) => Storage::disk('public')->url($path))->values()->all(),
            /** Bought and received the product. */
            'verified' => $this->is_verified,
            /** Shop's public answer. */
            'reply' => $this->reply,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
