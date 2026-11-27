<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'code' => Phone::latinDigits((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
            'code' => ['required', 'digits:6'],
            /** Only used when this sign-in creates the account. */
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
        ];
    }
}
