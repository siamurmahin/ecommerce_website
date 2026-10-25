<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Shipping\Models\Thana;
use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'thana_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Thana::active()->where('district_id', $this->input('district_id'))->whereKey($value)->exists()) {
                    $fail('থানা/উপজেলা এই জেলার নয়।');
                }
            }],
            'address' => ['required', 'string', 'min:8', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }
}
