<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Order\Enums\PaymentMethod;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    use CartItemsRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'payment_sender' => filled($this->input('payment_sender')) ? Phone::normalize($this->input('payment_sender')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:'.Phone::PATTERN],
            'email' => ['nullable', 'email', 'max:150'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'thana_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'address' => ['required', 'string', 'min:8', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Required by PlaceOrder only when the chosen method needs money sent first.
            'payment_wallet' => ['nullable', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::wallets()))],
            'payment_sender' => ['nullable', 'regex:'.Phone::PATTERN],
            'payment_trx_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{6,30}$/'],
            ...$this->cartItemRules(),
            'event_id' => ['nullable', 'string', 'max:64'],
            'landing_page_id' => ['nullable', 'integer'],
            'utm' => ['nullable', 'array:utm_source,utm_medium,utm_campaign,utm_content,utm_term'],
            'utm.utm_source' => ['nullable', 'string', 'max:150'],
            'utm.utm_medium' => ['nullable', 'string', 'max:150'],
            'utm.utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm.utm_content' => ['nullable', 'string', 'max:150'],
            'utm.utm_term' => ['nullable', 'string', 'max:150'],
            'fbp' => ['nullable', 'string', 'max:150'],
            'fbc' => ['nullable', 'string', 'max:255'],
            'ttp' => ['nullable', 'string', 'max:150'],
            // Signed-in customers only (POST /customer/orders); the guest route ignores it.
            'redeem_points' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'ttclid' => ['nullable', 'string', 'max:255'],
            'ga_client_id' => ['nullable', 'string', 'max:64'],
            'ga_session_id' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।',
            'payment_sender.regex' => 'যে নম্বর থেকে টাকা পাঠিয়েছেন সেটা সঠিকভাবে দিন।',
            'payment_trx_id.regex' => 'ট্রানজেকশন আইডি শুধু ইংরেজি অক্ষর ও সংখ্যা, ৬-৩০ অক্ষর।',
        ];
    }
}
