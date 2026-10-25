<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'code' => Phone::latinDigits((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'code' => ['required', 'digits:6'],
            /** Only used when this sign-in creates the account. */
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
        ];
    }
}
