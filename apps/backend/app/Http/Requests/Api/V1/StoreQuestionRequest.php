<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => filled($this->input('phone')) ? Phone::normalize($this->input('phone')) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'regex:'.Phone::PATTERN],
            'question' => ['required', 'string', 'min:5', 'max:500'],
            // Honeypot: real visitors never see or fill this.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।'];
    }
}
