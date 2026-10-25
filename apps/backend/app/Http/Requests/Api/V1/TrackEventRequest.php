<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tracking\ServerEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackEventRequest extends FormRequest
{
    use CartItemsRules;

    public function rules(): array
    {
        return [
            'name' => ['required', Rule::in(ServerEvent::BROWSER_REPORTED)],
            'event_id' => ['required', 'string', 'max:64'],
            'url' => ['nullable', 'url', 'max:500'],
            ...$this->cartItemRules(),
            'fbp' => ['nullable', 'string', 'max:150'],
            'fbc' => ['nullable', 'string', 'max:255'],
            'ttp' => ['nullable', 'string', 'max:150'],
            'ttclid' => ['nullable', 'string', 'max:255'],
        ];
    }
}
