<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class QuoteCartRequest extends FormRequest
{
    use CartItemsRules;

    public function rules(): array
    {
        return [
            ...$this->cartItemRules(),
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
