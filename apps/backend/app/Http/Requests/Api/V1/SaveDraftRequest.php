<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequest extends FormRequest
{
    use CartItemsRules;

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'draft_id' => ['required', 'uuid'],
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'name' => ['nullable', 'string', 'max:100'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'thana_id' => ['nullable', 'integer', 'exists:thanas,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            ...$this->cartItemRules(),
            'utm' => ['nullable', 'array:utm_source,utm_medium,utm_campaign,utm_content,utm_term'],
            'utm.*' => ['nullable', 'string', 'max:150'],
        ];
    }
}
