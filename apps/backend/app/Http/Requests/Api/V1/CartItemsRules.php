<?php

namespace App\Http\Requests\Api\V1;

/** Validation rules for a storefront cart payload, shared by quote and order requests. */
trait CartItemsRules
{
    protected function cartItemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.bump_id' => ['nullable', 'integer'],
            'landing_page_id' => ['nullable', 'integer'],
            // Position of the chosen package in the landing page's order form.
            'package' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }
}
