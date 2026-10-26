<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAlertRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize((string) $this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'variant_id' => ['nullable', 'integer'],
            // Honeypot: real visitors never see or fill this.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।'];
    }
}
