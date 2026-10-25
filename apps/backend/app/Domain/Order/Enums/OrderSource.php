<?php

namespace App\Domain\Order\Enums;

use Filament\Support\Contracts\HasLabel;

/** Where an order came from. Storefront checkout is `web`; the rest are entered by staff. */
enum OrderSource: string implements HasLabel
{
    case Web = 'web';
    case Phone = 'phone';
    case Facebook = 'facebook';
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case WalkIn = 'walk_in';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'ওয়েবসাইট',
            self::Phone => 'ফোন কল',
            self::Facebook => 'Facebook / Messenger',
            self::WhatsApp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::WalkIn => 'দোকানে এসে',
        };
    }
}
