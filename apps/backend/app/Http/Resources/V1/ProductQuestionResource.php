<?php

namespace App\Http\Resources\V1;

use App\Domain\Review\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductQuestion */
class ProductQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'question' => $this->question,
            'answer' => $this->answer,
            'created_at' => $this->created_at->toIso8601String(),
            /** @var string|null */
            'answered_at' => $this->answered_at?->toIso8601String(),
        ];
    }
}
