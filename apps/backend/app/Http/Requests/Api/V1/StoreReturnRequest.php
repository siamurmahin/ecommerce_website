<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Returns\Enums\ReturnType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'type' => ['required', Rule::enum(ReturnType::class)],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
