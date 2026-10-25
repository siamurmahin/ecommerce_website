<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'regex:'.Phone::PATTERN],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।'];
    }
}
